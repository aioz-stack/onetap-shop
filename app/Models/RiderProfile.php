<?php

namespace App\Models;

use App\Enums\RiderAvailability;
use App\Enums\RiderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiderProfile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'logistics_partner_id',
        'vehicle_type',
        'plate_number',
        'license_number',
        'status',
        'availability',
        'last_lat',
        'last_lng',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RiderStatus::class,
            'availability' => RiderAvailability::class,
            'last_seen_at' => 'datetime',
            'last_lat' => 'decimal:7',
            'last_lng' => 'decimal:7',
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
     * @return BelongsTo<LogisticsPartner, $this>
     */
    public function logisticsPartner(): BelongsTo
    {
        return $this->belongsTo(LogisticsPartner::class);
    }

    /**
     * @return HasMany<DeliveryAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    /**
     * @return HasMany<RiderCollection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(RiderCollection::class);
    }

    /**
     * @return HasMany<RiderRemittance, $this>
     */
    public function remittances(): HasMany
    {
        return $this->hasMany(RiderRemittance::class);
    }
}
