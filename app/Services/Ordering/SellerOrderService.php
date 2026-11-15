<?php

namespace App\Services\Ordering;

use App\Enums\SellerOrderStatus;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\User;
use App\Services\Logistics\ShipmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fulfilment actions a shop takes on its own orders.
 */
class SellerOrderService
{
    public function __construct(private OrderStatusService $orderStatus, private ShipmentService $shipments) {}

    public function accept(SellerOrder $order, Shop $shop, User $seller): SellerOrder
    {
        $this->assertShop($order, $shop);

        return $this->orderStatus->transition($order, SellerOrderStatus::Processing, $seller, 'Accepted by shop');
    }

    /**
     * Packed and waiting for the rider: the shipment is created and routed in the same transaction.
     */
    public function readyForPickup(SellerOrder $order, Shop $shop, User $seller): SellerOrder
    {
        $this->assertShop($order, $shop);

        return DB::transaction(function () use ($order, $seller) {
            $updated = $this->orderStatus->transition($order, SellerOrderStatus::ReadyForPickup, $seller, 'Packed, waiting for pickup');
            $this->shipments->createFor($updated, $seller);

            return $updated;
        });
    }

    public function cancel(SellerOrder $order, Shop $shop, User $seller, string $reason): SellerOrder
    {
        $this->assertShop($order, $shop);

        if (! in_array($order->status, [SellerOrderStatus::Unpaid, SellerOrderStatus::Pending, SellerOrderStatus::Processing], true)) {
            throw ValidationException::withMessages(['status' => 'Orders that are packed or shipped cannot be cancelled by the shop.']);
        }

        return $this->orderStatus->transition($order, SellerOrderStatus::Cancelled, $seller, "Cancelled by shop: {$reason}");
    }

    private function assertShop(SellerOrder $order, Shop $shop): void
    {
        abort_unless($order->shop_id === $shop->id, 404);
    }
}
