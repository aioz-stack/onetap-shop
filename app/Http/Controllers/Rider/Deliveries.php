<?php

namespace App\Http\Controllers\Rider;

use App\Enums\AssignmentStatus;
use App\Enums\ShipmentStatus;
use App\Http\Requests\CompleteDeliveryRequest;
use App\Models\DeliveryAssignment;
use App\Models\Shipment;
use App\Services\Logistics\DeliveryService;
use App\Support\ProofPhoto;
use App\Support\RiderJobs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Deliveries extends RiderController
{
    public const TABS = [
        'new' => 'New',
        'pickup' => 'To pick up',
        'delivery' => 'To deliver',
        'issues' => 'Issues',
        'history' => 'History',
    ];

    public function __construct(private DeliveryService $deliveries) {}

    public function index(Request $request): Response
    {
        $rider = $this->rider($request);
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'new';
        $search = trim((string) $request->query('search'));
        $counts = [];

        foreach (array_keys(self::TABS) as $key) {
            $counts[$key] = $this->scoped($rider, $key)->count();
        }

        return Inertia::render('Rider/Deliveries/Index', [
            'jobs' => $this->scoped($rider, $tab)
                ->when($search !== '', fn ($query) => $query->whereHas('shipment', fn ($shipments) => $shipments->where('tracking_number', 'like', "%{$search}%")))
                ->when($tab === 'history', fn ($query) => $query->latest('updated_at'), fn ($query) => $query->oldest('id'))
                ->paginate(15)
                ->withQueryString()
                ->through(fn ($job) => RiderJobs::row($job)),
            'tabs' => collect(self::TABS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'filters' => ['tab' => $tab, 'search' => $search],
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, Shipment $shipment): Response
    {
        $rider = $this->rider($request);
        $job = RiderJobs::find($rider, $shipment);

        return Inertia::render('Rider/Deliveries/Show', [
            'job' => RiderJobs::detail($job, route('rider.deliveries.proof-photo', $shipment)),
            'onShift' => $rider->shifts()->whereNull('ended_at')->exists(),
        ]);
    }

    public function status(Request $request, Shipment $shipment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(DeliveryService::UPDATE_STATUSES)],
            'note' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $updated = $this->deliveries->update($shipment, $this->rider($request), $validated['status'], $validated['note'] ?? null, $this->location($request));

        if ($validated['status'] === 'declined') {
            return to_route('rider.deliveries.index')->with('success', "{$shipment->tracking_number} declined. Your dispatcher will reassign it.");
        }

        return back()->with('success', "{$updated->tracking_number}: {$updated->status->label()}.");
    }

    public function deliver(CompleteDeliveryRequest $request, Shipment $shipment): RedirectResponse
    {
        $validated = $request->validated();
        $this->deliveries->complete($shipment, $this->rider($request), $request->file('photo'), $validated);

        return back()->with('success', "{$shipment->tracking_number} delivered. Proof and cash collection saved.");
    }

    public function proofPhoto(Request $request, Shipment $shipment): StreamedResponse
    {
        RiderJobs::find($this->rider($request), $shipment);

        return ProofPhoto::response($shipment);
    }

    /**
     * @return Builder<DeliveryAssignment>
     */
    private function scoped($rider, string $tab)
    {
        $query = RiderJobs::query($rider);
        $statuses = fn (array $statuses) => fn ($shipments) => $shipments->whereIn('status', $statuses);

        return match ($tab) {
            'new' => $query->where('status', AssignmentStatus::Assigned),
            'pickup' => $query->where('status', AssignmentStatus::Accepted)->whereHas('shipment', $statuses([ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup])),
            'delivery' => $query->whereIn('status', RiderJobs::OPEN)->whereHas('shipment', $statuses([ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery, ShipmentStatus::Rescheduled])),
            'issues' => $query->whereIn('status', RiderJobs::OPEN)->whereHas('shipment', $statuses([ShipmentStatus::FailedDelivery, ShipmentStatus::CustomerUnavailable])),
            default => $query->whereNotIn('status', RiderJobs::OPEN),
        };
    }
}
