<?php

namespace App\Enums;

enum SellerOrderStatus: string
{
    use HasLabel;

    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Processing = 'processing';
    case ReadyForPickup = 'ready_for_pickup';
    case Shipping = 'shipping';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case ReturnRequested = 'return_requested';
    case Returned = 'returned';
    case Refunded = 'refunded';

    /**
     * The order-management tabs shown in the admin and seller panels, mapped to statuses.
     *
     * @return array<string, array{label: string, statuses: list<self>}>
     */
    public static function tabs(): array
    {
        return [
            'unpaid' => ['label' => 'Unpaid', 'statuses' => [self::Unpaid]],
            'to_ship' => ['label' => 'To Ship', 'statuses' => [self::Pending, self::Processing, self::ReadyForPickup]],
            'shipping' => ['label' => 'Shipping', 'statuses' => [self::Shipping, self::Delivered]],
            'completed' => ['label' => 'Completed', 'statuses' => [self::Completed]],
            'cancelled' => ['label' => 'Cancelled', 'statuses' => [self::Cancelled]],
            'return_refund' => ['label' => 'Return / Refund', 'statuses' => [self::ReturnRequested, self::Returned, self::Refunded]],
        ];
    }

    /**
     * Allowed next statuses. Every change goes through OrderStatusService, which enforces this map.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Unpaid => [self::Pending, self::Cancelled],
            self::Pending => [self::Processing, self::Cancelled],
            self::Processing => [self::ReadyForPickup, self::Cancelled],
            self::ReadyForPickup => [self::Shipping, self::Cancelled],
            self::Shipping => [self::Delivered, self::Returned],
            self::Delivered => [self::Completed, self::ReturnRequested],
            self::Completed => [self::ReturnRequested],
            self::ReturnRequested => [self::Returned, self::Refunded, self::Delivered, self::Completed],
            self::Returned => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Whether stock reserved for this order is still held (not yet shipped or released).
     */
    public function holdsReservedStock(): bool
    {
        return in_array($this, [self::Unpaid, self::Pending, self::Processing, self::ReadyForPickup], true);
    }
}
