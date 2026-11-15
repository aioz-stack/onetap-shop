<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification
{
    public function __construct(public Order $order) {}

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
            'title' => 'Order placed',
            'body' => "We received order {$this->order->order_number} (₱".number_format((float) $this->order->grand_total, 2).'). Pay cash when it arrives.',
            'url' => '/account/orders',
            'order_id' => $this->order->id,
        ];
    }
}
