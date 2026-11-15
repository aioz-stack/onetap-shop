<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\LogisticsPartner;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only pickup and shipment monitoring across every logistics partner.
 */
class Shipments extends Controller
{
    /**
     * @return array<string, array{label: string, statuses: list<ShipmentStatus>}>
     */
    public static function tabs(): array
    {
        return [
            'pickup' => ['label' => 'Awaiting pickup', 'statuses' => [ShipmentStatus::PendingPickup, ShipmentStatus::Assigned, ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup]],
            'transit' => ['label' => 'In transit', 'statuses' => [ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery]],
            'delivered' => ['label' => 'Delivered', 'statuses' => [ShipmentStatus::Delivered]],
            'failed' => ['label' => 'Failed / Rescheduled', 'statuses' => [ShipmentStatus::FailedDelivery, ShipmentStatus::CustomerUnavailable, ShipmentStatus::Rescheduled]],
            'returned' => ['label' => 'Returned', 'statuses' => [ShipmentStatus::ReturnedToSeller]],
        ];
    }

    public function index(Request $request): Response
    {
        $tabs = self::tabs();
        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? $request->query('tab') : 'pickup';
        $partnerId = $request->integer('partner') ?: null;
        $search = trim((string) $request->query('search'));

        $counts = [];

        foreach ($tabs as $key => $definition) {
            $counts[$key] = Shipment::query()->whereIn('status', $definition['statuses'])->count();
        }

        return Inertia::render('Admin/Logistics/Shipments', [
            'shipments' => Shipment::query()
                ->with(['sellerOrder:id,reference,shop_id', 'sellerOrder.shop:id,name', 'logisticsPartner:id,name', 'assignments' => fn ($query) => $query->latest('id'), 'assignments.rider.user:id,name'])
                ->whereIn('status', $tabs[$tab]['statuses'])
                ->when($partnerId, fn ($query) => $query->where('logistics_partner_id', $partnerId))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('sellerOrder', fn ($orders) => $orders->where('reference', 'like', "%{$search}%"))))
                ->latest('updated_at')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Shipment $shipment) => [
                    'id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'status' => $shipment->status->value,
                    'order' => $shipment->sellerOrder?->only(['id', 'reference']),
                    'shop' => $shipment->sellerOrder?->shop?->name,
                    'partner' => $shipment->logisticsPartner?->name,
                    'rider' => $shipment->assignments->first()?->rider?->user?->name,
                    'dropoff' => collect([$shipment->dropoff_address['barangay'] ?? null, $shipment->dropoff_address['city'] ?? null])->filter()->implode(', '),
                    'cod_amount' => $shipment->cod_amount,
                    'delivery_fee' => $shipment->delivery_fee,
                    'attempts' => $shipment->attempts,
                    'updated_at' => $shipment->updated_at?->toIso8601String(),
                ]),
            'tabs' => collect($tabs)->map(fn ($definition, $key) => ['key' => $key, 'label' => $definition['label']])->values(),
            'filters' => ['tab' => $tab, 'partner' => $partnerId, 'search' => $search],
            'counts' => $counts,
            'partners' => LogisticsPartner::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
