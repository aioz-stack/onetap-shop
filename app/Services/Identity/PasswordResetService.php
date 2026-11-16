<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use App\Services\Administration\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Forgot-password for the mobile apps: a 6-digit code by email instead of a link.
 *
 * - Requesting a code never reveals whether an email is registered.
 * - Codes are stored hashed in `password_reset_tokens`, expire after 15 minutes and stop working
 *   after 5 wrong attempts.
 * - A successful reset signs the account out of every device (all API tokens revoked).
 */
class PasswordResetService
{
    public const EXPIRES_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private AuditLogger $auditLogger) {}

    public function sendCode(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->isActive()) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(['email' => $user->email], ['token' => Hash::make($code), 'created_at' => now()]);
        Cache::forget($this->attemptsKey($user->email));

        $user->notify(new PasswordResetCode($code, self::EXPIRES_MINUTES));
        $this->auditLogger->log('auth.password_reset_requested', $user, actor: $user);
    }

    /**
     * @throws ValidationException
     */
    public function reset(string $email, string $code, string $password): User
    {
        $invalid = fn () => ValidationException::withMessages(['code' => 'That code is invalid or has expired. Request a new one.']);
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();
        $user = User::query()->where('email', $email)->first();

        if (! $row || ! $user || ! $user->isActive()) {
            throw $invalid();
        }

        $attempts = (int) Cache::get($this->attemptsKey($email), 0);
        $expired = now()->subMinutes(self::EXPIRES_MINUTES)->greaterThan($row->created_at);

        if ($expired || $attempts >= self::MAX_ATTEMPTS) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw $invalid();
        }

        if (! Hash::check($code, $row->token)) {
            Cache::put($this->attemptsKey($email), $attempts + 1, now()->addMinutes(self::EXPIRES_MINUTES));

            throw $invalid();
        }

        DB::transaction(function () use ($user, $email, $password) {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });

        Cache::forget($this->attemptsKey($email));
        $this->auditLogger->log('auth.password_reset', $user, actor: $user);

        return $user;
    }

    private function attemptsKey(string $email): string
    {
        return 'password-reset-attempts:'.sha1(mb_strtolower($email));
    }
}
