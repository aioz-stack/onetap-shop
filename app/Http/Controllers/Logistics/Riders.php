<?php

namespace App\Http\Controllers\Logistics;

use App\Enums\AssignmentStatus;
use App\Enums\RiderStatus;
use App\Models\RiderProfile;
use App\Services\Logistics\RiderService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class Riders extends LogisticsController
{
    public const VEHICLES = ['motorcycle' => 'Motorcycle', 'bicycle' => 'Bicycle', 'e_bike' => 'E-bike', 'tricycle' => 'Tricycle', 'car' => 'Car', 'van' => 'Van'];

    public function __construct(private RiderService $riders) {}

    public function index(Request $request): Response
    {
        $partner = $this->partner($request);
        $status = RiderStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search'));
        $counts = ['all' => $partner->riders()->count()];

        foreach (RiderStatus::cases() as $case) {
            $counts[$case->value] = $partner->riders()->where('status', $case)->count();
        }

        return Inertia::render('Logistics/Riders/Index', [
            'riders' => $partner->riders()
                ->with('user:id,name,phone,email')
                ->withCount(['assignments as open_jobs' => fn ($query) => $query->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])])
                ->withCount(['assignments as completed_jobs' => fn ($query) => $query->where('status', AssignmentStatus::Completed)])
                ->withSum(['collections as unremitted' => fn ($query) => $query->whereNull('rider_remittance_id')], 'amount_collected')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('plate_number', 'like', "%{$search}%")->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (RiderProfile $rider) => [
                    'id' => $rider->id,
                    'name' => $rider->user?->name,
                    'phone' => $rider->user?->phone,
                    'vehicle' => trim((self::VEHICLES[$rider->vehicle_type] ?? $rider->vehicle_type ?? '').' '.($rider->plate_number ?? '')),
                    'status' => $rider->status->value,
                    'availability' => $rider->availability->value,
                    'open_jobs' => $rider->open_jobs,
                    'completed_jobs' => $rider->completed_jobs,
                    'unremitted' => Money::format($rider->unremitted),
                    'last_seen_at' => $rider->last_seen_at?->toIso8601String(),
                ]),
            'filters' => ['status' => $status?->value, 'search' => $search],
            'counts' => $counts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Logistics/Riders/Form', ['rider' => null, 'vehicles' => $this->vehicleOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(self::rules() + [
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

        return redirect()->route('logistics.riders.show', $rider)->with('success', "{$rider->user->name} added".($rider->status === RiderStatus::Active ? ' and can start taking deliveries.' : '. Approve them when their documents are checked.'));
    }

    public function show(Request $request, RiderProfile $rider): Response
    {
        $this->authorizeRider($request, $rider);
        $rider->load('user:id,name,username,email,phone,last_login_at');

        return Inertia::render('Logistics/Riders/Show', [
            'rider' => [
                'id' => $rider->id,
                'name' => $rider->user->name,
                'username' => $rider->user->username,
                'email' => $rider->user->email,
                'phone' => $rider->user->phone,
                'vehicle_type' => self::VEHICLES[$rider->vehicle_type] ?? $rider->vehicle_type,
                'plate_number' => $rider->plate_number,
                'license_number' => $rider->license_number,
                'status' => $rider->status->value,
                'availability' => $rider->availability->value,
                'last_seen_at' => $rider->last_seen_at?->toIso8601String(),
                'last_login_at' => $rider->user->last_login_at?->toIso8601String(),
                'joined_at' => $rider->created_at?->toIso8601String(),
            ],
            'performance' => $this->riders->performance($rider),
            'jobs' => $rider->assignments()
                ->with('shipment:id,tracking_number,status,cod_amount,dropoff_address,delivered_at')
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'shipment_id' => $assignment->shipment_id,
                    'tracking_number' => $assignment->shipment?->tracking_number,
                    'shipment_status' => $assignment->shipment?->status->value,
                    'status' => $assignment->status->value,
                    'cod_amount' => $assignment->shipment?->cod_amount,
                    'dropoff' => $assignment->shipment?->dropoff_address['barangay'] ?? null,
                    'created_at' => $assignment->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function edit(Request $request, RiderProfile $rider): Response
    {
        $this->authorizeRider($request, $rider);

        return Inertia::render('Logistics/Riders/Form', [
            'rider' => $rider->only(['id', 'vehicle_type', 'plate_number', 'license_number']) + $rider->user->only(['name', 'username', 'email', 'phone']),
            'vehicles' => $this->vehicleOptions(),
        ]);
    }

    public function update(Request $request, RiderProfile $rider): RedirectResponse
    {
        $this->authorizeRider($request, $rider);
        $user = $rider->user;
        $validated = $request->validate(self::rules() + [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['required', 'string', 'regex:/^(\+63|0)9\d{9}$/', Rule::unique('users', 'phone')->ignore($user)],
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
        ], ['phone.regex' => 'Enter a valid Philippine mobile number.']);

        $this->riders->update($rider, collect($validated)->only(['name', 'email', 'phone', 'password'])->all(), collect($validated)->only(['vehicle_type', 'plate_number', 'license_number'])->all(), $request->user());

        return redirect()->route('logistics.riders.show', $rider)->with('success', 'Rider details saved.');
    }

    public function approve(Request $request, RiderProfile $rider): RedirectResponse
    {
        return $this->setStatus($request, $rider, RiderStatus::Active, 'Rider approved and can now receive deliveries.');
    }

    public function suspend(Request $request, RiderProfile $rider): RedirectResponse
    {
        return $this->setStatus($request, $rider, RiderStatus::Suspended, 'Rider suspended. Their pickups went back to the queue.');
    }

    private function setStatus(Request $request, RiderProfile $rider, RiderStatus $status, string $message): RedirectResponse
    {
        $this->authorizeRider($request, $rider);
        $this->riders->setStatus($rider->load('user'), $status, $request->user());

        return back()->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vehicle_type' => ['required', Rule::in(array_keys(self::VEHICLES))],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'license_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function vehicleOptions(): array
    {
        return collect(self::VEHICLES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    private function authorizeRider(Request $request, RiderProfile $rider): void
    {
        abort_unless($rider->logistics_partner_id === $this->partner($request)->id, 404);
    }
}
