<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartnerStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\LogisticsPartner;
use App\Models\ServiceArea;
use App\Services\Logistics\CoverageService;
use App\Services\Logistics\LogisticsPartnerService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class LogisticsPartners extends Controller
{
    public function __construct(private LogisticsPartnerService $partners, private CoverageService $coverage) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));

        return Inertia::render('Admin/Logistics/Partners/Index', [
            'partners' => LogisticsPartner::query()
                ->with('owner:id,name,email')
                ->withCount(['riders', 'serviceAreas', 'shipments', 'shipments as active_shipments_count' => fn ($query) => $query->whereNotIn('status', [ShipmentStatus::Delivered, ShipmentStatus::ReturnedToSeller])])
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (LogisticsPartner $partner) => $partner->only(['id', 'name', 'code', 'contact_phone', 'contact_email', 'base_delivery_fee', 'riders_count', 'service_areas_count', 'shipments_count', 'active_shipments_count']) + [
                    'status' => $partner->status->value,
                    'owner' => $partner->owner?->only(['id', 'name', 'email']),
                ]),
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Logistics/Partners/Create', ['statuses' => PartnerStatus::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->partnerRules() + [
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', Password::min(8)->letters()->numbers()],
        ]);

        $partner = $this->partners->create(
            collect($validated)->only(array_keys($this->partnerRules()))->all(),
            ['name' => $validated['owner_name'], 'username' => $validated['owner_username'], 'email' => $validated['owner_email'], 'password' => $validated['owner_password']],
            $request->user(),
        );

        return redirect()->route('admin.logistics.partners.show', $partner)->with('success', "{$partner->name} added with an operator account.");
    }

    public function show(LogisticsPartner $partner): Response
    {
        $partner->load(['owner:id,name,email,username', 'serviceAreas' => fn ($query) => $query->orderBy('city')->orderBy('barangay'), 'riders.user:id,name,phone']);

        $shipments = fn () => $partner->shipments();

        return Inertia::render('Admin/Logistics/Partners/Show', [
            'partner' => $partner->only(['id', 'name', 'code', 'contact_phone', 'contact_email', 'address', 'base_delivery_fee', 'rider_fee_per_delivery']) + [
                'status' => $partner->status->value,
                'owner' => $partner->owner?->only(['id', 'name', 'email', 'username']),
            ],
            'areas' => $partner->serviceAreas->map->only(['id', 'region', 'province', 'city', 'barangay', 'delivery_fee', 'is_active']),
            'riders' => $partner->riders->map(fn ($rider) => [
                'id' => $rider->id,
                'name' => $rider->user?->name,
                'phone' => $rider->user?->phone,
                'vehicle_type' => $rider->vehicle_type,
                'plate_number' => $rider->plate_number,
                'status' => $rider->status->value,
                'availability' => $rider->availability->value,
            ]),
            'stats' => [
                'pendingPickups' => $shipments()->where('status', ShipmentStatus::PendingPickup)->count(),
                'inTransit' => $shipments()->whereIn('status', [ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery])->count(),
                'delivered' => $shipments()->where('status', ShipmentStatus::Delivered)->count(),
                'failed' => $shipments()->whereIn('status', [ShipmentStatus::FailedDelivery, ShipmentStatus::CustomerUnavailable])->count(),
                'fees' => Money::format($shipments()->where('status', ShipmentStatus::Delivered)->sum('delivery_fee')),
            ],
            'statuses' => PartnerStatus::options(),
        ]);
    }

    public function update(Request $request, LogisticsPartner $partner): RedirectResponse
    {
        $validated = $request->validate($this->partnerRules($partner));
        $this->partners->update($partner, $validated, $request->user());

        return back()->with('success', 'Partner details saved.');
    }

    public function storeArea(Request $request, LogisticsPartner $partner): RedirectResponse
    {
        $this->coverage->save($partner, $this->coverage->validated($request, $partner), $request->user());

        return back()->with('success', 'Coverage area added.');
    }

    public function updateArea(Request $request, LogisticsPartner $partner, ServiceArea $area): RedirectResponse
    {
        abort_unless($area->logistics_partner_id === $partner->id, 404);
        $this->coverage->save($partner, $this->coverage->validated($request, $partner, $area), $request->user(), $area);

        return back()->with('success', 'Coverage area updated.');
    }

    public function destroyArea(Request $request, LogisticsPartner $partner, ServiceArea $area): RedirectResponse
    {
        $this->coverage->delete($partner, $area, $request->user());

        return back()->with('success', 'Coverage area removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerRules(?LogisticsPartner $partner = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('logistics_partners', 'code')->ignore($partner)],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'base_delivery_fee' => ['required', 'numeric', 'min:0', 'max:10000'],
            'rider_fee_per_delivery' => ['sometimes', 'required', 'numeric', 'min:0', 'max:10000'],
            'status' => ['required', Rule::enum(PartnerStatus::class)],
        ];
    }
}
