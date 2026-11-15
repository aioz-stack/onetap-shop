<?php

namespace App\Http\Controllers\Logistics;

use App\Enums\RiderAvailability;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Dashboard extends Controller
{
    public function index(Request $request): Response
    {
        $partner = $request->user()->logisticsPartner;
        $shipments = fn () => $partner?->shipments();

        return Inertia::render('Logistics/Dashboard', [
            'partner' => $partner?->only(['id', 'name', 'code']),
            'stats' => [
                'shipments' => $shipments()?->count() ?? 0,
                'pendingPickups' => $shipments()?->where('status', ShipmentStatus::PendingPickup)->count() ?? 0,
                'inTransit' => $shipments()?->where('status', ShipmentStatus::InTransit)->count() ?? 0,
                'delivered' => $shipments()?->where('status', ShipmentStatus::Delivered)->count() ?? 0,
                'riders' => $partner?->riders()->count() ?? 0,
                'activeRiders' => $partner?->riders()->where('availability', RiderAvailability::Available)->count() ?? 0,
            ],
        ]);
    }
}
