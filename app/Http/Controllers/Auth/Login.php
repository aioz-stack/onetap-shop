<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Administration\AuditLogger;
use App\Services\Administration\SettingsService;
use App\Services\Identity\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class Login extends Controller
{
    public function __construct(
        private LoginService $loginService,
        private AuditLogger $auditLogger,
        private SettingsService $settings,
    ) {}

    /**
     * Show the login screen.
     */
    public function showLoginForm(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Attempt to log the user in with an email or username.
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $throttleKey = Str::lower($validated['login']).'|'.$request->ip();
        $maxAttempts = (int) $this->settings->get('security', 'max_login_attempts', 5);
        $lockoutSeconds = (int) $this->settings->get('security', 'lockout_seconds', 60);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            throw ValidationException::withMessages([
                'login' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        try {
            $user = $this->loginService->verify($validated['login'], $validated['password']);
        } catch (ValidationException $exception) {
            RateLimiter::hit($throttleKey, $lockoutSeconds);

            throw $exception;
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, $validated['remember'] ?? false);
        $request->session()->regenerate();
        $this->loginService->recordLogin($user, 'web');

        return redirect()->intended(route($user->homeRouteName()));
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->auditLogger->log('auth.logout', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
