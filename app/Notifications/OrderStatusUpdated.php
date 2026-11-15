<?php

namespace App\Notifications;

use App\Enums\SellerOrderStatus;
use App\Models\SellerOrder;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
{
    public function __construct(public SellerOrder $sellerOrder) {}

    /**
     * Customer-facing wording per status. Statuses without a message are not announced.
     */
    public static function messageFor(SellerOrderStatus $status): ?string
    {
        return match ($status) {
            SellerOrderStatus::Processing => 'is being prepared by the shop',
            SellerOrderStatus::ReadyForPickup => 'is packed and waiting for the rider',
            SellerOrderStatus::Shipping => 'is on its way',
            SellerOrderStatus::Delivered => 'was delivered. Tap “Order received” to confirm',
            SellerOrderStatus::Completed => 'is complete. Thanks for shopping local!',
            SellerOrderStatus::Cancelled => 'was cancelled',

            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order '.$this->sellerOrder->status->label(),
            'body' => "Your order {$this->sellerOrder->reference} ".self::messageFor($this->sellerOrder->status).'.',
            'url' => "/account/orders/{$this->sellerOrder->id}",
            'seller_order_id' => $this->sellerOrder->id,
        ];
    }
}
