<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RiderStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Logistics\Riders;
use App\Models\LogisticsPartner;
use App\Models\RiderCollection;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Services\Logistics\DispatchService;
use App\Services\Logistics\RiderService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Logistics partner endpoints for the mobile apps. The partner comes from EnsureLogisticsPartner;
 * shipments and riders of other partners return 404.
 */
class LogisticsController extends Controller
{
    public function __construct(private DispatchService $dispatch, private RiderService $riders) {}

    public function dashboard(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        $shipments = fn () => Shipment::query()->where('logistics_partner_id', $partner->id);
        $counts = [];

        foreach (ShipmentStatus::tabs() as $key => $definition) {
            $counts[$key] = $shipments()->whereIn('status', $definition['statuses'])->count();
        }

        $collections = fn () => RiderCollection::query()->whereHas('rider', fn ($riders) => $riders->where('logistics_partner_id', $partner->id));

        return response()->json(['data' => [
            'partner' => $partner->only(['id', 'name', 'code', 'base_delivery_fee', 'rider_fee_per_delivery']),
            'shipments' => $counts,
            'active_riders' => $partner->riders()->where('status', RiderStatus::Active)->where('availability', '!=', 'offline')->count(),
            'cod_collected_today' => Money::format($collections()->whereDate('collected_at', today())->sum('amount_collected')),
            'cod_unremitted' => Money::format($collections()->whereNull('rider_remittance_id')->sum('amount_collected')),
        ]]);
    }

    // ---------------------------------------------------------------- shipments

