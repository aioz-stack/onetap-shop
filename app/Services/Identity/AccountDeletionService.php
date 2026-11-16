<?php

namespace App\Services\Identity;

use App\Enums\AssignmentStatus;
use App\Enums\RoleName;
use App\Enums\SellerOrderStatus;
use App\Enums\ShopStatus;
use App\Enums\WithdrawalStatus;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * In-app account deletion (required by the App Store and Google Play).
 *
 * Refused while something is still open, so nobody loses money or a parcel:
 * unfinished orders, a shop with open orders / money / payouts, or a rider with open jobs or cash.
 * Admin and logistics staff accounts are managed by Onetap, not deleted from an app.
 *
 * On deletion the person can no longer sign in, and their personal details are removed:
 * name, email, username and phone are replaced, the photo and saved addresses are deleted,
 * and every token, session and push registration is revoked. Orders, payouts and the ledger keep
 * their own copies (delivery address snapshots, amounts) as business records.
 */
class AccountDeletionService
{
    private const OPEN_ORDER = [
        SellerOrderStatus::Unpaid, SellerOrderStatus::Pending, SellerOrderStatus::Processing, SellerOrderStatus::ReadyForPickup,
        SellerOrderStatus::Shipping, SellerOrderStatus::Delivered, SellerOrderStatus::ReturnRequested,
    ];

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Reasons the account can't be deleted yet (empty when it can).
     *
     * @return list<string>
     */
    public function blockers(User $user): array
    {
        $blockers = [];

        if ($user->hasRole(RoleName::Admin) || $user->hasRole(RoleName::Logistics)) {
            return ['Staff accounts are closed by Onetap. Contact your administrator.'];
        }

        if (SellerOrder::query()->whereHas('order', fn ($orders) => $orders->where('user_id', $user->id))->whereIn('status', self::OPEN_ORDER)->exists()) {
            $blockers[] = 'You have orders that are not finished yet. Wait until they are completed or cancelled.';
        }

        $shop = $user->sellerProfile?->shop;

        if ($shop) {
            if ($shop->sellerOrders()->whereIn('status', self::OPEN_ORDER)->exists()) {
                $blockers[] = 'Your shop has orders in progress.';
            }

            $balance = $shop->balance;
            if ($balance && (bccomp((string) $balance->available_balance, '0', 2) > 0 || bccomp((string) $balance->pending_balance, '0', 2) > 0)) {
                $blockers[] = 'Your shop still has a balance. Withdraw it first.';
            }

            if ($shop->withdrawals()->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Approved])->exists()) {
                $blockers[] = 'A payout to your bank is still in progress.';
            }
        }

        $rider = $user->riderProfile;

        if ($rider) {
            if ($rider->assignments()->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])->exists()) {
                $blockers[] = 'You still have delivery jobs assigned.';
            }

            if ($rider->collections()->whereNull('rider_remittance_id')->exists()) {
                $blockers[] = 'You still have COD cash to remit.';
            }
        }

        return $blockers;
    }

    /**
     * @throws ValidationException
     */
    public function delete(User $user, string $password, ?string $reason = null): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'That password is incorrect.']);
        }

        if ($blockers = $this->blockers($user)) {
            throw ValidationException::withMessages(['account' => $blockers]);
        }

        DB::transaction(function () use ($user, $reason) {
            $this->auditLogger->log('account.deleted', $user, ['username' => $user->username, 'roles' => $user->roleNames()], ['reason' => $reason], $user);

            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $user->sellerProfile?->shop?->forceFill(['status' => ShopStatus::Closed])->save();
            $user->addresses()->delete();
            $user->tokens()->delete();
            $user->deviceTokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $marker = 'deleted-'.$user->id.'-'.Str::lower(Str::random(6));
            $user->forceFill([
                'name' => 'Deleted user',
                'username' => $marker,
                'email' => $marker.'@deleted.invalid',
                'phone' => null,
                'profile_picture' => null,
                'password' => Str::random(40),
                'remember_token' => null,
            ])->save();
            $user->delete();
        });
    }
}
