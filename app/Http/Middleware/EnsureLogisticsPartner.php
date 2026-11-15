<?php

namespace App\Http\Middleware;

use App\Enums\PartnerStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logistics Panel requests need the operator account of an active logistics partner.
 * The partner is attached to the request so controllers never trust a partner id from input.
 */
class EnsureLogisticsPartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $partner = $request->user()?->logisticsPartner()->first();

        if (! $partner || $partner->status !== PartnerStatus::Active) {
            abort(403, 'Your logistics partner account is not active.');
        }

        $request->attributes->set('partner', $partner);

        return $next($request);
    }
}
