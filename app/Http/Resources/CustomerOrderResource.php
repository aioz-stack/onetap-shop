<?php

namespace App\Http\Resources;

use App\Enums\RefundStatus;
use App\Enums\SellerOrderStatus;
use App\Models\SellerOrder;
use App\Services\Ordering\CustomerOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One seller order as the customer sees it. Detail fields appear when their relations are loaded.
 *
 * @mixin SellerOrder
 */
class CustomerOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $openRefund = $this->relationLoaded('refunds')
            ? $this->refunds->first(fn ($refund) => in_array($refund->status, [RefundStatus::Requested, RefundStatus::Approved], true))
            : null;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'order_number' => $this->order?->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'items_subtotal' => $this->items_subtotal,
            'shipping_fee' => $this->shipping_fee,
            'discount' => $this->discount,
            'total' => $this->total,
            'payment_method' => $this->order?->payment_method->value,
            'payment_status' => $this->order?->payment_status->value,
            'placed_at' => $this->order?->placed_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_reason' => $this->cancelled_reason,
            'shop' => $this->whenLoaded('shop', fn () => ['id' => $this->shop->id, 'name' => $this->shop->name, 'slug' => $this->shop->slug]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_slug' => $item->relationLoaded('product') ? $item->product?->slug : null,
                'image_url' => $item->relationLoaded('product') && $item->product?->primaryImage ? asset('storage/'.$item->product->primaryImage->path) : null,
                'product_name' => $item->product_name,
                'variation_label' => $item->variation_label,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
                'reviewed' => $item->relationLoaded('review') ? (bool) $item->review : null,
            ])),
            'address' => $this->when($this->order?->relationLoaded('address'), fn () => $this->order->address?->only(['recipient_name', 'phone', 'street', 'barangay', 'city', 'province', 'region', 'postal_code'])),
            'timeline' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->sortBy('id')->values()->map(fn ($history) => [
                'status' => $history->to_status,
                'label' => SellerOrderStatus::tryFrom($history->to_status)?->label() ?? $history->to_status,
                'note' => $history->note,
                'at' => $history->created_at?->toIso8601String(),
            ])),
            'shipment' => $this->whenLoaded('shipment', fn () => $this->shipment ? [
                'tracking_number' => $this->shipment->tracking_number,
                'status' => $this->shipment->status->value,
                'status_label' => $this->shipment->status->label(),
            ] : null),
            'refund' => $openRefund ? ['id' => $openRefund->id, 'status' => $openRefund->status->value, 'amount' => $openRefund->amount, 'reason' => $openRefund->reason] : null,
            'can' => [
                'cancel' => in_array($this->status, CustomerOrderService::CANCELLABLE, true),
                'confirm_received' => $this->status === SellerOrderStatus::Delivered,
                'request_refund' => in_array($this->status, [SellerOrderStatus::Delivered, SellerOrderStatus::Completed], true) && ! $openRefund,
                'review' => $this->status === SellerOrderStatus::Completed,
            ],
        ];
    }
}
