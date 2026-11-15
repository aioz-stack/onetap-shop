<?php

namespace App\Services\Ordering;

use App\Enums\AssignmentStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use App\Services\Administration\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place seller-order statuses change. Enforces the transition map,
 * stamps timestamps, writes `order_status_histories`, and keeps stock in sync.
 */
class OrderStatusService
{
    public function __construct(private InventoryService $inventory, private AuditLogger $auditLogger) {}

    /**
     * @throws ValidationException
     */
    public function transition(SellerOrder $sellerOrder, SellerOrderStatus $next, ?User $actor = null, ?string $note = null): SellerOrder
    {
        return DB::transaction(function () use ($sellerOrder, $next, $actor, $note) {
            /** @var SellerOrder $order */
            $order = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);
            $current = $order->status;

            if (! $current->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => "An order that is {$current->label()} cannot be moved to {$next->label()}.",
                ]);
            }

            $this->applyStockEffects($order, $current, $next, $actor);

            $order->status = $next;
            $timestamp = match ($next) {
                SellerOrderStatus::Shipping => 'shipped_at',
                SellerOrderStatus::Delivered => 'delivered_at',
                SellerOrderStatus::Completed => 'completed_at',
                SellerOrderStatus::Cancelled => 'cancelled_at',
                default => null,
            };

            if ($timestamp && ! $order->{$timestamp}) {
                $order->{$timestamp} = now();
            }

            if ($next === SellerOrderStatus::Cancelled) {
                $order->cancelled_reason = $note;
                $order->cancelled_by = $actor?->id;
            }

            $order->save();

            if ($next === SellerOrderStatus::Cancelled) {
                $this->closeShipmentBeforePickup($order, $actor, $note);
            }

            $order->statusHistories()->create([
                'from_status' => $current->value,
                'to_status' => $next->value,
                'note' => $note,
                'user_id' => $actor?->id,
            ]);

            $this->auditLogger->log('order.status_changed', $order, ['status' => $current->value], ['status' => $next->value, 'note' => $note], $actor);

            // Tell the customer once the change is committed, unless they made it themselves.
            DB::afterCommit(function () use ($order, $actor) {
                $customer = $order->order()->first()?->customer;

                if ($customer && ! $customer->is($actor) && OrderStatusUpdated::messageFor($order->status)) {
                    $customer->notify(new OrderStatusUpdated($order));
                }
            });

            return $order;
        });
    }

    /**
     * The status the order had before it entered `return_requested`, used when a return is rejected.
     */
    public function statusBeforeReturn(SellerOrder $order): SellerOrderStatus
    {
        $previous = $order->statusHistories()
            ->where('to_status', SellerOrderStatus::ReturnRequested->value)
            ->latest('id')
            ->value('from_status');

        return SellerOrderStatus::tryFrom((string) $previous) ?? SellerOrderStatus::Delivered;
    }

    /**
     * A packed order cancelled before the rider collected it: the waiting shipment is closed
     * and any rider job is cancelled so nobody is sent to pick it up.
     */
    private function closeShipmentBeforePickup(SellerOrder $order, ?User $actor, ?string $note): void
    {
        $shipment = $order->shipment()->first();

        if (! $shipment || ! $shipment->status->isBeforePickup()) {
            return;
        }

        $from = $shipment->status->value;
        $shipment->forceFill(['status' => ShipmentStatus::ReturnedToSeller])->save();
        $shipment->assignments()->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])->update(['status' => AssignmentStatus::Cancelled]);
        $shipment->statusHistories()->create([
            'from_status' => $from,
            'to_status' => ShipmentStatus::ReturnedToSeller->value,
            'note' => 'Order cancelled before pickup'.($note ? ": {$note}" : ''),
            'user_id' => $actor?->id,
        ]);
    }

    private function applyStockEffects(SellerOrder $order, SellerOrderStatus $current, SellerOrderStatus $next, ?User $actor): void
    {
        $items = $order->items()->with('variation')->get();

        foreach ($items as $item) {
            if ($next === SellerOrderStatus::Cancelled && $current->holdsReservedStock()) {
                $this->inventory->release($item->variation, $item->quantity, $order, $actor);
            }

            if ($next === SellerOrderStatus::Shipping && $current->holdsReservedStock()) {
                $this->inventory->commitSale($item->variation, $item->quantity, $order, $actor);
                $item->product()->increment('sold_count', $item->quantity);
            }

            if ($next === SellerOrderStatus::Returned) {
                $this->inventory->returnToStock($item->variation, $item->quantity, $order, $actor);
            }
        }
    }
}
