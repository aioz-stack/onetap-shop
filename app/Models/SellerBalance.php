<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerBalance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'pending_balance',
        'available_balance',
        'total_earned',
        'total_withdrawn',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pending_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
