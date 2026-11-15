<?php

namespace App\Http\Controllers\Rider;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Dashboard extends Controller
{
    public function index(Request $request): Response
    {
        $rider = $request->user()->riderProfile;

        return Inertia::render('Rider/Dashboard', [
            'rider' => $rider ? [
                'availability' => $rider->availability->value,
                'status' => $rider->status->value,
                'partner' => $rider->logisticsPartner?->name,
            ] : null,
            'stats' => [
                'assigned' => $rider?->assignments()->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])->count() ?? 0,
                'completedToday' => $rider?->assignments()->where('status', AssignmentStatus::Completed)->whereDate('completed_at', today())->count() ?? 0,
                'codToday' => Money::format(($rider?->collections()->whereDate('collected_at', today())->sum('amount_collected') ?? 0)),
            ],
        ]);
    }
}
