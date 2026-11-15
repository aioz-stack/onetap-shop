<?php

namespace App\Models;

use App\Enums\RemittanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiderRemittance extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rider_profile_id',
        'logistics_partner_id',
        'remittance_date',
        'expected_amount',
        'remitted_amount',
        'discrepancy',
        'status',
        'confirmed_by',
        'confirmed_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remittance_date' => 'date',
            'expected_amount' => 'decimal:2',
            'remitted_amount' => 'decimal:2',
            'discrepancy' => 'decimal:2',
            'status' => RemittanceStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RiderProfile, $this>
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class, 'rider_profile_id');
    }

    /**
     * @return BelongsTo<LogisticsPartner, $this>
     */
    public function logisticsPartner(): BelongsTo
    {
        return $this->belongsTo(LogisticsPartner::class);
    }

    /**
     * @return HasMany<RiderCollection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(RiderCollection::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
