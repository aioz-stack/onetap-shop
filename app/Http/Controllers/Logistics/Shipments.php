<?php

namespace App\Http\Controllers\Logistics;

use App\Enums\ShipmentStatus;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Services\Logistics\DispatchService;
use App\Services\Logistics\ShipmentStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Shipments extends LogisticsController
{
    public function __construct(private DispatchService $dispatch, private ShipmentStatusService $statuses) {}

    public function index(Request $request): Response
    {
        $partner = $this->partner($request);
        $tabs = ShipmentStatus::tabs();
        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? $request->query('tab') : 'incoming';
        $search = trim((string) $request->query('search'));
        $riderId = $request->integer('rider') ?: null;
        $mine = fn () => Shipment::query()->where('logistics_partner_id', $partner->id);
        $counts = [];

        foreach ($tabs as $key => $definition) {
            $counts[$key] = $mine()->whereIn('status', $definition['statuses'])->count();
        }

        return Inertia::render('Logistics/Shipments/Index', [
            'shipments' => $mine()
                ->with(['sellerOrder:id,reference', 'assignments' => fn ($query) => $query->latest('id'), 'assignments.rider.user:id,name'])
                ->whereIn('status', $tabs[$tab]['statuses'])
                ->when($riderId, fn ($query) => $query->whereHas('assignments', fn ($assignments) => $assignments->where('rider_profile_id', $riderId)->whereIn('status', ['assigned', 'accepted'])))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('sellerOrder', fn ($orders) => $orders->where('reference', 'like', "%{$search}%"))))
                ->oldest('updated_at')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Shipment $shipment) => $this->row($shipment)),
            'tabs' => collect($tabs)->map(fn ($definition, $key) => ['key' => $key, 'label' => $definition['label']])->values(),
            'filters' => ['tab' => $tab, 'search' => $search, 'rider' => $riderId],
            'counts' => $counts,
            'riders' => $this->riderOptions($request),
        ]);
    }

    public function show(Request $request, Shipment $shipment): Response
    {
        $this->authorizeShipment($request, $shipment);
        $shipment->load(['sellerOrder:id,reference,shop_id,total', 'sellerOrder.shop:id,name', 'items.orderItem:id,product_name,variation_label', 'statusHistories.user:id,name', 'assignments.rider.user:id,name,phone', 'proof', 'collection']);

        return Inertia::render('Logistics/Shipments/Show', [
            'shipment' => $this->row($shipment) + [
                'pickup_address' => $shipment->pickup_address,
                'dropoff_address' => $shipment->dropoff_address,
                'shop' => $shipment->sellerOrder?->shop?->name,
                'items' => $shipment->items->map(fn ($item) => ['name' => $item->orderItem?->product_name, 'variation' => $item->orderItem?->variation_label, 'quantity' => $item->quantity]),
                'history' => $shipment->statusHistories->sortByDesc('id')->values()->map(fn ($history) => [
                    'id' => $history->id,
                    'to' => $history->to_status,
                    'note' => $history->note,
                    'user' => $history->user?->name,
                    'has_location' => $history->lat !== null,
                    'created_at' => $history->created_at?->toIso8601String(),
                ]),
                'assignments' => $shipment->assignments->sortByDesc('id')->values()->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'rider' => $assignment->rider?->user?->name,
                    'phone' => $assignment->rider?->user?->phone,
                    'type' => $assignment->type->value,
                    'status' => $assignment->status->value,
                    'created_at' => $assignment->created_at?->toIso8601String(),
                ]),
                'proof' => $shipment->proof ? [
                    'recipient_name' => $shipment->proof->recipient_name,
                    'recipient_relation' => $shipment->proof->recipient_relation,
                    'delivered_at' => $shipment->proof->delivered_at?->toIso8601String(),
                    'has_location' => $shipment->proof->lat !== null,
                ] : null,
                'collection' => $shipment->collection ? $shipment->collection->only(['amount_due', 'amount_collected']) + ['status' => $shipment->collection->status->value] : null,
                'can' => [
                    'assign' => in_array($shipment->status, [ShipmentStatus::PendingPickup, ShipmentStatus::Assigned, ShipmentStatus::Accepted, ShipmentStatus::Rescheduled], true),
                    'unassign' => in_array($shipment->status, [ShipmentStatus::Assigned, ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup], true),
                    'reschedule' => in_array($shipment->status, [ShipmentStatus::FailedDelivery, ShipmentStatus::CustomerUnavailable], true),
                    'return' => $shipment->status->canTransitionTo(ShipmentStatus::ReturnedToSeller),
                ],
            ],
            'riders' => $this->riderOptions($request),
        ]);
    }

    public function assign(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->authorizeShipment($request, $shipment);
        $validated = $request->validate(['rider_id' => ['required', 'integer']]);
        $rider = RiderProfile::query()->with('user')->find($validated['rider_id']);

        if (! $rider) {
            return back()->withErrors(['rider_id' => 'Choose one of your riders.']);
        }

        $this->dispatch->assign($shipment, $rider, $request->user());

        return back()->with('success', "{$shipment->tracking_number} assigned to {$rider->user->name}.");
    }

    public function unassign(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->authorizeShipment($request, $shipment);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->dispatch->unassign($shipment, $request->user(), $validated['reason']);

        return back()->with('success', 'Rider removed. The shipment is back in the pickup queue.');
    }

    public function reschedule(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->authorizeShipment($request, $shipment);
        $validated = $request->validate(['note' => ['required', 'string', 'max:255'], 'scheduled_at' => ['nullable', 'date', 'after:now']]);
        $this->statuses->transition($shipment, ShipmentStatus::Rescheduled, $request->user(), $validated['note']);

        if (filled($validated['scheduled_at'] ?? null)) {
            $shipment->refresh()->forceFill(['scheduled_at' => $validated['scheduled_at']])->save();
        }

        return back()->with('success', 'Redelivery scheduled. Assign a rider to take it out again.');
    }

    public function returnToSeller(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->authorizeShipment($request, $shipment);
        $validated = $request->validate(['note' => ['required', 'string', 'max:255']]);
        $this->statuses->transition($shipment, ShipmentStatus::ReturnedToSeller, $request->user(), "Returned to seller: {$validated['note']}");

        return back()->with('success', 'Marked as returned to the seller. The items go back to their stock.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Shipment $shipment): array
    {
        $current = $shipment->assignments->first(fn ($assignment) => in_array($assignment->status->value, ['assigned', 'accepted'], true));

        return [
            'id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'status' => $shipment->status->value,
            'order' => $shipment->sellerOrder?->reference,
            'pickup' => collect([$shipment->pickup_address['name'] ?? null, $shipment->pickup_address['barangay'] ?? null])->filter()->implode(' · '),
            'dropoff' => collect([$shipment->dropoff_address['recipient_name'] ?? null, $shipment->dropoff_address['barangay'] ?? null, $shipment->dropoff_address['city'] ?? null])->filter()->implode(' · '),
            'cod_amount' => $shipment->cod_amount,
            'delivery_fee' => $shipment->delivery_fee,
            'attempts' => $shipment->attempts,
            'rider' => $current?->rider?->user?->name,
            'rider_id' => $current?->rider_profile_id,
            'scheduled_at' => $shipment->scheduled_at?->toIso8601String(),
            'updated_at' => $shipment->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function riderOptions(Request $request): array
    {
        return $this->dispatch->availableRiders($this->partner($request))
            ->map(fn (RiderProfile $rider) => ['value' => $rider->id, 'label' => "{$rider->user->name} · {$rider->availability->label()} · {$rider->open_jobs} open"])
            ->all();
    }

    private function authorizeShipment(Request $request, Shipment $shipment): void
    {
        abort_unless($shipment->logistics_partner_id === $this->partner($request)->id, 404);
    }
}
