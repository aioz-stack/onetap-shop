<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Services\Ordering\OrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Orders extends Controller
{
    public function __construct(private OrderStatusService $orderStatus) {}

    public function index(Request $request): Response
    {
        $tabs = SellerOrderStatus::tabs();
        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? $request->query('tab') : null;
        $search = trim((string) $request->query('search'));
        $from = $request->date('from');
        $to = $request->date('to');

        $counts = ['all' => SellerOrder::query()->count()];

        foreach ($tabs as $key => $definition) {
            $counts[$key] = SellerOrder::query()->whereIn('status', $definition['statuses'])->count();
        }

        $orders = SellerOrder::query()
            ->with(['order:id,order_number,user_id,payment_method,payment_status', 'order.customer:id,name', 'shop:id,name'])
            ->withCount('items')
            ->when($tab, fn ($query) => $query->whereIn('status', $tabs[$tab]['statuses']))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('order', fn ($orders) => $orders->where('order_number', 'like', "%{$search}%"))
                ->orWhereHas('order.customer', fn ($users) => $users->where('name', 'like', "%{$search}%"))
                ->orWhereHas('shop', fn ($shops) => $shops->where('name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SellerOrder $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'order_number' => $order->order?->order_number,
                'customer' => $order->order?->customer?->name,
                'shop' => $order->shop?->name,
                'items_count' => $order->items_count,
                'total' => $order->total,
                'status' => $order->status->value,
                'payment_method' => $order->order?->payment_method->value,
                'payment_status' => $order->order?->payment_status->value,
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'tabs' => collect($tabs)->map(fn ($definition, $key) => ['key' => $key, 'label' => $definition['label']])->values(),
            'filters' => ['tab' => $tab, 'search' => $search, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'counts' => $counts,
        ]);
    }

    public function show(SellerOrder $order): Response
    {
        $order->load([
            'order.customer:id,name,email,phone',
            'order.address',
            'order.payments',
            'shop:id,name,slug,pickup_phone',
            'items',
            'statusHistories.user:id,name',
            'shipment.logisticsPartner:id,name',
            'shipment.proof',
            'shipment.assignments.rider.user:id,name',
            'refunds',
        ]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status->value,
                'items_subtotal' => $order->items_subtotal,
                'shipping_fee' => $order->shipping_fee,
                'discount' => $order->discount,
                'total' => $order->total,
                'commission_rate' => $order->commission_rate,
                'commission_amount' => $order->commission_amount,
                'seller_earning' => $order->seller_earning,
                'cancelled_reason' => $order->cancelled_reason,
                'created_at' => $order->created_at?->toIso8601String(),
                'can_cancel' => $order->status->canTransitionTo(SellerOrderStatus::Cancelled),
                'order' => [
                    'order_number' => $order->order->order_number,
                    'payment_method' => $order->order->payment_method->value,
                    'payment_status' => $order->order->payment_status->value,
                    'grand_total' => $order->order->grand_total,
                    'notes' => $order->order->notes,
                    'placed_at' => $order->order->placed_at?->toIso8601String(),
                ],
                'customer' => $order->order->customer?->only(['id', 'name', 'email', 'phone']),
                'address' => $order->order->address?->only(['recipient_name', 'phone', 'street', 'barangay', 'city', 'province', 'region', 'postal_code']),
                'shop' => $order->shop?->only(['id', 'name', 'slug', 'pickup_phone']),
                'items' => $order->items->map->only(['id', 'product_id', 'product_name', 'variation_label', 'unit_price', 'quantity', 'line_total']),
                'history' => $order->statusHistories->sortBy('id')->values()->map(fn ($history) => [
                    'id' => $history->id,
                    'from' => $history->from_status,
                    'to' => $history->to_status,
                    'note' => $history->note,
                    'user' => $history->user?->name,
                    'created_at' => $history->created_at?->toIso8601String(),
                ]),
                'shipment' => $order->shipment ? [
                    'tracking_number' => $order->shipment->tracking_number,
                    'status' => $order->shipment->status->value,
                    'partner' => $order->shipment->logisticsPartner?->name,
                    'rider' => $order->shipment->assignments->sortByDesc('id')->first()?->rider?->user?->name,
                    'delivery_fee' => $order->shipment->delivery_fee,
                    'cod_amount' => $order->shipment->cod_amount,
                    'proof' => $order->shipment->proof ? [
                        'recipient_name' => $order->shipment->proof->recipient_name,
                        'recipient_relation' => $order->shipment->proof->recipient_relation,
                        'delivered_at' => $order->shipment->proof->delivered_at?->toIso8601String(),
                        'photo_url' => route('admin.shipments.proof-photo', $order->shipment),
                    ] : null,
                ] : null,
                'refunds' => $order->refunds->map(fn ($refund) => $refund->only(['id', 'reason', 'amount']) + ['status' => $refund->status->value]),
            ],
        ]);
    }

    public function cancel(Request $request, SellerOrder $order): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->orderStatus->transition($order, SellerOrderStatus::Cancelled, $request->user(), $validated['reason']);

        return back()->with('success', "Order {$order->reference} cancelled and its stock released.");
    }
}
