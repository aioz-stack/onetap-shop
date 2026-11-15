<?php

namespace App\Http\Controllers\Logistics;

use App\Enums\RemittanceStatus;
use App\Enums\ShipmentStatus;
use App\Models\RiderCollection;
use App\Models\RiderRemittance;
use App\Models\ServiceArea;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Services\Finance\RemittanceService;
use App\Services\Logistics\CoverageService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service coverage and logistics money: COD collections, rider remittances, delivery-fee earnings.
 */
class Operations extends LogisticsController
{
    public function __construct(private CoverageService $coverage, private RemittanceService $remittances) {}

    public function coverage(Request $request): Response
    {
        $partner = $this->partner($request);

        return Inertia::render('Logistics/Coverage', [
            'partner' => $partner->only(['id', 'name', 'base_delivery_fee']),
            'areas' => $partner->serviceAreas()->orderBy('city')->orderBy('barangay')->get()->map->only(['id', 'region', 'province', 'city', 'barangay', 'delivery_fee', 'is_active']),
        ]);
    }

    public function storeArea(Request $request): RedirectResponse
    {
        $partner = $this->partner($request);
        $this->coverage->save($partner, $this->coverage->validated($request, $partner), $request->user());

        return back()->with('success', 'Coverage area added. New orders there are routed to you.');
    }

    public function updateArea(Request $request, ServiceArea $area): RedirectResponse
    {
        $partner = $this->partner($request);
        abort_unless($area->logistics_partner_id === $partner->id, 404);
        $this->coverage->save($partner, $this->coverage->validated($request, $partner, $area), $request->user(), $area);

        return back()->with('success', 'Coverage area updated.');
    }

    public function destroyArea(Request $request, ServiceArea $area): RedirectResponse
    {
        $this->coverage->delete($this->partner($request), $area, $request->user());

        return back()->with('success', 'Coverage area removed.');
    }

    /**
     * Daily COD: what each rider collected per day, and how much is still with them.
     */
    public function cod(Request $request): Response
    {
        $partner = $this->partner($request);
        $date = $request->date('date') ?? today();
        $collections = RiderCollection::query()
            ->whereHas('rider', fn ($riders) => $riders->where('logistics_partner_id', $partner->id))
            ->whereDate('collected_at', $date)
            ->with(['rider.user:id,name', 'shipment:id,tracking_number', 'remittance:id,status'])
            ->orderBy('collected_at')
            ->get();

        $byRider = $collections->groupBy('rider_profile_id')->map(fn ($rows) => [
            'rider' => $rows->first()->rider?->user?->name,
            'deliveries' => $rows->count(),
            'collected' => Money::format($rows->sum(fn ($row) => (float) $row->amount_collected)),
            'remitted' => Money::format($rows->whereNotNull('rider_remittance_id')->sum(fn ($row) => (float) $row->amount_collected)),
            'outstanding' => Money::format($rows->whereNull('rider_remittance_id')->sum(fn ($row) => (float) $row->amount_collected)),
        ])->values();

        return Inertia::render('Logistics/Finance/Cod', [
            'date' => $date->toDateString(),
            'summary' => [
                'collected' => Money::format($collections->sum(fn ($row) => (float) $row->amount_collected)),
                'due' => Money::format($collections->sum(fn ($row) => (float) $row->amount_due)),
                'deliveries' => $collections->count(),
                'outstanding' => Money::format($collections->whereNull('rider_remittance_id')->sum(fn ($row) => (float) $row->amount_collected)),
            ],
            'byRider' => $byRider,
            'collections' => $collections->map(fn (RiderCollection $row) => [
                'id' => $row->id,
                'tracking_number' => $row->shipment?->tracking_number,
                'rider' => $row->rider?->user?->name,
                'amount_due' => $row->amount_due,
                'amount_collected' => $row->amount_collected,
                'status' => $row->status->value,
                'collected_at' => $row->collected_at?->toIso8601String(),
            ]),
        ]);
    }

