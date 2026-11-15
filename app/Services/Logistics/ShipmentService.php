<?php

namespace App\Services\Logistics;

use App\Enums\PaymentMethod;
use App\Enums\ShipmentStatus;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Ordering\ShippingFeeService;
use Illuminate\Support\Str;

/**
 * Creates the parcel for a seller order once the shop has packed it.
 * The covering logistics partner is chosen from the customer's address; with no coverage
 * the shipment stays unassigned until an administrator routes it.
 */
class ShipmentService
{
    public function __construct(private ShippingFeeService $shipping) {}

    public function createFor(SellerOrder $order, ?User $actor = null): Shipment
    {
        $order->loadMissing(['shop', 'items', 'order.address', 'shipment']);

        if ($order->shipment) {
            return $order->shipment;
        }

        $address = $order->order->address;
        $area = $address ? $this->shipping->bestArea($address->city, $address->barangay) : null;
        $shop = $order->shop;

        $shipment = $order->shipment()->create([
            'tracking_number' => $this->trackingNumber(),
            'logistics_partner_id' => $area?->logistics_partner_id,
            'status' => ShipmentStatus::PendingPickup,
            'pickup_address' => [
                'name' => $shop->name,
                'phone' => $shop->pickup_phone,
                'street' => $shop->pickup_street,
                'barangay' => $shop->pickup_barangay,
                'city' => $shop->pickup_city,
                'province' => $shop->pickup_province,
            ],
            'dropoff_address' => $address?->only(['recipient_name', 'phone', 'street', 'barangay', 'city', 'province', 'postal_code', 'lat', 'lng']) ?? [],
            'cod_amount' => $order->order->payment_method === PaymentMethod::Cod ? $order->total : '0.00',
            'delivery_fee' => $order->shipping_fee,
        ]);

        foreach ($order->items as $item) {
            $shipment->items()->create(['order_item_id' => $item->id, 'quantity' => $item->quantity]);
        }

        $shipment->statusHistories()->create([
            'to_status' => ShipmentStatus::PendingPickup->value,
            'note' => $area ? "Routed to {$area->logisticsPartner->name}" : 'No partner covers this address yet; waiting for assignment',
            'user_id' => $actor?->id,
        ]);

        return $shipment;
    }

    private function trackingNumber(): string
    {
        do {
            $number = 'OTX'.now()->format('ymd').Str::upper(Str::random(7));
        } while (Shipment::query()->where('tracking_number', $number)->exists());

        return $number;
    }
}
