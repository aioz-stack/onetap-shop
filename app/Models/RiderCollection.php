<?php

namespace App\Models;

use App\Enums\CollectionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderCollection extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shipment_id',
        'rider_profile_id',
        'rider_remittance_id',
        'amount_due',
        'amount_collected',
        'collected_at',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'amount_collected' => 'decimal:2',
            'collected_at' => 'datetime',
            'status' => CollectionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo<RiderProfile, $this>
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class, 'rider_profile_id');
    }

    /**
     * @return BelongsTo<RiderRemittance, $this>
     */
    public function remittance(): BelongsTo
    {
        return $this->belongsTo(RiderRemittance::class, 'rider_remittance_id');
    }
}