    public function remittances(Request $request): Response
    {
        $partner = $this->partner($request);
        $status = RemittanceStatus::tryFrom((string) $request->query('status', 'submitted')) ?? RemittanceStatus::Submitted;
        $counts = [];

        foreach (RemittanceStatus::cases() as $case) {
            $counts[$case->value] = RiderRemittance::query()->where('logistics_partner_id', $partner->id)->where('status', $case)->count();
        }

        return Inertia::render('Logistics/Finance/Remittances', [
            'remittances' => RiderRemittance::query()
                ->where('logistics_partner_id', $partner->id)
                ->where('status', $status)
                ->with(['rider.user:id,name', 'confirmer:id,name'])
                ->withCount('collections')
                ->latest('remittance_date')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (RiderRemittance $remittance) => [
                    'id' => $remittance->id,
                    'rider' => $remittance->rider?->user?->name,
                    'remittance_date' => $remittance->remittance_date?->toDateString(),
                    'collections_count' => $remittance->collections_count,
                    'expected_amount' => $remittance->expected_amount,
                    'remitted_amount' => $remittance->remitted_amount,
                    'discrepancy' => $remittance->discrepancy,
                    'status' => $remittance->status->value,
                    'notes' => $remittance->notes,
                    'confirmer' => $remittance->confirmer?->name,
                    'confirmed_at' => $remittance->confirmed_at?->toIso8601String(),
                ]),
            'status' => $status->value,
            'counts' => $counts,
        ]);
    }

    public function confirmRemittance(Request $request, RiderRemittance $remittance): RedirectResponse
    {
        abort_unless($remittance->logistics_partner_id === $this->partner($request)->id, 404);
        $validated = $request->validate(['received_amount' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:500']]);
        $result = $this->remittances->confirm($remittance->load('rider.user'), $request->user(), (string) $validated['received_amount'], $validated['notes'] ?? null);

        return back()->with($result->status === RemittanceStatus::Confirmed ? 'success' : 'error', $result->status === RemittanceStatus::Confirmed
            ? 'Remittance confirmed.'
            : 'Saved as disputed: the cash counted differs by ₱'.number_format(abs((float) $result->discrepancy), 2).'.');
    }

    /**
     * Delivery-fee earnings: what the partner earns per delivered parcel, by month.
     */
    public function earnings(Request $request): Response
    {
        return Inertia::render('Logistics/Finance/Earnings', $this->earningsData($request));
    }

    public function exportEarnings(Request $request): StreamedResponse
    {
        $data = $this->earningsData($request);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Month', 'Delivered parcels', 'Delivery fees', 'COD collected', 'Failed attempts', 'Returned']);

            foreach ($data['monthly'] as $row) {
                fputcsv($out, [$row['month'], $row['delivered'], $row['fees'], $row['cod'], $row['failed'], $row['returned']]);
            }

            fclose($out);
        }, 'onetap-logistics-earnings-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function earningsData(Request $request): array
    {
        $partner = $this->partner($request);
        $shipments = fn () => Shipment::query()->where('logistics_partner_id', $partner->id);
        $monthly = [];

        for ($offset = 0; $offset < 12; $offset++) {
            $start = Carbon::today()->startOfMonth()->subMonthsNoOverflow($offset);
            $end = $start->copy()->endOfMonth();
            $delivered = $shipments()->where('status', ShipmentStatus::Delivered)->whereBetween('delivered_at', [$start, $end]);

            $monthly[] = [
                'month' => $start->format('Y-m'),
                'label' => $start->format('F Y'),
                'delivered' => (clone $delivered)->count(),
                'fees' => Money::format((clone $delivered)->sum('delivery_fee')),
                'cod' => Money::format(RiderCollection::query()->whereIn('shipment_id', $shipments()->select('id'))->whereBetween('collected_at', [$start, $end])->sum('amount_collected')),
                'failed' => ShipmentStatusHistory::query()->whereIn('shipment_id', $shipments()->select('id'))->whereIn('to_status', [ShipmentStatus::FailedDelivery->value, ShipmentStatus::CustomerUnavailable->value])->whereBetween('created_at', [$start, $end])->count(),
                'returned' => $shipments()->where('status', ShipmentStatus::ReturnedToSeller)->whereBetween('updated_at', [$start, $end])->count(),
            ];
        }

        return [
            'summary' => [
                'allTimeFees' => Money::format($shipments()->where('status', ShipmentStatus::Delivered)->sum('delivery_fee')),
                'thisMonthFees' => $monthly[0]['fees'],
                'pendingFees' => Money::format($shipments()->whereNotIn('status', [ShipmentStatus::Delivered, ShipmentStatus::ReturnedToSeller])->sum('delivery_fee')),
                'delivered' => $shipments()->where('status', ShipmentStatus::Delivered)->count(),
            ],
            'monthly' => $monthly,
        ];
    }
}
