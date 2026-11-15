<?php

namespace App\Models;

use App\Enums\PartnerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsPartner extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'code',
        'contact_phone',
        'contact_email',
        'address',
        'base_delivery_fee',
        'rider_fee_per_delivery',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_delivery_fee' => 'decimal:2',
            'rider_fee_per_delivery' => 'decimal:2',
            'status' => PartnerStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<ServiceArea, $this>
     */
    public function serviceAreas(): HasMany
    {
        return $this->hasMany(ServiceArea::class);
    }

    /**
     * @return HasMany<RiderProfile, $this>
     */
    public function riders(): HasMany
    {
        return $this->hasMany(RiderProfile::class);
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * @return HasMany<RiderRemittance, $this>
     */
    public function remittances(): HasMany
    {
        return $this->hasMany(RiderRemittance::class);
    }
}
