<?php

namespace App\Http\Controllers\Rider;

use App\Enums\CollectionStatus;
use App\Models\RiderCollection;
use App\Models\RiderProfile;
use App\Models\RiderRemittance;
use App\Services\Finance\RemittanceService;
use App\Services\Logistics\DeliveryService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The rider's cash: what was collected, what is still in hand, and remittances to the partner.
 */
class Cod extends RiderController
{
    public function __construct(private RemittanceService $remittances, private DeliveryService $deliveries) {}

    public function index(Request $request): Response
    {
        $rider = $this->rider($request);

        return Inertia::render('Rider/Cod', self::overview($rider, $this->deliveries) + [
            'remittances' => $rider->remittances()->latest('id')->paginate(10)->withQueryString()->through(fn (RiderRemittance $remittance) => self::remittanceRow($remittance)),
        ]);
    }

    public function remit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $remittance = $this->remittances->submit($this->rider($request), (string) $validated['amount'], null, $validated['notes'] ?? null, $request->user());
        $message = bccomp($remittance->discrepancy, '0', 2) === 0
            ? 'Remittance submitted. Your partner will confirm once they count the cash.'
            : 'Remittance submitted with a difference of ₱'.number_format((float) $remittance->discrepancy, 2).'. Your partner will review it.';

        return back()->with('success', $message);
    }

    /**
     * Cash summary shared with the rider API.
     *
     * @return array<string, mixed>
     */
    public static function overview(RiderProfile $rider, DeliveryService $deliveries): array
    {
        $collections = fn () => RiderCollection::query()->where('rider_profile_id', $rider->id);
        $unremitted = $collections()->whereNull('rider_remittance_id');

        return [
            'summary' => [
                'collectedToday' => Money::format($collections()->whereDate('collected_at', today())->sum('amount_collected')),
                'inHand' => Money::format($unremitted->clone()->sum('amount_collected')),
                'inHandCount' => $unremitted->clone()->count(),
                'pendingConfirmation' => Money::format($rider->remittances()->where('status', 'submitted')->sum('remitted_amount')),
                'discrepancies' => $collections()->where('status', CollectionStatus::Discrepancy)->count() + $rider->remittances()->where('discrepancy', '!=', 0)->count(),
            ],
            'collections' => $collections()
                ->with('shipment:id,tracking_number')
                ->where(fn ($query) => $query->whereNull('rider_remittance_id')->orWhereDate('collected_at', today()))
                ->latest('collected_at')
                ->limit(50)
                ->get()
                ->map(fn (RiderCollection $collection) => [
                    'id' => $collection->id,
                    'shipment_id' => $collection->shipment_id,
                    'tracking_number' => $collection->shipment?->tracking_number,
                    'amount_due' => $collection->amount_due,
                    'amount_collected' => $collection->amount_collected,
                    'status' => $collection->status->value,
                    'remitted' => $collection->rider_remittance_id !== null,
                    'collected_at' => $collection->collected_at?->toIso8601String(),
                ]),
            'earnings' => collect(range(0, 6))->map(function ($daysAgo) use ($rider, $deliveries) {
                $day = today()->subDays($daysAgo);

                return ['date' => $day->toDateString()] + $deliveries->earningsFor($rider, $day);
            })->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function remittanceRow(RiderRemittance $remittance): array
    {
        return [
            'id' => $remittance->id,
            'date' => $remittance->remittance_date?->toDateString(),
            'expected_amount' => $remittance->expected_amount,
            'remitted_amount' => $remittance->remitted_amount,
            'discrepancy' => $remittance->discrepancy,
            'status' => $remittance->status->value,
            'notes' => $remittance->notes,
            'confirmed_at' => $remittance->confirmed_at?->toIso8601String(),
            'created_at' => $remittance->created_at?->toIso8601String(),
        ];
    }
}
