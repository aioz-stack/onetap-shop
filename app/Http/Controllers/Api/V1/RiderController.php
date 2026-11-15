<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Rider\Cod;
use App\Http\Controllers\Rider\Dashboard;
use App\Http\Requests\CompleteDeliveryRequest;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Services\Finance\RemittanceService;
use App\Services\Logistics\DeliveryService;
use App\Services\Logistics\RiderShiftService;
use App\Support\ProofPhoto;
use App\Support\RiderJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rider endpoints for the future mobile app. Same services as the rider web app;
 * riders only ever see and touch their own assignments.
 */
class RiderController extends Controller
{
    public function __construct(private DeliveryService $deliveries, private RiderShiftService $shifts, private RemittanceService $remittances) {}

    public function dashboard(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        return response()->json(['data' => [
            'availability' => $rider->availability->value,
            'on_shift' => $this->shifts->openShift($rider) !== null,
            'partner' => $rider->logisticsPartner?->name,
            'stats' => Dashboard::stats($rider, $this->deliveries),
        ]]);
    }

    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', Rule::in(['sign_in', 'sign_out', 'available', 'unavailable'])]]);
        $rider = $this->rider($request);

        match ($validated['action']) {
            'sign_in' => $this->shifts->signIn($rider),
            'sign_out' => $this->shifts->signOut($rider),
            'available' => $this->shifts->setAvailable($rider, true),
            'unavailable' => $this->shifts->setAvailable($rider, false),
        };

        return response()->json(['data' => ['availability' => $rider->refresh()->availability->value, 'on_shift' => $this->shifts->openShift($rider) !== null]]);
    }

    public function assignments(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $validated = $request->validate(['scope' => ['nullable', Rule::in(['open', 'history'])]]);
        $page = RiderJobs::query($rider)
            ->when(($validated['scope'] ?? 'open') === 'open', fn ($query) => $query->whereIn('status', RiderJobs::OPEN)->oldest('id'), fn ($query) => $query->whereNotIn('status', RiderJobs::OPEN)->latest('updated_at'))
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($job) => RiderJobs::row($job))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, Shipment $shipment): JsonResponse
    {
        $job = RiderJobs::find($this->rider($request), $shipment);

        return response()->json(['data' => RiderJobs::detail($job, route('api.v1.rider.deliveries.proof-photo', $shipment))]);
    }

    public function status(Request $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(DeliveryService::UPDATE_STATUSES)],
            'note' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $rider = $this->rider($request);
        $location = ['lat' => isset($validated['lat']) ? (float) $validated['lat'] : null, 'lng' => isset($validated['lng']) ? (float) $validated['lng'] : null];
        $updated = $this->deliveries->update($shipment, $rider, $validated['status'], $validated['note'] ?? null, $location);

        return response()->json(['data' => ['id' => $updated->id, 'tracking_number' => $updated->tracking_number, 'status' => $updated->status->value]]);
    }

    public function proof(CompleteDeliveryRequest $request, Shipment $shipment): JsonResponse
    {
        $rider = $this->rider($request);
        $this->deliveries->complete($shipment, $rider, $request->file('photo'), $request->validated());

        return response()->json(['data' => RiderJobs::detail(RiderJobs::find($rider, $shipment), route('api.v1.rider.deliveries.proof-photo', $shipment))], 201);
    }

    public function proofPhoto(Request $request, Shipment $shipment): StreamedResponse
    {
        RiderJobs::find($this->rider($request), $shipment);

        return ProofPhoto::response($shipment);
    }

    public function cod(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        return response()->json(['data' => Cod::overview($rider, $this->deliveries) + [
            'remittances' => $rider->remittances()->latest('id')->limit(20)->get()->map(fn ($remittance) => Cod::remittanceRow($remittance)),
        ]]);
    }

    public function remit(Request $request): JsonResponse
    {
        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:0', 'max:9999999'], 'notes' => ['nullable', 'string', 'max:255']]);
        $remittance = $this->remittances->submit($this->rider($request), (string) $validated['amount'], null, $validated['notes'] ?? null, $request->user());

        return response()->json(['data' => Cod::remittanceRow($remittance)], 201);
    }

    private function rider(Request $request): RiderProfile
    {
        $rider = $request->user()->riderProfile;

        abort_unless($rider, 403, 'Your rider profile has not been set up yet.');

        return $rider->loadMissing('logisticsPartner:id,name,rider_fee_per_delivery');
    }
}
