<?php

namespace App\Services\Ordering;

use App\Enums\PartnerStatus;
use App\Models\ServiceArea;
use App\Services\Administration\SettingsService;
use App\Support\Money;

/**
 * Delivery fee for one shop's parcel to a customer address.
 *
 * The cheapest active partner covering the barangay (or the whole city) wins:
 * its area fee override, else its base fee. With no coverage, the marketplace default applies.
 * Shops whose subtotal reaches the free-shipping threshold ship free.
 */
class ShippingFeeService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @return array{fee: string, logistics_partner_id: int|null, covered: bool, free: bool}
     */
    public function quote(?string $city, ?string $barangay, string $shopSubtotal): array
    {
        $threshold = (string) $this->settings->get('shipping', 'free_shipping_threshold', '0');
        $area = $city ? $this->bestArea($city, $barangay) : null;

        $fee = $area
            ? Money::format($area->delivery_fee ?? $area->logisticsPartner->base_delivery_fee)
            : Money::format($this->settings->get('shipping', 'default_delivery_fee', '59.00'));

        $free = bccomp($threshold, '0', 2) > 0 && bccomp($shopSubtotal, $threshold, 2) >= 0;

        return [
            'fee' => $free ? '0.00' : $fee,
            'logistics_partner_id' => $area?->logistics_partner_id,
            'covered' => (bool) $area,
            'free' => $free,
        ];
    }

    public function bestArea(string $city, ?string $barangay): ?ServiceArea
    {
        return ServiceArea::query()
            ->with('logisticsPartner')
            ->where('is_active', true)
            ->whereRaw('lower(city) = ?', [mb_strtolower(trim($city))])
            ->where(fn ($query) => $query
                ->whereNull('barangay')
                ->when($barangay, fn ($inner) => $inner->orWhereRaw('lower(barangay) = ?', [mb_strtolower(trim($barangay))])))
            ->whereHas('logisticsPartner', fn ($partners) => $partners->where('status', PartnerStatus::Active))
            ->get()
            ->sortBy(fn (ServiceArea $area) => [
                (float) ($area->delivery_fee ?? $area->logisticsPartner->base_delivery_fee),
                $area->barangay ? 0 : 1,
            ])
            ->first();
    }
}
