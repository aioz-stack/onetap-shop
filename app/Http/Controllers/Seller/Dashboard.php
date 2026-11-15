<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Dashboard extends Controller
{
    public function index(Request $request): Response
    {
        $shop = $request->user()->sellerProfile?->shop;

        return Inertia::render('Seller/Dashboard', [
            'shop' => $shop?->only(['id', 'name', 'slug', 'status']),
            'stats' => [
                'products' => $shop?->products()->count() ?? 0,
                'orders' => $shop?->sellerOrders()->count() ?? 0,
                'availableBalance' => (string) ($shop?->balance?->available_balance ?? '0.00'),
                'pendingBalance' => (string) ($shop?->balance?->pending_balance ?? '0.00'),
            ],
        ]);
    }
}
