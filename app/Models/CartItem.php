<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shopping_cart_id',
        'product_variation_id',
        'quantity',
        'is_selected',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ShoppingCart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(ShoppingCart::class, 'shopping_cart_id');
    }

    /**
     * @return BelongsTo<ProductVariation, $this>
     */
    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }
}
