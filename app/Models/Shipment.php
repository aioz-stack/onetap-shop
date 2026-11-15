<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tracking_number',
        'seller_order_id',
        'logistics_partner_id',
        'status',
        'pickup_address',
        'dropoff_address',
        'cod_amount',
        'delivery_fee',
        'attempts',
        'scheduled_at',
        'picked_up_at',
        'delivered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'pickup_address' => 'array',
            'dropoff_address' => 'array',
            'cod_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    /**
     * @return BelongsTo<LogisticsPartner, $this>
     */
    public function logisticsPartner(): BelongsTo
    {
        return $this->belongsTo(LogisticsPartner::class);
    }

    /**
     * @return HasMany<ShipmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /**
     * @return HasMany<ShipmentStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class);
    }

    /**
     * @return HasMany<DeliveryAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    /**
     * @return HasOne<DeliveryProof, $this>
     */
    public function proof(): HasOne
    {
        return $this->hasOne(DeliveryProof::class);
    }

    /**
     * @return HasOne<RiderCollection, $this>
     */
    public function collection(): HasOne
    {
        return $this->hasOne(RiderCollection::class);
    }
}
