<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerOrderStatus;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductViolation;
use App\Models\SellerProfile;
use App\Services\Catalog\SellerApprovalService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Sellers extends Controller
{
    public function __construct(private SellerApprovalService $approvals) {}

    public function index(Request $request): Response
    {
        $status = SellerStatus::tryFrom((string) $request->query('status', 'pending')) ?? SellerStatus::Pending;
        $search = trim((string) $request->query('search'));

        $counts = [];

        foreach (SellerStatus::cases() as $case) {
            $counts[$case->value] = SellerProfile::query()->where('status', $case)->count();
        }

        $sellers = SellerProfile::query()
            ->with(['user:id,name,email', 'shop:id,seller_profile_id,name,slug,status'])
            ->addSelect(['open_violations_count' => ProductViolation::query()
                ->selectRaw('count(*)')
                ->join('products', 'products.id', '=', 'product_violations.product_id')
                ->join('shops', 'shops.id', '=', 'products.shop_id')
                ->whereColumn('shops.seller_profile_id', 'seller_profiles.id')
                ->where('product_violations.status', 'open')])
            ->where('status', $status)
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('business_name', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('shop', fn ($shops) => $shops->where('name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SellerProfile $profile) => [
                'id' => $profile->id,
                'business_name' => $profile->business_name,
                'business_type' => $profile->business_type,
                'status' => $profile->status->value,
                'owner' => $profile->user?->only(['id', 'name', 'email']),
                'shop' => $profile->shop?->only(['id', 'name', 'slug', 'status']),
                'violations_count' => (int) $profile->open_violations_count,
                'created_at' => $profile->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Sellers/Index', [
            'sellers' => $sellers,
            'status' => $status->value,
            'counts' => $counts,
            'search' => $search,
        ]);
    }

    public function show(SellerProfile $seller): Response
    {
        $seller->load(['user:id,name,email,phone,status', 'approver:id,name', 'shop.category:id,name']);
        $shop = $seller->shop;
        $orders = $shop?->sellerOrders();
        $total = $shop ? $shop->sellerOrders()->count() : 0;
        $cancelled = $shop ? $shop->sellerOrders()->where('status', SellerOrderStatus::Cancelled)->count() : 0;

        return Inertia::render('Admin/Sellers/Show', [
            'seller' => [
                'id' => $seller->id,
                'business_name' => $seller->business_name,
                'business_type' => $seller->business_type,
                'tin' => $seller->tin,
                'status' => $seller->status->value,
                'rejection_reason' => $seller->rejection_reason,
                'approved_at' => $seller->approved_at?->toIso8601String(),
                'approver' => $seller->approver?->name,
                'created_at' => $seller->created_at?->toIso8601String(),
                'owner' => $seller->user?->only(['id', 'name', 'email', 'phone']) + ['status' => $seller->user?->status->value],
                'shop' => $shop ? [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'slug' => $shop->slug,
                    'status' => $shop->status->value,
                    'description' => $shop->description,
                    'category' => $shop->category?->name,
                    'pickup' => collect([$shop->pickup_street, $shop->pickup_barangay, $shop->pickup_city, $shop->pickup_province])->filter()->implode(', '),
                    'vacation_mode' => $shop->vacation_mode,
                    'rating_avg' => $shop->rating_avg,
                ] : null,
            ],
            'performance' => [
                'products' => $shop?->products()->count() ?? 0,
                'liveProducts' => $shop?->products()->where('status', 'live')->count() ?? 0,
                'orders' => $total,
                'completedOrders' => $shop ? $shop->sellerOrders()->where('status', SellerOrderStatus::Completed)->count() : 0,
                'cancellationRate' => $total ? round($cancelled / $total * 100, 1) : 0,
                'gmv' => Money::format(($orders?->where('status', SellerOrderStatus::Completed)->sum('total') ?? 0)),
                'rating' => $shop?->rating_avg ?? '0.00',
            ],
            'violations' => $shop ? ProductViolation::query()
                ->whereIn('product_id', $shop->products()->withTrashed()->select('id'))
                ->with('product:id,name')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (ProductViolation $violation) => [
                    'id' => $violation->id,
                    'product' => $violation->product?->only(['id', 'name']),
                    'reason' => $violation->reason,
                    'status' => $violation->status,
                    'created_at' => $violation->created_at?->toIso8601String(),
                ]) : [],
        ]);
    }

    public function approve(Request $request, SellerProfile $seller): RedirectResponse
    {
        $this->approvals->approve($seller, $request->user());

        return back()->with('success', "{$seller->business_name} is approved and the shop is open.");
    }

    public function reject(Request $request, SellerProfile $seller): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->approvals->reject($seller, $request->user(), $validated['reason']);

        return back()->with('success', 'Application rejected.');
    }

    public function suspend(Request $request, SellerProfile $seller): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->approvals->suspend($seller, $request->user(), $validated['reason']);

        return back()->with('success', 'Seller suspended. Their shop and live products are hidden.');
    }

    public function reinstate(Request $request, SellerProfile $seller): RedirectResponse
    {
        $this->approvals->reinstate($seller, $request->user());

        return back()->with('success', 'Seller reinstated. Products stay unpublished until the seller republishes them.');
    }
}
