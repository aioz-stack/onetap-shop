<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        return redirect()->to($this->destinationFor($request, $user));
    }

    /**
     * The page the user was trying to reach, unless it belongs to a panel their role cannot open.
     */
    private function destinationFor(Request $request, User $user): string
    {
        $home = route($user->homeRouteName());
        $intended = $request->session()->pull('url.intended');

        if (! $intended) {
            return $home;
        }

        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
        $panels = ['/admin' => 'admin', '/seller' => 'seller', '/logistics' => 'logistics', '/rider' => 'rider', '/account' => 'customer', '/cart' => 'customer', '/checkout' => 'customer'];

        foreach ($panels as $prefix => $role) {
            if (($path === $prefix || str_starts_with($path, $prefix.'/')) && ! $user->hasRole($role)) {
                return $home;
            }
        }

        return $intended;
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