    public function shipments(Request $request): JsonResponse
    {
        $tabs = ShipmentStatus::tabs();
        $validated = $request->validate(['tab' => ['nullable', Rule::in(array_keys($tabs))], 'search' => ['nullable', 'string', 'max:50']]);
        $page = Shipment::query()
            ->where('logistics_partner_id', $this->partner($request)->id)
            ->with(['sellerOrder:id,reference', 'assignments' => fn ($query) => $query->latest('id'), 'assignments.rider.user:id,name'])
            ->whereIn('status', $tabs[$validated['tab'] ?? 'incoming']['statuses'])
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where('tracking_number', 'like', "%{$search}%"))
            ->oldest('updated_at')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Shipment $shipment) => $this->shipmentRow($shipment))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'tabs' => array_keys($tabs)],
        ]);
    }

    public function shipment(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorizeShipment($request, $shipment);
        $shipment->load(['sellerOrder:id,reference', 'assignments.rider.user:id,name,phone', 'statusHistories', 'proof', 'collection']);

        return response()->json(['data' => $this->shipmentRow($shipment) + [
            'pickup_address' => $shipment->pickup_address,
            'dropoff_address' => $shipment->dropoff_address,
            'history' => $shipment->statusHistories->sortByDesc('id')->values()->map(fn ($history) => ['to' => $history->to_status, 'note' => $history->note, 'created_at' => $history->created_at?->toIso8601String()]),
            'proof' => $shipment->proof ? $shipment->proof->only(['recipient_name', 'recipient_relation', 'lat', 'lng']) + ['delivered_at' => $shipment->proof->delivered_at?->toIso8601String()] : null,
            'collection' => $shipment->collection ? $shipment->collection->only(['amount_due', 'amount_collected']) + ['status' => $shipment->collection->status->value] : null,
        ]]);
    }

    public function assign(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorizeShipment($request, $shipment);
        $validated = $request->validate(['rider_id' => ['required', 'integer']]);
        $rider = RiderProfile::query()->with('user')->where('logistics_partner_id', $shipment->logistics_partner_id)->find($validated['rider_id']);

        if (! $rider) {
            return response()->json(['message' => 'Choose one of your riders.', 'errors' => ['rider_id' => ['Choose one of your riders.']]], 422);
        }

        $this->dispatch->assign($shipment, $rider, $request->user());

        return response()->json(['data' => $this->shipmentRow($shipment->fresh(['sellerOrder', 'assignments.rider.user']))]);
    }

    public function unassign(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorizeShipment($request, $shipment);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->dispatch->unassign($shipment, $request->user(), $validated['reason']);

        return response()->json(['data' => $this->shipmentRow($shipment->fresh(['sellerOrder', 'assignments.rider.user']))]);
    }

    // ---------------------------------------------------------------- riders

    public function riders(Request $request): JsonResponse
    {
        $validated = $request->validate(['status' => ['nullable', Rule::enum(RiderStatus::class)]]);
        $riders = $this->partner($request)->riders()
            ->with('user:id,name,username,phone')
            ->withCount(['assignments as open_jobs' => fn ($query) => $query->whereIn('status', ['assigned', 'accepted'])])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $riders->map(fn (RiderProfile $rider) => $this->riderRow($rider))]);
    }

    public function rider(Request $request, RiderProfile $rider): JsonResponse
    {
        $this->authorizeRider($request, $rider);
        $rider->load('user:id,name,username,phone')->loadCount(['assignments as open_jobs' => fn ($query) => $query->whereIn('status', ['assigned', 'accepted'])]);

        return response()->json(['data' => $this->riderRow($rider) + ['performance' => $this->riders->performance($rider)]]);
    }

    public function storeRider(Request $request): JsonResponse
    {
        $validated = $request->validate(Riders::rules() + [
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^(\+63|0)9\d{9}$/', 'unique:users,phone'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'activate' => ['boolean'],
        ], ['phone.regex' => 'Enter a valid Philippine mobile number.']);

        $rider = $this->riders->create(
            $this->partner($request),
            collect($validated)->only(['name', 'username', 'email', 'phone', 'password'])->all(),
            collect($validated)->only(['vehicle_type', 'plate_number', 'license_number'])->all(),
            $request->boolean('activate'),
            $request->user(),
        );

        return response()->json(['data' => $this->riderRow($rider->load('user'))], 201);
    }

    public function updateRider(Request $request, RiderProfile $rider): JsonResponse
    {
        $this->authorizeRider($request, $rider);
        $user = $rider->user;
        $validated = $request->validate(Riders::rules() + [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['required', 'string', 'regex:/^(\+63|0)9\d{9}$/', Rule::unique('users', 'phone')->ignore($user)],
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
        ], ['phone.regex' => 'Enter a valid Philippine mobile number.']);

        $this->riders->update($rider, collect($validated)->only(['name', 'email', 'phone', 'password'])->all(), collect($validated)->only(['vehicle_type', 'plate_number', 'license_number'])->all(), $request->user());

        return response()->json(['data' => $this->riderRow($rider->fresh('user'))]);
    }

    public function riderStatus(Request $request, RiderProfile $rider): JsonResponse
    {
        $this->authorizeRider($request, $rider);
        $validated = $request->validate(['action' => ['required', Rule::in(['approve', 'suspend'])]]);
        $this->riders->setStatus($rider->load('user'), $validated['action'] === 'approve' ? RiderStatus::Active : RiderStatus::Suspended, $request->user());

        return response()->json(['data' => $this->riderRow($rider->fresh('user'))]);
    }

    // ---------------------------------------------------------------- helpers

    private function partner(Request $request): LogisticsPartner
    {
        return $request->attributes->get('partner');
    }

    private function authorizeShipment(Request $request, Shipment $shipment): void
    {
        abort_unless($shipment->logistics_partner_id === $this->partner($request)->id, 404);
    }

    private function authorizeRider(Request $request, RiderProfile $rider): void
    {
        abort_unless($rider->logistics_partner_id === $this->partner($request)->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentRow(Shipment $shipment): array
    {
        $job = $shipment->assignments->sortByDesc('id')->first();

        return [
            'id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'status' => $shipment->status->value,
            'order' => $shipment->sellerOrder?->reference,
            'cod_amount' => $shipment->cod_amount,
            'delivery_fee' => $shipment->delivery_fee,
            'attempts' => $shipment->attempts,
            'rider' => $job && in_array($job->status->value, ['assigned', 'accepted', 'completed'], true) ? ['id' => $job->rider_profile_id, 'name' => $job->rider?->user?->name, 'job_status' => $job->status->value] : null,
            'updated_at' => $shipment->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function riderRow(RiderProfile $rider): array
    {
        return [
            'id' => $rider->id,
            'name' => $rider->user?->name,
            'username' => $rider->user?->username,
            'phone' => $rider->user?->phone,
            'vehicle_type' => $rider->vehicle_type,
            'plate_number' => $rider->plate_number,
            'status' => $rider->status->value,
            'availability' => $rider->availability->value,
            'open_jobs' => $rider->open_jobs ?? null,
            'last_seen_at' => $rider->last_seen_at?->toIso8601String(),
        ];
    }
}
