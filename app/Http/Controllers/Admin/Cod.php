<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CollectionStatus;
use App\Enums\RemittanceStatus;
use App\Http\Controllers\Controller;
use App\Models\LogisticsPartner;
use App\Models\RiderCollection;
use App\Models\RiderRemittance;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * COD monitoring: what riders collected, what they remitted, and where the numbers disagree.
 */
class Cod extends Controller
{
    public function index(Request $request): Response
    {
        $view = $request->query('view') === 'remittances' ? 'remittances' : 'collections';
        $partnerId = $request->integer('partner') ?: null;
        $status = (string) $request->query('status');

        $collections = RiderCollection::query();
        $unremitted = (clone $collections)->where('status', CollectionStatus::Collected);

        $summary = [
            'collectedToday' => Money::format(RiderCollection::query()->whereDate('collected_at', today())->sum('amount_collected')),
            'unremitted' => Money::format($unremitted->sum('amount_collected')),
            'unremittedCount' => RiderCollection::query()->where('status', CollectionStatus::Collected)->count(),
            'remittedToday' => Money::format(RiderRemittance::query()->whereDate('remittance_date', today())->where('status', RemittanceStatus::Confirmed)->sum('remitted_amount')),
            'discrepancies' => RiderRemittance::query()->where('discrepancy', '!=', 0)->count() + RiderCollection::query()->where('status', CollectionStatus::Discrepancy)->count(),
        ];

        $rows = $view === 'collections'
            ? RiderCollection::query()
                ->with(['shipment:id,tracking_number,logistics_partner_id', 'shipment.logisticsPartner:id,name', 'rider.user:id,name'])
                ->when(CollectionStatus::tryFrom($status), fn ($query, $case) => $query->where('status', $case))
                ->when($partnerId, fn ($query) => $query->whereHas('shipment', fn ($shipments) => $shipments->where('logistics_partner_id', $partnerId)))
                ->latest('collected_at')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (RiderCollection $collection) => [
                    'id' => $collection->id,
                    'tracking_number' => $collection->shipment?->tracking_number,
                    'partner' => $collection->shipment?->logisticsPartner?->name,
                    'rider' => $collection->rider?->user?->name,
                    'amount_due' => $collection->amount_due,
                    'amount_collected' => $collection->amount_collected,
                    'status' => $collection->status->value,
                    'mismatch' => bccomp($collection->amount_due, $collection->amount_collected, 2) !== 0,
                    'collected_at' => $collection->collected_at?->toIso8601String(),
                ])
            : RiderRemittance::query()
                ->with(['rider.user:id,name', 'logisticsPartner:id,name', 'confirmer:id,name'])
                ->withCount('collections')
                ->when(RemittanceStatus::tryFrom($status), fn ($query, $case) => $query->where('status', $case))
                ->when($partnerId, fn ($query) => $query->where('logistics_partner_id', $partnerId))
                ->latest('remittance_date')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (RiderRemittance $remittance) => [
                    'id' => $remittance->id,
                    'rider' => $remittance->rider?->user?->name,
                    'partner' => $remittance->logisticsPartner?->name,
                    'remittance_date' => $remittance->remittance_date?->toDateString(),
                    'expected_amount' => $remittance->expected_amount,
                    'remitted_amount' => $remittance->remitted_amount,
                    'discrepancy' => $remittance->discrepancy,
                    'collections_count' => $remittance->collections_count,
                    'status' => $remittance->status->value,
                    'confirmer' => $remittance->confirmer?->name,
                ]);

        return Inertia::render('Admin/Logistics/Cod', [
            'view' => $view,
            'rows' => $rows,
            'summary' => $summary,
            'filters' => ['partner' => $partnerId, 'status' => $status ?: null],
            'statuses' => $view === 'collections' ? CollectionStatus::options() : RemittanceStatus::options(),
            'partners' => LogisticsPartner::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
