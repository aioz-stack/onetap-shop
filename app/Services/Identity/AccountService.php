<?php

namespace App\Services\Identity;

use App\Enums\SellerStatus;
use App\Enums\ShopStatus;
use App\Enums\VerificationStatus;
use App\Models\SellerProfile;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\VerificationDocument;
use App\Services\Administration\AuditLogger;
use App\Services\Communication\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Self-service account actions for signed-in users.
 */
class AccountService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $data
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $picture = null, bool $removePicture = false): User
    {
        if ($picture) {
            $old = $user->profile_picture;
            $data['profile_picture'] = $picture->store('profile-pictures', 'public');

            if ($old) {
                Storage::disk('public')->delete($old);
            }
        } elseif ($removePicture && $user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
            $data['profile_picture'] = null;
        }

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }

        $user->forceFill($data)->save();
        $this->auditLogger->log('account.profile_updated', $user, actor: $user);

        return $user;
    }

    public function changePassword(User $user, string $current, string $new, ?string $keepSessionId = null): void
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }

        DB::transaction(function () use ($user, $new, $keepSessionId) {
            $user->forceFill(['password' => $new])->save();
            $this->signOutOtherDevices($user, $keepSessionId);
        });

        $this->auditLogger->log('account.password_changed', $user, actor: $user);
    }

    /**
     * End every other browser session and revoke all API tokens.
     */
    public function signOutOtherDevices(User $user, ?string $keepSessionId): int
    {
        $user->tokens()->delete();

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($keepSessionId, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveAddress(User $user, array $data, ?UserAddress $address = null): UserAddress
    {
        return DB::transaction(function () use ($user, $data, $address) {
            $makeDefault = (bool) ($data['is_default'] ?? false) || ! $user->addresses()->exists();

            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            // The current default stays default until another address takes over; there is always one.
            $data['is_default'] = $makeDefault || (bool) $address?->is_default;

            if ($address) {
                $address->update($data);

                return $address;
            }

            return $user->addresses()->create($data);
        });
    }

    public function deleteAddress(UserAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $user = $address->user;
            $address->delete();

            if ($wasDefault) {
                $user->addresses()->oldest('id')->first()?->forceFill(['is_default' => true])->save();
            }
        });
    }

    public function uploadVerification(User $user, string $type, UploadedFile $file): VerificationDocument
    {
        if ($user->verificationDocuments()->where('type', $type)->where('status', VerificationStatus::Pending)->exists()) {
            throw ValidationException::withMessages(['type' => 'A document of this type is already waiting for review.']);
        }

        $path = $file->storeAs("verification/{$user->id}", Str::uuid().'.'.$file->extension(), 'local');
        $document = $user->verificationDocuments()->create(['type' => $type, 'path' => $path, 'status' => VerificationStatus::Pending]);
        $this->auditLogger->log('verification.uploaded', $document, newValues: ['type' => $type], actor: $user);

        return $document;
    }

    /**
     * Apply to sell. The shop is created in "pending" and opens when an admin approves the seller.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyToSell(User $user, array $data): SellerProfile
    {
        $existing = SellerProfile::query()->where('user_id', $user->id)->first();

        if ($existing && $existing->status !== SellerStatus::Rejected) {
            throw ValidationException::withMessages(['business_name' => 'You already have a seller application ('.$existing->status->label().').']);
        }

        return DB::transaction(function () use ($user, $data, $existing) {
            $profile = $existing ?? new SellerProfile(['user_id' => $user->id]);
            $profile->fill([
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'tin' => $data['tin'] ?? null,
                'status' => SellerStatus::Pending,
                'rejection_reason' => null,
            ])->save();

            $shopData = [
                'name' => $data['shop_name'],
                'shop_category_id' => $data['shop_category_id'] ?? null,
                'description' => $data['description'] ?? null,
                'pickup_street' => $data['pickup_street'],
                'pickup_barangay' => $data['pickup_barangay'],
                'pickup_city' => $data['pickup_city'],
                'pickup_province' => $data['pickup_province'],
                'pickup_region' => $data['pickup_region'],
                'pickup_phone' => $data['pickup_phone'],
                'status' => ShopStatus::Pending,
            ];

            $shop = $profile->shop;
            $shop ? $shop->update($shopData) : $profile->shop()->create($shopData + ['slug' => $this->uniqueShopSlug($data['shop_name'])]);

            $this->auditLogger->log('seller.applied', $profile, newValues: ['business_name' => $data['business_name']], actor: $user);
            app(Notifier::class)->sellerApplied($profile);

            return $profile->refresh();
        });
    }

    private function uniqueShopSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $suffix = 2;

        while (Shop::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
