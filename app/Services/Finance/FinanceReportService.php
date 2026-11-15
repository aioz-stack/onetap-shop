<?php

namespace App\Services\Finance;

use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Illuminate\Support\Carbon;

/**
 * Marketplace money figures. Revenue is recognised when a seller order is completed.
 */
class FinanceReportService
{
    /**
     * @return array<string, string>
     */
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $completed = SellerOrder::query()
            ->where('status', SellerOrderStatus::Completed)
            ->when($from, fn ($query) => $query->where('completed_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('completed_at', '<=', $to));

        $refunds = Transaction::query()
            ->where('type', TransactionType::Refund)
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to));

        return [
            'gmv' => $this->sum((clone $completed), 'total'),
            'commission' => $this->sum((clone $completed), 'commission_amount'),
            'sellerEarnings' => $this->sum((clone $completed), 'seller_earning'),
            'logisticsFees' => $this->sum(Shipment::query()
                ->where('status', ShipmentStatus::Delivered)
                ->when($from, fn ($query) => $query->where('delivered_at', '>=', $from))
                ->when($to, fn ($query) => $query->where('delivered_at', '<=', $to)), 'delivery_fee'),
            'refunds' => $this->sum($refunds, 'amount'),
            'paidOut' => $this->sum(Withdrawal::query()
                ->where('status', WithdrawalStatus::Paid)
                ->when($from, fn ($query) => $query->where('processed_at', '>=', $from))
                ->when($to, fn ($query) => $query->where('processed_at', '<=', $to)), 'amount'),
            'pendingPayouts' => $this->sum(Withdrawal::query()->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Approved]), 'amount'),
        ];
    }

    /**
     * One row per month for the last N months, oldest first.
     *
     * @return list<array<string, string>>
     */
    public function monthly(int $months = 12): array
    {
        $rows = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $start = Carbon::today()->startOfMonth()->subMonthsNoOverflow($offset);
            $end = $start->copy()->endOfMonth();
            $rows[] = ['month' => $start->format('Y-m'), 'label' => $start->format('M Y')] + $this->summary($start, $end);
        }

        return $rows;
    }

    private function sum($query, string $column): string
    {
        return number_format((float) $query->sum($column), 2, '.', '');
    }
}
