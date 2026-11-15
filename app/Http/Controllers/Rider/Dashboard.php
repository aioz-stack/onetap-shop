<?php

namespace App\Http\Controllers\Rider;

use App\Enums\AssignmentStatus;
use App\Enums\ShipmentStatus;
use App\Models\RiderProfile;
use App\Services\Logistics\DeliveryService;
use App\Services\Logistics\RiderShiftService;
use App\Support\Money;
use App\Support\RiderJobs;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Dashboard extends RiderController
{
    public function __construct(private RiderShiftService $shifts, private DeliveryService $deliveries) {}

    public function index(Request $request): Response
    {
        $rider = $request->user()->riderProfile;

        if (! $rider) {
            return Inertia::render('Rider/Dashboard', ['rider' => null, 'stats' => null, 'jobs' => []]);
        }

        $rider = $this->rider($request);

        return Inertia::render('Rider/Dashboard', [
            'rider' => $this->summary($rider),
            'stats' => self::stats($rider, $this->deliveries),
            'jobs' => RiderJobs::query($rider)
                ->whereIn('status', RiderJobs::OPEN)
                ->oldest('id')
                ->limit(5)
                ->get()
                ->map(fn ($job) => RiderJobs::row($job)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(RiderProfile $rider): array
    {
        $shift = $this->shifts->openShift($rider);

        return [
            'availability' => $rider->availability->value,
            'status' => $rider->status->value,
            'partner' => $rider->logisticsPartner?->name,
            'vehicle' => trim($rider->vehicle_type.' '.$rider->plate_number),
            'on_shift' => $shift !== null,
            'shift_started_at' => $shift?->started_at?->toIso8601String(),
        ];
    }

    /**
     * Today's numbers, shared with the rider API.
     *
     * @return array<string, mixed>
     */
    public static function stats(RiderProfile $rider, DeliveryService $deliveries): array
    {
        $open = fn () => $rider->assignments()->whereIn('status', RiderJobs::OPEN);
        $earnings = $deliveries->earningsFor($rider);

        return [
            'assigned' => $open()->count(),
            'pendingPickups' => $open()->whereHas('shipment', fn ($shipments) => $shipments->whereIn('status', [ShipmentStatus::Assigned, ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup]))->count(),
            'todayDeliveries' => $open()->whereHas('shipment', fn ($shipments) => $shipments->whereIn('status', [ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery, ShipmentStatus::Rescheduled]))->count(),
            'completedToday' => $earnings['delivered'],
            'codToday' => Money::format($rider->collections()->whereDate('collected_at', today())->sum('amount_collected')),
            'codUnremitted' => Money::format($rider->collections()->whereNull('rider_remittance_id')->sum('amount_collected')),
            'earningsToday' => $earnings['earnings'],
            'ratePerDelivery' => $earnings['rate'],
            'failedToday' => $rider->assignments()->where('status', AssignmentStatus::Accepted)->whereHas('shipment', fn ($shipments) => $shipments->whereIn('status', [ShipmentStatus::FailedDelivery, ShipmentStatus::CustomerUnavailable]))->count(),
        ];
    }
}
