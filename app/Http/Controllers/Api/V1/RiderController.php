<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Rider\Cod;
use App\Http\Controllers\Rider\Dashboard;
use App\Http\Controllers\Rider\Deliveries;
use App\Http\Requests\CompleteDeliveryRequest;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Services\Finance\RemittanceService;
use App\Services\Identity\AccountService;
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
        $tabs = array_keys(Deliveries::TABS);
        $validated = $request->validate([
            'scope' => ['nullable', Rule::in(['open', 'history'])],
            // Same tabs as the rider web app: new, pickup, delivery, issues, history.
            'tab' => ['nullable', Rule::in($tabs)],
            'search' => ['nullable', 'string', 'max:50'],
        ]);

        $query = isset($validated['tab'])
            ? Deliveries::scoped($rider, $validated['tab'])->when($validated['tab'] === 'history', fn ($inner) => $inner->latest('updated_at'), fn ($inner) => $inner->oldest('id'))
            : RiderJobs::query($rider)->when(($validated['scope'] ?? 'open') === 'open', fn ($inner) => $inner->whereIn('status', RiderJobs::OPEN)->oldest('id'), fn ($inner) => $inner->whereNotIn('status', RiderJobs::OPEN)->latest('updated_at'));

        $page = $query
            ->when($validated['search'] ?? null, fn ($inner, $search) => $inner->whereHas('shipment', fn ($shipments) => $shipments->where('tracking_number', 'like', "%{$search}%")))
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($job) => RiderJobs::row($job))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'counts' => collect($tabs)->mapWithKeys(fn ($tab) => [$tab => Deliveries::scoped($rider, $tab)->count()]),
            ],
        ]);
    }

    /**
     * The rider's own details, pay rate, performance and recent shifts.
     */
    public function profile(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $user = $request->user();

        return response()->json(['data' => [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_picture_url' => $user->profile_picture ? asset('storage/'.$user->profile_picture) : null,
            'partner' => $rider->logisticsPartner?->name,
            'vehicle_type' => $rider->vehicle_type,
            'plate_number' => $rider->plate_number,
            'license_number' => $rider->license_number,
            'status' => $rider->status->value,
            'availability' => $rider->availability->value,
            'rate_per_delivery' => $rider->logisticsPartner?->rider_fee_per_delivery,
            'performance' => [
                'delivered' => $rider->assignments()->where('status', 'completed')->whereHas('shipment', fn ($shipments) => $shipments->where('status', 'delivered'))->count(),
                'declined' => $rider->assignments()->where('status', 'declined')->count(),
            ],
        ]]);
    }

    /**
     * Update the contact number. Vehicle and status are managed by the logistics partner.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^(\+63|0)9\d{9}$/', Rule::unique('users', 'phone')->ignore($user)],
        ], ['phone.regex' => 'Use a mobile number like 09171234567.']);

        app(AccountService::class)->updateProfile($user, ['name' => $user->name, 'email' => $user->email] + $validated);

        return $this->profile($request);
    }

    public function shifts(Request $request): JsonResponse
    {
        $page = $this->rider($request)->shifts()->latest('started_at')->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($shift) => [
                'id' => $shift->id,
                'started_at' => $shift->started_at?->toIso8601String(),
                'ended_at' => $shift->ended_at?->toIso8601String(),
                'minutes' => (int) $shift->started_at?->diffInMinutes($shift->ended_at ?? now()),
            ])->values(),
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
