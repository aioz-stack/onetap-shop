<?php

namespace Database\Factories;

use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\LogisticsPartner;
use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tracking_number' => 'OTX'.Str::upper(Str::random(10)),
            'seller_order_id' => SellerOrder::factory()->status(SellerOrderStatus::ReadyForPickup),
            'logistics_partner_id' => LogisticsPartner::factory(),
            'status' => ShipmentStatus::PendingPickup,
            'pickup_address' => ['city' => 'Quezon City', 'barangay' => 'Bagong Pag-asa', 'street' => '45 Rizal Ave.'],
            'dropoff_address' => ['city' => 'Quezon City', 'barangay' => 'Bagong Pag-asa', 'street' => '12 Mabini St.'],
            'cod_amount' => '359.00',
            'delivery_fee' => '59.00',
        ];
    }

    public function status(ShipmentStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
