<?php

namespace App\Models;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'shop_id',
        'logistics_partner_id',
        'type',
        'direction',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'direction' => TransactionDirection::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<LogisticsPartner, $this>
     */
    public function logisticsPartner(): BelongsTo
    {
        return $this->belongsTo(LogisticsPartner::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
