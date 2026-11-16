<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * API version of the web "confirm your password" step (seller finance).
 *
 * The app calls POST /api/v1/auth/confirm-password; that stamps the current token. Guarded endpoints
 * then work for `auth.password_timeout` seconds (3 hours by default). Otherwise they answer
 * 423 Locked with code "password_confirmation_required", so the app can ask for the password.
 */
class EnsureTokenPasswordConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        $raw = $token instanceof PersonalAccessToken ? $token->getAttribute('password_confirmed_at') : null;
        $confirmedAt = $raw ? Carbon::parse($raw) : null;
        $timeout = (int) config('auth.password_timeout', 10800);

        if (! $confirmedAt || $confirmedAt->isFuture() || now()->diffInSeconds($confirmedAt, true) > $timeout) {
            return response()->json([
                'message' => 'Please confirm your password to continue.',
                'code' => 'password_confirmation_required',
            ], 423);
        }

        return $next($request);
    }
}
