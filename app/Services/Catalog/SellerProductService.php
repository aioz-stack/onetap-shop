<?php

namespace App\Services\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use App\Services\Ordering\InventoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Seller-side product management.
 *
 * Review rule: new products and changes to what a product *is* (name, description,
 * category, brand, photos) go back to admin review. Price, stock and variation edits on a
 * live product stay live.
 */
class SellerProductService
{
    public const MAX_IMAGES = 8;

    private const REVIEWED_FIELDS = ['name', 'description', 'product_category_id', 'brand'];

    public function __construct(private InventoryService $inventory, private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data  product fields + variations[]
     * @param  list<UploadedFile>  $images
     */
    public function create(Shop $shop, User $seller, array $data, array $images, bool $submit): Product
    {
        if (! $images) {
            throw ValidationException::withMessages(['images' => 'Add at least one photo.']);
        }

        return DB::transaction(function () use ($shop, $seller, $data, $images, $submit) {
            $product = $shop->products()->create($this->productFields($data) + [
                'slug' => $this->uniqueSlug($shop, $data['name']),
                'status' => $submit ? ProductStatus::UnderReview : ProductStatus::Draft,
            ]);

            $this->syncVariations($product, $data['variations'], $seller);
            $this->addImages($product, $images);
            $this->auditLogger->log('product.created', $product, newValues: ['status' => $product->status->value], actor: $seller);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $newImages
     * @param  list<int>  $removeImageIds
     */
    public function update(Product $product, User $seller, array $data, array $newImages = [], array $removeImageIds = [], ?int $primaryImageId = null, bool $submit = false): Product
    {
        $this->assertEditable($product);

        return DB::transaction(function () use ($product, $seller, $data, $newImages, $removeImageIds, $primaryImageId, $submit) {
            $product->fill($this->productFields($data));
            $contentChanged = $product->isDirty(self::REVIEWED_FIELDS) || $newImages || $removeImageIds;
            $product->save();

            $this->syncVariations($product, $data['variations'], $seller);
            $this->removeImages($product, $removeImageIds);
            $this->addImages($product, $newImages);
            $this->setPrimaryImage($product, $primaryImageId);

            if (! $product->images()->exists()) {
                throw ValidationException::withMessages(['images' => 'Keep at least one photo.']);
            }

            $next = match (true) {
                $product->status === ProductStatus::Live && $contentChanged => ProductStatus::UnderReview,
                $submit && in_array($product->status, [ProductStatus::Draft, ProductStatus::Rejected], true) => ProductStatus::UnderReview,
                default => $product->status,
            };

            $product->forceFill(['status' => $next])->save();
            $this->auditLogger->log('product.updated', $product, newValues: ['status' => $next->value, 'needs_review' => $next === ProductStatus::UnderReview], actor: $seller);

            return $product;
        });
    }

    public function submit(Product $product, User $seller): Product
    {
        return $this->move($product, [ProductStatus::Draft, ProductStatus::Rejected], ProductStatus::UnderReview, $seller);
    }

    public function unpublish(Product $product, User $seller): Product
    {
        return $this->move($product, [ProductStatus::Live], ProductStatus::Unpublished, $seller);
    }

    /**
     * Previously approved products go straight back live; never-approved ones need review.
     */
    public function republish(Product $product, User $seller): Product
    {
        return $this->move($product, [ProductStatus::Unpublished], $product->published_at ? ProductStatus::Live : ProductStatus::UnderReview, $seller);
    }

    public function delete(Product $product, User $seller): void
    {
        DB::transaction(function () use ($product, $seller) {
            $product->variations()->update(['is_active' => false]);
            $product->delete();
            $this->auditLogger->log('product.deleted', $product, actor: $seller);
        });
    }

    public function adjustStock(ProductVariation $variation, int $newStock, User $seller, ?string $note = null): void
    {
        DB::transaction(function () use ($variation, $newStock, $seller, $note) {
            try {
                $this->inventory->adjust($variation, $newStock, $seller, $note ?? 'Stock count updated');
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['stock' => $exception->getMessage()]);
            }
        });
    }

    /**
     * @param  list<array{id?: int|null, label: string, price: numeric-string, compare_at_price?: numeric-string|null, stock: int, is_active?: bool}>  $rows
     */
    private function syncVariations(Product $product, array $rows, User $seller): void
    {
        $keep = [];

        foreach (array_values($rows) as $position => $row) {
            $fields = [
                'label' => trim($row['label']) ?: 'Default',
                'price' => number_format((float) $row['price'], 2, '.', ''),
                'compare_at_price' => filled($row['compare_at_price'] ?? null) ? number_format((float) $row['compare_at_price'], 2, '.', '') : null,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'is_default' => $position === 0,
            ];

            $variation = filled($row['id'] ?? null) ? $product->variations()->find($row['id']) : null;

            if ($variation) {
                $variation->update($fields);

                try {
                    $this->inventory->adjust($variation, (int) $row['stock'], $seller, 'Edited in product form');
                } catch (\InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(["variations.{$position}.stock" => $exception->getMessage()]);
                }
            } else {
                $variation = $product->variations()->create($fields + ['sku' => $this->sku($product), 'stock' => 0]);

                if ((int) $row['stock'] > 0) {
                    $this->inventory->restock($variation, (int) $row['stock'], $seller, 'Initial stock');
                }
            }

            $keep[] = $variation->id;
        }

        // Variations that were removed: delete if never sold, otherwise deactivate to keep order history intact.
        foreach ($product->variations()->whereNotIn('id', $keep)->get() as $removed) {
            \App\Models\OrderItem::query()->where('product_variation_id', $removed->id)->exists() || $removed->reserved > 0
                ? $removed->update(['is_active' => false])
                : $removed->delete();
        }

        if (! $product->variations()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['variations' => 'Keep at least one active variation.']);
        }

        $product->forceFill(['base_price' => $product->variations()->where('is_active', true)->min('price')])->save();
    }

    /**
     * @param  list<UploadedFile>  $images
     */
    private function addImages(Product $product, array $images): void
    {
        if (! $images) {
            return;
        }

        if ($product->images()->count() + count($images) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images' => 'A product can have up to '.self::MAX_IMAGES.' photos.']);
        }

        $order = (int) $product->images()->max('sort_order');
        $hasPrimary = $product->images()->where('is_primary', true)->exists();

        foreach ($images as $image) {
            $product->images()->create([
                'path' => $image->store("products/{$product->shop_id}", 'public'),
                'sort_order' => ++$order,
                'is_primary' => ! $hasPrimary,
            ]);
            $hasPrimary = true;
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function removeImages(Product $product, array $ids): void
    {
        foreach ($product->images()->whereIn('id', $ids)->get() as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
    }

    private function setPrimaryImage(Product $product, ?int $imageId): void
    {
        $target = ($imageId ? $product->images()->find($imageId) : null) ?? ($product->images()->where('is_primary', true)->exists() ? null : $product->images()->orderBy('sort_order')->first());

        if ($target) {
            $product->images()->update(['is_primary' => false]);
            $target->forceFill(['is_primary' => true])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function productFields(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'brand' => $data['brand'] ?? null,
            'product_category_id' => $data['product_category_id'],
            'weight_grams' => (int) ($data['weight_grams'] ?? 0),
            'base_price' => number_format((float) collect($data['variations'])->min('price'), 2, '.', ''),
        ];
    }

    /**
     * @param  list<ProductStatus>  $from
     */
    private function move(Product $product, array $from, ProductStatus $to, User $seller): Product
    {
        if (! in_array($product->status, $from, true)) {
            throw ValidationException::withMessages(['status' => "A {$product->status->label()} product cannot be moved to {$to->label()}."]);
        }

        if ($to !== ProductStatus::Unpublished && ! $product->images()->exists()) {
            throw ValidationException::withMessages(['status' => 'Add at least one photo first.']);
        }

        $old = $product->status->value;
        $product->forceFill(['status' => $to])->save();
        $this->auditLogger->log('product.'.$to->value, $product, ['status' => $old], ['status' => $to->value], $seller);

        return $product;
    }

    private function assertEditable(Product $product): void
    {
        if ($product->status === ProductStatus::Violation) {
            throw ValidationException::withMessages(['status' => 'This product has an open violation. Fix the issue described below and wait for the admin to clear it.']);
        }
    }

    private function uniqueSlug(Shop $shop, string $name): string
    {
        $base = Str::slug($shop->slug.' '.$name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function sku(Product $product): string
    {
        do {
            $sku = 'S'.$product->shop_id.'-'.Str::upper(Str::random(8));
        } while (ProductVariation::query()->where('sku', $sku)->exists());

        return $sku;
    }
}
