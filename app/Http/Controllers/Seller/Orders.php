<?php

namespace App\Http\Controllers\Seller;

use App\Enums\SellerOrderStatus;
use App\Models\SellerOrder;
use App\Services\Ordering\SellerOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Orders extends SellerController
{
    public function __construct(private SellerOrderService $orders) {}

    public function index(Request $request): Response
    {
        $shop = $this->shop($request);
        $tabs = SellerOrderStatus::tabs();
        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? $request->query('tab') : null;
        $search = trim((string) $request->query('search'));
        $counts = ['all' => $shop->sellerOrders()->count()];

        foreach ($tabs as $key => $definition) {
            $counts[$key] = $shop->sellerOrders()->whereIn('status', $definition['statuses'])->count();
        }

        return Inertia::render('Seller/Orders/Index', [
            'orders' => $shop->sellerOrders()
                ->with(['order:id,order_number,user_id,payment_method,placed_at', 'order.customer:id,name', 'items:id,seller_order_id,product_name,variation_label,quantity', 'shipment:id,seller_order_id,tracking_number,status'])
                ->when($tab, fn ($query) => $query->whereIn('status', $tabs[$tab]['statuses']))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('items', fn ($items) => $items->where('product_name', 'like', "%{$search}%"))
                    ->orWhereHas('order.customer', fn ($users) => $users->where('name', 'like', "%{$search}%"))))
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (SellerOrder $order) => [
                    'id' => $order->id,
                    'reference' => $order->reference,
                    'status' => $order->status->value,
                    'customer' => $order->order?->customer?->name,
                    'items' => $order->items->map(fn ($item) => "{$item->quantity}× {$item->product_name}".($item->variation_label !== 'Default' ? " ({$item->variation_label})" : '')),
                    'total' => $order->total,
                    'seller_earning' => $order->seller_earning,
                    'payment_method' => $order->order?->payment_method->value,
                    'shipment' => $order->shipment ? ['tracking_number' => $order->shipment->tracking_number, 'status' => $order->shipment->status->value] : null,
                    'placed_at' => $order->order?->placed_at?->toIso8601String(),
                ]),
            'tabs' => collect($tabs)->map(fn ($definition, $key) => ['key' => $key, 'label' => $definition['label']])->values(),
            'filters' => ['tab' => $tab, 'search' => $search],
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, SellerOrder $order): Response
    {
        $this->authorizeOrder($request, $order);
        $order->load(['order.customer:id,name,phone', 'order.address', 'items', 'statusHistories.user:id,name', 'shipment.logisticsPartner:id,name', 'refunds', 'voucher:id,code']);

        return Inertia::render('Seller/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status->value,
                'items_subtotal' => $order->items_subtotal,
                'shipping_fee' => $order->shipping_fee,
                'discount' => $order->discount,
                'voucher' => $order->voucher?->code,
                'total' => $order->total,
                'commission_rate' => $order->commission_rate,
                'commission_amount' => $order->commission_amount,
                'seller_earning' => $order->seller_earning,
                'cancelled_reason' => $order->cancelled_reason,
                'payment_method' => $order->order->payment_method->value,
                'notes' => $order->order->notes,
                'placed_at' => $order->order->placed_at?->toIso8601String(),
                'customer' => $order->order->customer?->only(['name', 'phone']),
                'address' => $order->order->address?->only(['recipient_name', 'phone', 'street', 'barangay', 'city', 'province']),
                'items' => $order->items->map->only(['id', 'product_id', 'product_name', 'variation_label', 'unit_price', 'quantity', 'line_total']),
                'history' => $order->statusHistories->sortBy('id')->values()->map(fn ($history) => [
                    'id' => $history->id, 'to' => $history->to_status, 'note' => $history->note, 'user' => $history->user?->name, 'created_at' => $history->created_at?->toIso8601String(),
                ]),
                'shipment' => $order->shipment ? [
                    'tracking_number' => $order->shipment->tracking_number,
                    'status' => $order->shipment->status->value,
                    'partner' => $order->shipment->logisticsPartner?->name,
                ] : null,
                'refunds' => $order->refunds->map(fn ($refund) => $refund->only(['id', 'reason', 'details', 'amount']) + ['status' => $refund->status->value]),
                'can' => [
                    'accept' => $order->status === SellerOrderStatus::Pending,
                    'ready' => $order->status === SellerOrderStatus::Processing,
                    'cancel' => in_array($order->status, [SellerOrderStatus::Unpaid, SellerOrderStatus::Pending, SellerOrderStatus::Processing], true),
                ],
            ],
        ]);
    }

    public function accept(Request $request, SellerOrder $order): RedirectResponse
    {
        $this->orders->accept($order, $this->shop($request), $request->user());

        return back()->with('success', 'Order accepted. Pack it and mark it ready for pickup.');
    }

    public function ready(Request $request, SellerOrder $order): RedirectResponse
    {
        $this->orders->readyForPickup($order, $this->shop($request), $request->user());

        return back()->with('success', 'Marked ready. A rider will be assigned to pick it up.');
    }

    public function cancel(Request $request, SellerOrder $order): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->orders->cancel($order, $this->shop($request), $request->user(), $validated['reason']);

        return back()->with('success', 'Order cancelled and stock released.');
    }

    private function authorizeOrder(Request $request, SellerOrder $order): void
    {
        abort_unless($order->shop_id === $this->shop($request)->id, 404);
    }
}
