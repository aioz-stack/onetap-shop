<?php

namespace App\Services\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductViolation;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use App\Services\Communication\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin product review: approve, reject, flag violations, unpublish.
 */
class ProductModerationService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function approve(Product $product, User $admin): Product
    {
        $this->assertStatus($product, [ProductStatus::UnderReview, ProductStatus::Rejected, ProductStatus::Unpublished]);

        if (! $product->variations()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['status' => 'A product needs at least one active variation before it can go live.']);
        }

        return $this->change($product, ProductStatus::Live, $admin, 'product.approved', [
            'rejection_reason' => null,
            'published_at' => $product->published_at ?? now(),
        ]);
    }

    public function reject(Product $product, User $admin, string $reason): Product
    {
        $this->assertStatus($product, [ProductStatus::UnderReview, ProductStatus::Live]);

        return $this->change($product, ProductStatus::Rejected, $admin, 'product.rejected', ['rejection_reason' => $reason]);
    }

    public function unpublish(Product $product, User $admin, ?string $reason = null): Product
    {
        $this->assertStatus($product, [ProductStatus::Live]);

        return $this->change($product, ProductStatus::Unpublished, $admin, 'product.unpublished', ['rejection_reason' => $reason]);
    }

    /**
     * Record a violation and take the product off the storefront.
     */
    public function flagViolation(Product $product, User $admin, string $reason, ?string $details = null): ProductViolation
    {
        return DB::transaction(function () use ($product, $admin, $reason, $details) {
            $violation = $product->violations()->create([
                'reported_by' => $admin->id,
                'reason' => $reason,
                'details' => $details,
                'status' => 'open',
            ]);

            $this->change($product, ProductStatus::Violation, $admin, 'product.violation_flagged', ['rejection_reason' => $reason]);

            return $violation;
        });
    }

    /**
     * Close every open violation. The product returns to review so the seller's fix is checked.
     */
    public function resolveViolations(Product $product, User $admin): Product
    {
        $this->assertStatus($product, [ProductStatus::Violation]);

        return DB::transaction(function () use ($product, $admin) {
            $product->violations()->where('status', 'open')->update([
                'status' => 'resolved',
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ]);

            return $this->change($product, ProductStatus::UnderReview, $admin, 'product.violation_resolved');
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function change(Product $product, ProductStatus $to, User $admin, string $action, array $extra = []): Product
    {
        $from = $product->status->value;
        $product->forceFill(array_merge($extra, ['status' => $to]))->save();
        $this->auditLogger->log($action, $product, ['status' => $from], ['status' => $to->value] + $extra, $admin);

        $outcome = match ($to) {
            ProductStatus::Live => 'approved',
            ProductStatus::Rejected => 'rejected',
            ProductStatus::Violation => 'violation',
            ProductStatus::Unpublished => 'unpublished',
            default => null,
        };

        if ($outcome) {
            app(Notifier::class)->productReviewed($product, $outcome, $extra['rejection_reason'] ?? null);
        }

        return $product;
    }

    /**
     * @param  list<ProductStatus>  $allowed
     */
    private function assertStatus(Product $product, array $allowed): void
    {
        if (! in_array($product->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "This product is {$product->status->label()} and cannot be changed that way."]);
        }
    }
}
