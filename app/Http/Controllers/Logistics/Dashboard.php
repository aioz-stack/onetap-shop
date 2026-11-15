<?php

namespace App\Http\Controllers\Logistics;

use App\Enums\CollectionStatus;
use App\Enums\RemittanceStatus;
use App\Enums\RiderAvailability;
use App\Enums\RiderStatus;
use App\Enums\ShipmentStatus;
use App\Models\RiderCollection;
use App\Models\Shipment;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Dashboard extends LogisticsController
{
    public function index(Request $request): Response
    {
        $partner = $this->partner($request);
        $shipments = fn () => Shipment::query()->where('logistics_partner_id', $partner->id);
        $count = fn (array $statuses) => $shipments()->whereIn('status', $statuses)->count();
        $collections = fn () => RiderCollection::query()->whereHas('rider', fn ($riders) => $riders->where('logistics_partner_id', $partner->id));

        return Inertia::render('Logistics/Dashboard', [
            'partner' => $partner->only(['id', 'name', 'code']),
            'stats' => [
                'shipments' => $shipments()->count(),
                'pendingPickups' => $count([ShipmentStatus::PendingPickup, ShipmentStatus::Assigned, ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup]),
                'unassigned' => $count([ShipmentStatus::PendingPickup]),
                'inTransit' => $count([ShipmentStatus::PickedUp, ShipmentStatus::InTransit]),
                'outForDelivery' => $count([ShipmentStatus::OutForDelivery, ShipmentStatus::Rescheduled]),
                'delivered' => $count([ShipmentStatus::Delivered]),
                'deliveredToday' => $shipments()->where('status', ShipmentStatus::Delivered)->whereDate('delivered_at', today())->count(),
                'failed' => $count([ShipmentStatus::FailedDelivery, ShipmentStatus::CustomerUnavailable]),
                'riders' => $partner->riders()->count(),
                'activeRiders' => $partner->riders()->where('status', RiderStatus::Active)->where('availability', '!=', RiderAvailability::Offline)->count(),
                'pendingRiders' => $partner->riders()->where('status', RiderStatus::Pending)->count(),
                'codToday' => Money::format($collections()->whereDate('collected_at', today())->sum('amount_collected')),
                'codUnremitted' => Money::format($collections()->where('status', CollectionStatus::Collected)->sum('amount_collected')),
                'remittancesToReview' => $partner->remittances()->where('status', RemittanceStatus::Submitted)->count(),
            ],
            'waiting' => $shipments()
                ->where('status', ShipmentStatus::PendingPickup)
                ->with('sellerOrder:id,reference')
                ->oldest('id')
                ->limit(6)
                ->get()
                ->map(fn (Shipment $shipment) => [
                    'id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'pickup' => $shipment->pickup_address['name'] ?? null,
                    'dropoff' => collect([$shipment->dropoff_address['barangay'] ?? null, $shipment->dropoff_address['city'] ?? null])->filter()->implode(', '),
                    'cod_amount' => $shipment->cod_amount,
                    'created_at' => $shipment->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
