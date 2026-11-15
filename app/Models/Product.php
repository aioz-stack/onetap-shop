<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'product_category_id',
        'name',
        'slug',
        'description',
        'brand',
        'status',
        'rejection_reason',
        'base_price',
        'weight_grams',
        'sold_count',
        'rating_avg',
        'rating_count',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'base_price' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * @return HasMany<ProductVariation, $this>
     */
    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    /**
     * @return HasMany<ProductReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * @return HasMany<ProductViolation, $this>
     */
    public function violations(): HasMany
    {
        return $this->hasMany(ProductViolation::class);
    }

    /**
     * Products customers may see: live, in an active (not deleted) shop.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('products.status', ProductStatus::Live)
            ->whereHas('shop', fn (Builder $shops) => $shops->where('status', ShopStatus::Active));
    }

    /**
     * @return HasOne<ProductImage, $this>
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    /**
     * Promotions running right now, best discount first.
     *
     * @return BelongsToMany<Promotion, $this>
     */
    public function activePromotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_products')
            ->where('promotions.is_active', true)
            ->where(fn ($query) => $query->whereNull('promotions.starts_at')->orWhere('promotions.starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('promotions.ends_at')->orWhere('promotions.ends_at', '>=', now()))
            ->orderByDesc('promotions.discount_percent');
    }
}
