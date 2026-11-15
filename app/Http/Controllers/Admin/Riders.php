<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentStatus;
use App\Enums\RiderAvailability;
use App\Enums\RiderStatus;
use App\Http\Controllers\Controller;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Services\Logistics\RiderService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Riders extends Controller
{
    public function __construct(private RiderService $riders) {}

    public function index(Request $request): Response
    {
        $status = RiderStatus::tryFrom((string) $request->query('status'));
        $availability = RiderAvailability::tryFrom((string) $request->query('availability'));
        $partnerId = $request->integer('partner') ?: null;
        $search = trim((string) $request->query('search'));

        $counts = ['all' => RiderProfile::query()->count()];

        foreach (RiderStatus::cases() as $case) {
            $counts[$case->value] = RiderProfile::query()->where('status', $case)->count();
        }

        return Inertia::render('Admin/Logistics/Riders', [
            'riders' => RiderProfile::query()
                ->with(['user:id,name,phone,email', 'logisticsPartner:id,name'])
                ->withCount(['assignments as completed_count' => fn ($query) => $query->where('status', AssignmentStatus::Completed)])
                ->withCount(['assignments as active_count' => fn ($query) => $query->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])])
                ->withSum('collections as cod_collected', 'amount_collected')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($availability, fn ($query) => $query->where('availability', $availability))
                ->when($partnerId, fn ($query) => $query->where('logistics_partner_id', $partnerId))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('plate_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (RiderProfile $rider) => [
                    'id' => $rider->id,
                    'name' => $rider->user?->name,
                    'phone' => $rider->user?->phone,
                    'user_id' => $rider->user_id,
                    'partner' => $rider->logisticsPartner?->name,
                    'vehicle_type' => $rider->vehicle_type,
                    'plate_number' => $rider->plate_number,
                    'status' => $rider->status->value,
                    'availability' => $rider->availability->value,
                    'completed_count' => $rider->completed_count,
                    'active_count' => $rider->active_count,
                    'cod_collected' => Money::format(($rider->cod_collected ?? '0.00')),
                    'last_seen_at' => $rider->last_seen_at?->toIso8601String(),
                ]),
            'filters' => ['status' => $status?->value, 'availability' => $availability?->value, 'partner' => $partnerId, 'search' => $search],
            'counts' => $counts,
            'partners' => LogisticsPartner::query()->orderBy('name')->get(['id', 'name']),
            'availabilities' => RiderAvailability::options(),
        ]);
    }

    public function suspend(Request $request, RiderProfile $rider): RedirectResponse
    {
        $this->riders->setStatus($rider, RiderStatus::Suspended, $request->user());

        return back()->with('success', 'Rider suspended and set offline.');
    }

    public function activate(Request $request, RiderProfile $rider): RedirectResponse
    {
        $this->riders->setStatus($rider, RiderStatus::Active, $request->user());

        return back()->with('success', 'Rider activated.');
    }
}
