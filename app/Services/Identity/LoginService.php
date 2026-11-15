<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Services\Administration\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Credential checks shared by the web (session) and API (token) login flows.
 */
class LoginService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Resolve the user for an email-or-username identifier, or fail with a validation error.
     *
     * @throws ValidationException
     */
    public function verify(string $login, string $password, string $errorKey = 'login'): User
    {
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::query()->where($field, $login)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([$errorKey => 'The provided credentials do not match our records.']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([$errorKey => 'Your account is not active. Please contact support.']);
        }

        return $user;
    }

    public function recordLogin(User $user, string $channel): void
    {
        $user->forceFill(['last_login_at' => now()])->save();
        $this->auditLogger->log('auth.login', $user, newValues: ['channel' => $channel], actor: $user);
    }
}
