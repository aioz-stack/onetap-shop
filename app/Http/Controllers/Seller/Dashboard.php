<?php

namespace App\Http\Controllers\Seller;

use App\Enums\SellerOrderStatus;
use App\Models\ProductVariation;
use App\Models\SellerOrder;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class Dashboard extends SellerController
{
    public function index(Request $request): Response
    {
        $shop = $this->shop($request)->load('balance');
        $orders = fn () => SellerOrder::query()->where('shop_id', $shop->id);
        $start = Carbon::today()->subDays(29);

        $daily = $orders()
            ->where('created_at', '>=', $start)
            ->where('status', '!=', SellerOrderStatus::Cancelled)
            ->get(['created_at', 'items_subtotal'])
            ->groupBy(fn ($order) => $order->created_at->toDateString());

        $chart = ['labels' => [], 'sales' => [], 'orders' => []];

        for ($day = $start->copy(); $day->lte(Carbon::today()); $day->addDay()) {
            $chart['labels'][] = $day->format('M j');
            $chart['sales'][] = round((float) ($daily[$day->toDateString()] ?? collect())->sum('items_subtotal'), 2);
            $chart['orders'][] = ($daily[$day->toDateString()] ?? collect())->count();
        }

        return Inertia::render('Seller/Dashboard', [
            'shop' => $shop->only(['id', 'name', 'slug', 'status', 'vacation_mode']),
            'stats' => [
                'totalSales' => Money::format($orders()->where('status', SellerOrderStatus::Completed)->sum('items_subtotal')),
                'orders' => $orders()->count(),
                'pendingOrders' => $orders()->whereIn('status', SellerOrderStatus::tabs()['to_ship']['statuses'])->count(),
                'productsSold' => (int) $shop->products()->withTrashed()->sum('sold_count'),
                'products' => $shop->products()->count(),
                'liveProducts' => $shop->products()->where('status', 'live')->count(),
                'balance' => Money::format(bcadd((string) ($shop->balance?->pending_balance ?? 0), (string) ($shop->balance?->available_balance ?? 0), 2)),
                'availableBalance' => Money::format($shop->balance?->available_balance),
                'pendingBalance' => Money::format($shop->balance?->pending_balance),
            ],
            'chart' => $chart,
            'toShip' => $orders()
                ->whereIn('status', [SellerOrderStatus::Pending, SellerOrderStatus::Processing])
                ->with('order:id,order_number,placed_at')
                ->withCount('items')
                ->oldest('id')
                ->limit(5)
                ->get()
                ->map(fn (SellerOrder $order) => ['id' => $order->id, 'reference' => $order->reference, 'status' => $order->status->value, 'total' => $order->total, 'items_count' => $order->items_count, 'placed_at' => $order->order?->placed_at?->toIso8601String()]),
            'lowStock' => ProductVariation::query()
                ->whereHas('product', fn ($products) => $products->where('shop_id', $shop->id))
                ->where('is_active', true)
                ->whereRaw('stock - reserved <= 5')
                ->with('product:id,name')
                ->orderByRaw('stock - reserved')
                ->limit(6)
                ->get()
                ->map(fn ($variation) => ['product_id' => $variation->product_id, 'name' => $variation->product?->name, 'label' => $variation->label, 'available' => max(0, $variation->stock - $variation->reserved)]),
        ]);
    }
}
