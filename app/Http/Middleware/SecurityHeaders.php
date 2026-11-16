<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections on every web and API response.
 *
 * A Content-Security-Policy is not set yet: the Vite dev server and Inertia's inline page data need
 * a nonce-based policy, which is a production deployment task (see DEVELOPMENT.md).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        // Riders need the camera (proof photos) and location (GPS) on this site only.
        $headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()', false);

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
