<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceArea extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'logistics_partner_id',
        'region',
        'province',
        'city',
        'barangay',
        'delivery_fee',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LogisticsPartner, $this>
     */
    public function logisticsPartner(): BelongsTo
    {
        return $this->belongsTo(LogisticsPartner::class);
    }
}
