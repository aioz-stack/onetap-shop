<?php

namespace App\Services\Ordering;

use App\Enums\RefundStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use App\Services\Communication\Notifier;
use App\Services\Finance\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin decisions on return/refund requests.
 */
class RefundService
{
    public function __construct(
        private OrderStatusService $orderStatus,
        private LedgerService $ledger,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Approve a refund. With $restock the returned items go back on hand first.
     * If the seller was already credited for the order, their earning is clawed back.
     */
    public function approve(Refund $refund, User $actor, bool $restock = false, ?string $note = null): Refund
    {
        return DB::transaction(function () use ($refund, $actor, $restock, $note) {
            $refund = $this->lockRequested($refund);
            $order = $refund->sellerOrder;

            if ($restock) {
                $order = $this->orderStatus->transition($order, SellerOrderStatus::Returned, $actor, 'Items returned to stock');
            }

            $this->orderStatus->transition($order, SellerOrderStatus::Refunded, $actor, $note ?? 'Refund approved');

            $earningCredited = Transaction::query()
                ->where('type', TransactionType::SaleEarning)
                ->where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->id)
                ->exists();

            $balanceAfter = null;

            if ($earningCredited && bccomp($order->seller_earning, '0', 2) > 0) {
                $balanceAfter = $this->ledger->clawBack($order->shop, $order->seller_earning)->available_balance;
            }

            $this->ledger->record(TransactionType::Refund, TransactionDirection::Debit, $refund->amount, $refund, [
                'shop' => $order->shop,
                'user_id' => $refund->user_id,
                'balance_after' => $balanceAfter,
                'description' => "Refund for {$order->reference}",
            ]);

            $refund->forceFill(['status' => RefundStatus::Refunded, 'resolved_by' => $actor->id, 'resolved_at' => now()])->save();
            $this->auditLogger->log('refund.approved', $refund, newValues: ['restock' => $restock, 'note' => $note], actor: $actor);
            app(Notifier::class)->refundReviewed($refund);

            return $refund;
        });
    }

    public function reject(Refund $refund, User $actor, string $reason): Refund
    {
        return DB::transaction(function () use ($refund, $actor, $reason) {
            $refund = $this->lockRequested($refund);
            $order = $refund->sellerOrder;

            $this->orderStatus->transition($order, $this->orderStatus->statusBeforeReturn($order), $actor, "Return rejected: {$reason}");

            $refund->forceFill(['status' => RefundStatus::Rejected, 'resolved_by' => $actor->id, 'resolved_at' => now(), 'details' => trim(($refund->details ?? '')."\n\nRejected: {$reason}")])->save();
            $this->auditLogger->log('refund.rejected', $refund, newValues: ['reason' => $reason], actor: $actor);
            app(Notifier::class)->refundReviewed($refund, $reason);

            return $refund;
        });
    }

    private function lockRequested(Refund $refund): Refund
    {
        $locked = Refund::query()->lockForUpdate()->with('sellerOrder.shop')->findOrFail($refund->id);

        if (! in_array($locked->status, [RefundStatus::Requested, RefundStatus::Approved], true)) {
            throw ValidationException::withMessages(['status' => 'This refund has already been resolved.']);
        }

        return $locked;
    }
}
