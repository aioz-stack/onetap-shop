<?php

namespace App\Services\Ordering;

use App\Enums\RefundStatus;
use App\Enums\SellerOrderStatus;
use App\Models\Refund;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\Finance\EarningsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Actions customers take on their own orders.
 */
class CustomerOrderService
{
    /**
     * Statuses a customer may still cancel from (before the seller starts preparing).
     */
    public const CANCELLABLE = [SellerOrderStatus::Unpaid, SellerOrderStatus::Pending];

    public function __construct(private OrderStatusService $orderStatus, private EarningsService $earnings) {}

    public function cancel(SellerOrder $order, User $customer, string $reason): SellerOrder
    {
        $this->assertOwner($order, $customer);

        if (! in_array($order->status, self::CANCELLABLE, true)) {
            throw ValidationException::withMessages(['status' => 'This order is already being prepared. Contact the shop or request a return after delivery.']);
        }

        return $this->orderStatus->transition($order, SellerOrderStatus::Cancelled, $customer, "Cancelled by customer: {$reason}");
    }

    /**
     * The customer confirms the parcel arrived: the order completes and the seller is credited.
     */
    public function confirmReceived(SellerOrder $order, User $customer): SellerOrder
    {
        $this->assertOwner($order, $customer);

        if ($order->status !== SellerOrderStatus::Delivered) {
            throw ValidationException::withMessages(['status' => 'Only delivered orders can be confirmed.']);
        }

        return DB::transaction(function () use ($order, $customer) {
            $completed = $this->orderStatus->transition($order, SellerOrderStatus::Completed, $customer, 'Received by customer');
            $this->earnings->postCompletion($completed);

            return $completed;
        });
    }

    /**
     * @param  array{reason: string, details?: string|null, amount?: string|null}  $data
     */
    public function requestRefund(SellerOrder $order, User $customer, array $data): Refund
    {
        $this->assertOwner($order, $customer);

        if (! in_array($order->status, [SellerOrderStatus::Delivered, SellerOrderStatus::Completed], true)) {
            throw ValidationException::withMessages(['status' => 'Returns can be requested after the order is delivered.']);
        }

        if ($order->refunds()->whereIn('status', [RefundStatus::Requested, RefundStatus::Approved])->exists()) {
            throw ValidationException::withMessages(['status' => 'A return request for this order is already open.']);
        }

        $amount = $data['amount'] ?? $order->items_subtotal;

        if (bccomp((string) $amount, $order->items_subtotal, 2) > 0) {
            throw ValidationException::withMessages(['amount' => 'The refund cannot exceed the items total of ₱'.$order->items_subtotal.'.']);
        }

        return DB::transaction(function () use ($order, $customer, $data, $amount) {
            $this->orderStatus->transition($order, SellerOrderStatus::ReturnRequested, $customer, $data['reason']);

            return $order->refunds()->create([
                'user_id' => $customer->id,
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'amount' => number_format((float) $amount, 2, '.', ''),
                'status' => RefundStatus::Requested,
            ]);
        });
    }

    private function assertOwner(SellerOrder $order, User $customer): void
    {
        abort_unless($order->order()->value('user_id') === $customer->id, 404);
    }
}
