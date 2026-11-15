<?php

namespace App\Services\Identity;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Services\Administration\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin account management: create staff accounts, edit, suspend, reactivate, verify.
 */
class UserAccountService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, username: string, email: string, phone?: string|null, password: string}  $data
     * @param  list<RoleName>  $roles
     */
    public function create(array $data, array $roles, User $admin): User
    {
        return DB::transaction(function () use ($data, $roles, $admin) {
            $user = User::query()->create($data + ['status' => UserStatus::Active]);
            $this->syncRoles($user, $roles);

            if ($user->hasRole(RoleName::Customer)) {
                $user->customerProfile()->firstOrCreate([], ['referral_code' => strtoupper(Str::random(8))]);
                $user->cart()->firstOrCreate([]);
            }

            $this->auditLogger->log('user.created', $user, newValues: ['roles' => $user->roleNames()], actor: $admin);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<RoleName>  $roles
     */
    public function update(User $user, array $data, array $roles, User $admin): User
    {
        if ($user->is($admin) && ! in_array(RoleName::Admin, $roles, true)) {
            throw ValidationException::withMessages(['roles' => 'You cannot remove your own admin role.']);
        }

        return DB::transaction(function () use ($user, $data, $roles, $admin) {
            $old = $user->only(['name', 'email', 'phone']) + ['roles' => $user->roleNames()];

            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }

            $user->update($data);
            $this->syncRoles($user, $roles);

            $this->auditLogger->log('user.updated', $user, $old, $user->only(['name', 'email', 'phone']) + ['roles' => $user->roleNames()], $admin);

            return $user;
        });
    }

    public function suspend(User $user, User $admin, string $reason): User
    {
        if ($user->is($admin)) {
            throw ValidationException::withMessages(['status' => 'You cannot suspend your own account.']);
        }

        return DB::transaction(function () use ($user, $admin, $reason) {
            $user->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => now(), 'suspension_reason' => $reason])->save();
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $this->auditLogger->log('user.suspended', $user, newValues: ['reason' => $reason], actor: $admin);

            return $user;
        });
    }

    public function reactivate(User $user, User $admin): User
    {
        $user->forceFill(['status' => UserStatus::Active, 'suspended_at' => null, 'suspension_reason' => null])->save();
        $this->auditLogger->log('user.reactivated', $user, actor: $admin);

        return $user;
    }

    public function reviewDocument(VerificationDocument $document, VerificationStatus $decision, User $admin, ?string $notes = null): VerificationDocument
    {
        if ($document->status !== VerificationStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'This document has already been reviewed.']);
        }

        $document->forceFill(['status' => $decision, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'notes' => $notes])->save();

        if ($decision === VerificationStatus::Approved && ! $document->user->email_verified_at) {
            $document->user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->auditLogger->log('verification.'.$decision->value, $document, newValues: ['notes' => $notes], actor: $admin);

        return $document;
    }

    /**
     * @param  list<RoleName>  $roles
     */
    private function syncRoles(User $user, array $roles): void
    {
        foreach (RoleName::cases() as $role) {
            if (in_array($role, $roles, true)) {
                $user->assignRole($role);
            } elseif ($user->hasRole($role)) {
                $user->removeRole($role);
            }
        }

        $user->unsetRelation('roles');
    }
}
