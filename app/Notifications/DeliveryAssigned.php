<?php

namespace App\Notifications;

use App\Models\DeliveryAssignment;
use App\Notifications\Concerns\PushesToDevices;
use Illuminate\Notifications\Notification;

class DeliveryAssigned extends Notification
{
    use PushesToDevices;

    public function __construct(public DeliveryAssignment $assignment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->withPush(['database'], $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $shipment = $this->assignment->shipment;
        $pickup = $shipment->pickup_address['name'] ?? 'the shop';

        return [
            'title' => 'New delivery assigned',
            'body' => "Pick up {$shipment->tracking_number} at {$pickup}. Collect ₱".number_format((float) $shipment->cod_amount, 2).' on delivery.',
            'url' => "/rider/deliveries/{$shipment->id}",
            'assignment_id' => $this->assignment->id,
        ];
    }
}
