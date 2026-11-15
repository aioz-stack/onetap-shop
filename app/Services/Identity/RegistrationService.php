<?php

namespace App\Services\Identity;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Create a customer account with its profile and empty cart in one transaction.
     *
     * @param  array{name: string, username: string, email: string, phone?: string|null, password: string, referral_code?: string|null}  $data
     */
    public function registerCustomer(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => UserStatus::Active,
            ]);

            $user->assignRole(RoleName::Customer);

            $referrerId = filled($data['referral_code'] ?? null)
                ? CustomerProfile::query()->where('referral_code', strtoupper($data['referral_code']))->value('user_id')
                : null;

            $user->customerProfile()->create([
                'referral_code' => $this->uniqueReferralCode(),
                'referred_by' => $referrerId,
            ]);

            $user->cart()->create();

            $this->auditLogger->log('auth.registered', $user, actor: $user);

            return $user;
        });
    }

    private function uniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (CustomerProfile::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
