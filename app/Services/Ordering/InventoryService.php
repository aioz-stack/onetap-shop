<?php

namespace App\Services\Ordering;

use App\Enums\InventoryMovementType;
use App\Models\ProductInventory;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Every stock change goes through here so `product_inventory` stays a complete ledger.
 *
 * `stock` is on-hand quantity; `reserved` is the part held by unshipped orders.
 * Callers must already be inside a DB transaction.
 */
class InventoryService
{
    public function restock(ProductVariation $variation, int $quantity, ?User $user = null, ?string $note = null): ProductInventory
    {
        $this->assertPositive($quantity);
        $variation = $this->lock($variation);
        $variation->increment('stock', $quantity);

        return $this->record($variation, InventoryMovementType::Restock, $quantity, null, $user, $note);
    }

    public function adjust(ProductVariation $variation, int $newStock, ?User $user = null, ?string $note = null): ?ProductInventory
    {
        $variation = $this->lock($variation);
        $delta = $newStock - $variation->stock;

        if ($delta === 0) {
            return null;
        }

        if ($newStock < $variation->reserved) {
            throw new InvalidArgumentException('Stock cannot go below the quantity reserved by open orders.');
        }

        $variation->forceFill(['stock' => $newStock])->save();

        return $this->record($variation, InventoryMovementType::Adjustment, $delta, null, $user, $note);
    }

    /**
     * Hold stock for a placed order.
     */
    public function reserve(ProductVariation $variation, int $quantity, Model $reference, ?User $user = null): ProductInventory
    {
        $this->assertPositive($quantity);
        $variation = $this->lock($variation);

        if ($variation->stock - $variation->reserved < $quantity) {
            throw new InvalidArgumentException("Only {$this->available($variation)} left of {$variation->sku}.");
        }

        $variation->increment('reserved', $quantity);

        return $this->record($variation, InventoryMovementType::Reserve, 0, $reference, $user, "Reserved {$quantity}");
    }

    /**
     * Give reserved stock back (order cancelled before it shipped).
     */
    public function release(ProductVariation $variation, int $quantity, Model $reference, ?User $user = null): ProductInventory
    {
        $this->assertPositive($quantity);
        $variation = $this->lock($variation);
        $variation->forceFill(['reserved' => max(0, $variation->reserved - $quantity)])->save();

        return $this->record($variation, InventoryMovementType::Release, 0, $reference, $user, "Released {$quantity}");
    }

    /**
     * Convert a reservation into a sale when the order ships.
     */
    public function commitSale(ProductVariation $variation, int $quantity, Model $reference, ?User $user = null): ProductInventory
    {
        $this->assertPositive($quantity);
        $variation = $this->lock($variation);
        $variation->forceFill([
            'stock' => max(0, $variation->stock - $quantity),
            'reserved' => max(0, $variation->reserved - $quantity),
        ])->save();

        return $this->record($variation, InventoryMovementType::Sale, -$quantity, $reference, $user);
    }

    /**
     * Put returned items back on hand.
     */
    public function returnToStock(ProductVariation $variation, int $quantity, Model $reference, ?User $user = null): ProductInventory
    {
        $this->assertPositive($quantity);
        $variation = $this->lock($variation);
        $variation->increment('stock', $quantity);

        return $this->record($variation, InventoryMovementType::Return, $quantity, $reference, $user);
    }

    public function available(ProductVariation $variation): int
    {
        return max(0, $variation->stock - $variation->reserved);
    }

    private function lock(ProductVariation $variation): ProductVariation
    {
        return ProductVariation::query()->lockForUpdate()->findOrFail($variation->id);
    }

    private function record(ProductVariation $variation, InventoryMovementType $type, int $quantity, ?Model $reference, ?User $user, ?string $note = null): ProductInventory
    {
        return ProductInventory::query()->create([
            'product_variation_id' => $variation->id,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $variation->refresh()->stock,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $user?->id,
            'note' => $note,
        ]);
    }

    private function assertPositive(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }
    }
}
