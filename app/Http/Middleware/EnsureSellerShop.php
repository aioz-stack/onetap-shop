<?php

namespace App\Http\Middleware;

use App\Enums\SellerStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seller Center requests need an approved seller profile with a shop.
 * The shop is attached to the request so controllers never trust a shop id from input.
 */
class EnsureSellerShop
{
    public function handle(Request $request, Closure $next): Response
    {
        $profile = $request->user()?->sellerProfile()->with('shop')->first();

        if (! $profile || $profile->status !== SellerStatus::Approved || ! $profile->shop) {
            abort(403, 'Your seller account is not active.');
        }

        $request->attributes->set('shop', $profile->shop);

        return $next($request);
    }
}
