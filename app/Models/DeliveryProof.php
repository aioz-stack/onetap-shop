<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryProof extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shipment_id',
        'rider_profile_id',
        'photo_path',
        'recipient_name',
        'recipient_relation',
        'signature_path',
        'lat',
        'lng',
        'delivered_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['photo_path', 'signature_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'delivered_at' => 'datetime',
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
}
