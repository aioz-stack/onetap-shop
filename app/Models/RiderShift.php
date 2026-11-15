<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderShift extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['rider_profile_id', 'started_at', 'ended_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<RiderProfile, $this>
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class, 'rider_profile_id');
    }
}
