<?php

namespace App\Http\Controllers\Customer\Account;

use App\Enums\RefundStatus;
use App\Enums\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerOrderResource;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\SellerOrder;
use App\Services\Catalog\ReviewService;
use App\Services\Ordering\CustomerOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * My Purchases, order tracking, cancellations, refunds and reviews.
 */
class Orders extends Controller
{
    public function __construct(private CustomerOrderService $orders, private ReviewService $reviews) {}

    public function index(Request $request): Response
    {
        $tabs = SellerOrderStatus::tabs();
        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? $request->query('tab') : null;
        $search = trim((string) $request->query('search'));
        $mine = fn (): Builder => SellerOrder::query()->whereHas('order', fn ($orders) => $orders->where('user_id', $request->user()->id));

        $counts = ['all' => $mine()->count()];

        foreach ($tabs as $key => $definition) {
            $counts[$key] = $mine()->whereIn('status', $definition['statuses'])->count();
        }

        $orders = $mine()
            ->with(['order:id,order_number,payment_method,payment_status,placed_at', 'shop:id,name,slug', 'items.product' => fn ($query) => $query->withTrashed()->with('primaryImage'), 'refunds'])
            ->when($tab, fn ($query) => $query->whereIn('status', $tabs[$tab]['statuses']))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('items', fn ($items) => $items->where('product_name', 'like', "%{$search}%"))
                ->orWhereHas('shop', fn ($shops) => $shops->where('name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Customer/Account/Orders', [
            'orders' => CustomerOrderResource::collection($orders)->response()->getData(true),
            'tabs' => collect($tabs)->map(fn ($definition, $key) => ['key' => $key, 'label' => $definition['label']])->values(),
            'filters' => ['tab' => $tab, 'search' => $search],
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, SellerOrder $order): Response
    {
        $this->authorizeOrder($request, $order);
        $order->load([
            'order.address',
            'shop:id,name,slug',
            'items.product' => fn ($query) => $query->withTrashed()->with('primaryImage'),
            'items.review',
            'statusHistories',
            'shipment.statusHistories',
            'refunds',
        ]);

        return Inertia::render('Customer/Account/OrderShow', [
            'order' => (new CustomerOrderResource($order))->resolve(),
        ]);
    }

    public function cancel(Request $request, SellerOrder $order): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->orders->cancel($order, $request->user(), $validated['reason']);

        return back()->with('success', 'Order cancelled.');
    }

    public function confirm(Request $request, SellerOrder $order): RedirectResponse
    {
        $this->orders->confirmReceived($order, $request->user());

        return back()->with('success', 'Thanks! The order is complete. You can now review your items.');
    }

    public function refund(Request $request, SellerOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        $this->orders->requestRefund($order, $request->user(), $validated);

        return back()->with('success', 'Return request sent. We will review it shortly.');
    }

    public function review(Request $request, OrderItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->reviews->create($item, $request->user(), $validated['rating'], $validated['comment'] ?? null);

        return back()->with('success', 'Thanks for your review!');
    }

    public function cancellations(Request $request): Response
    {
        $orders = SellerOrder::query()
            ->whereHas('order', fn ($orders) => $orders->where('user_id', $request->user()->id))
            ->where('status', SellerOrderStatus::Cancelled)
            ->with(['order:id,order_number,payment_method,payment_status,placed_at', 'shop:id,name,slug', 'items'])
            ->latest('cancelled_at')
            ->paginate(10);

        return Inertia::render('Customer/Account/Cancellations', [
            'orders' => CustomerOrderResource::collection($orders)->response()->getData(true),
        ]);
    }

    public function refunds(Request $request): Response
    {
        $refunds = Refund::query()
            ->where('user_id', $request->user()->id)
            ->with('sellerOrder:id,reference,shop_id', 'sellerOrder.shop:id,name')
            ->latest('id')
            ->paginate(10)
            ->through(fn (Refund $refund) => [
                'id' => $refund->id,
                'reason' => $refund->reason,
                'details' => $refund->details,
                'amount' => $refund->amount,
                'status' => $refund->status->value,
                'order' => ['id' => $refund->sellerOrder?->id, 'reference' => $refund->sellerOrder?->reference, 'shop' => $refund->sellerOrder?->shop?->name],
                'created_at' => $refund->created_at?->toIso8601String(),
                'resolved_at' => $refund->resolved_at?->toIso8601String(),
            ]);

        return Inertia::render('Customer/Account/Refunds', ['refunds' => $refunds, 'statuses' => RefundStatus::options()]);
    }

    public function reviews(Request $request): Response
    {
        $user = $request->user();

        $pending = OrderItem::query()
            ->whereHas('sellerOrder', fn ($orders) => $orders->where('status', SellerOrderStatus::Completed)->whereHas('order', fn ($inner) => $inner->where('user_id', $user->id)))
            ->doesntHave('review')
            ->with(['product' => fn ($query) => $query->withTrashed()->with('primaryImage'), 'sellerOrder:id,reference,completed_at'])
            ->latest('id')
            ->get()
            ->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'variation_label' => $item->variation_label,
                'image_url' => $item->product?->primaryImage ? asset('storage/'.$item->product->primaryImage->path) : null,
                'order_reference' => $item->sellerOrder?->reference,
            ]);

        $written = $user->productReviews()
            ->with(['product' => fn ($query) => $query->withTrashed()->select('id', 'name', 'slug')])
            ->latest('id')
            ->get()
            ->map(fn ($review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'seller_reply' => $review->seller_reply,
                'product' => $review->product?->only(['id', 'name', 'slug']),
                'created_at' => $review->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Customer/Account/Reviews', ['pending' => $pending, 'written' => $written]);
    }

    private function authorizeOrder(Request $request, SellerOrder $order): void
    {
        abort_unless($order->order()->value('user_id') === $request->user()->id, 404);
    }
}
