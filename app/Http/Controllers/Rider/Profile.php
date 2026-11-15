<?php

namespace App\Http\Controllers\Rider;

use App\Models\RiderShift;
use App\Services\Identity\AccountService;
use App\Services\Logistics\DeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The rider's own details, shift log and password. Vehicle and status are managed by the partner.
 */
class Profile extends RiderController
{
    public function __construct(private AccountService $accounts, private DeliveryService $deliveries) {}

    public function show(Request $request): Response
    {
        $rider = $this->rider($request);
        $user = $request->user();

        return Inertia::render('Rider/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'partner' => $rider->logisticsPartner?->name,
                'vehicle_type' => $rider->vehicle_type,
                'plate_number' => $rider->plate_number,
                'license_number' => $rider->license_number,
                'status' => $rider->status->value,
                'availability' => $rider->availability->value,
                'rate' => $rider->logisticsPartner?->rider_fee_per_delivery,
            ],
            'shifts' => $rider->shifts()->latest('started_at')->limit(10)->get()->map(fn (RiderShift $shift) => [
                'id' => $shift->id,
                'started_at' => $shift->started_at?->toIso8601String(),
                'ended_at' => $shift->ended_at?->toIso8601String(),
                'minutes' => (int) $shift->started_at?->diffInMinutes($shift->ended_at ?? now()),
            ]),
            'performance' => [
                'delivered' => $rider->assignments()->where('status', 'completed')->whereHas('shipment', fn ($shipments) => $shipments->where('status', 'delivered'))->count(),
                'declined' => $rider->assignments()->where('status', 'declined')->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^(\+63|0)9\d{9}$/', Rule::unique('users', 'phone')->ignore($user)],
        ], ['phone.regex' => 'Use a mobile number like 09171234567.']);

        $this->accounts->updateProfile($user, ['name' => $user->name, 'email' => $user->email] + $validated);

        return back()->with('success', 'Contact number updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $this->accounts->changePassword($request->user(), $validated['current_password'], $validated['password'], $request->session()->getId());

        return back()->with('success', 'Password changed. Other devices were signed out.');
    }
}
