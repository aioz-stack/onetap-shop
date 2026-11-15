<?php

namespace App\Services\Catalog;

use App\Enums\ProductStatus;
use App\Enums\RoleName;
use App\Enums\SellerStatus;
use App\Enums\ShopStatus;
use App\Models\SellerProfile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin decisions on seller applications and seller standing.
 */
class SellerApprovalService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Approve: grant the seller role, open (or create) the shop and its balance.
     */
    public function approve(SellerProfile $profile, User $admin): SellerProfile
    {
        $this->assertStatus($profile, [SellerStatus::Pending, SellerStatus::Rejected]);

        return DB::transaction(function () use ($profile, $admin) {
            $profile->forceFill([
                'status' => SellerStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'rejection_reason' => null,
            ])->save();

            $shop = $profile->shop ?? $profile->shop()->create([
                'name' => $profile->business_name,
                'slug' => $this->uniqueSlug($profile->business_name),
                'status' => ShopStatus::Active,
            ]);
            $shop->forceFill(['status' => ShopStatus::Active])->save();
            $shop->balance()->firstOrCreate([]);

            $profile->user->assignRole(RoleName::Seller);
            $this->auditLogger->log('seller.approved', $profile, actor: $admin);

            return $profile->refresh();
        });
    }

    public function reject(SellerProfile $profile, User $admin, string $reason): SellerProfile
    {
        $this->assertStatus($profile, [SellerStatus::Pending]);

        $profile->forceFill(['status' => SellerStatus::Rejected, 'rejection_reason' => $reason])->save();
        $this->auditLogger->log('seller.rejected', $profile, newValues: ['reason' => $reason], actor: $admin);

        return $profile;
    }

    /**
     * Suspend: the shop closes, its live products are hidden, and the seller role is removed.
     */
    public function suspend(SellerProfile $profile, User $admin, string $reason): SellerProfile
    {
        $this->assertStatus($profile, [SellerStatus::Approved]);

        return DB::transaction(function () use ($profile, $admin, $reason) {
            $profile->forceFill(['status' => SellerStatus::Suspended, 'rejection_reason' => $reason])->save();

            if ($profile->shop) {
                $profile->shop->forceFill(['status' => ShopStatus::Suspended])->save();
                $profile->shop->products()->where('status', ProductStatus::Live)->update(['status' => ProductStatus::Unpublished]);
            }

            $profile->user->removeRole(RoleName::Seller);
            $this->auditLogger->log('seller.suspended', $profile, newValues: ['reason' => $reason], actor: $admin);

            return $profile;
        });
    }

    public function reinstate(SellerProfile $profile, User $admin): SellerProfile
    {
        $this->assertStatus($profile, [SellerStatus::Suspended]);

        return DB::transaction(function () use ($profile, $admin) {
            $profile->forceFill(['status' => SellerStatus::Approved, 'rejection_reason' => null])->save();
            $profile->shop?->forceFill(['status' => ShopStatus::Active])->save();
            $profile->user->assignRole(RoleName::Seller);
            $this->auditLogger->log('seller.reinstated', $profile, actor: $admin);

            return $profile;
        });
    }

    /**
     * @param  list<SellerStatus>  $allowed
     */
    private function assertStatus(SellerProfile $profile, array $allowed): void
    {
        if (! in_array($profile->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "This seller is {$profile->status->label()} and cannot be changed that way."]);
        }
    }

    private function uniqueSlug(string $name): string
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
