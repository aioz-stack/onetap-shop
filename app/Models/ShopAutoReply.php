<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Types: greeting, off_work (outside working hours), offline (vacation mode) and keyword.
 */
class ShopAutoReply extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'type',
        'keyword',
        'message',
        'schedule',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'schedule' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Whether a moment falls inside the shop's working hours. `$now` must already be in the shop's
     * local timezone. Hours may run past midnight (e.g. 20:00–02:00 counts the early hours as part of
     * the day the shift started).
     */
    public function isWithinWorkingHours(CarbonInterface $now): bool
    {
        $days = array_map('intval', $this->schedule['days'] ?? []);
        $start = $this->schedule['start'] ?? null;
        $end = $this->schedule['end'] ?? null;

        if (! $days || ! $start || ! $end) {
            return true;
        }

        $time = $now->format('H:i');

        if ($start < $end) {
            return in_array($now->dayOfWeekIso, $days, true) && $time >= $start && $time < $end;
        }

        // Overnight shift: evening part belongs to today, early-morning part to yesterday's shift.
        return (in_array($now->dayOfWeekIso, $days, true) && $time >= $start)
            || (in_array($now->copy()->subDay()->dayOfWeekIso, $days, true) && $time < $end);
    }
}
