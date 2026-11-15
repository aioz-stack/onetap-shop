<?php

namespace App\Notifications;

use App\Models\SellerOrder;
use Illuminate\Notifications\Notification;

class NewSellerOrder extends Notification
{
    public function __construct(public SellerOrder $sellerOrder) {}

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
            'title' => 'New order',
            'body' => "Order {$this->sellerOrder->reference} for ₱".number_format((float) $this->sellerOrder->total, 2).' is waiting to be prepared.',
            'url' => "/seller/orders/{$this->sellerOrder->id}",
            'seller_order_id' => $this->sellerOrder->id,
        ];
    }
}
