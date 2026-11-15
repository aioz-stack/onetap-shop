<?php

namespace App\Services\Logistics;

use App\Enums\RiderAvailability;
use App\Enums\RiderStatus;
use App\Models\RiderProfile;
use App\Models\RiderShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rider shifts and availability.
 *
 * Signed out = offline. Signed in = available (taking jobs) or busy (on a break / unavailable).
 */
class RiderShiftService
{
    public function signIn(RiderProfile $rider): RiderShift
    {
        if ($rider->status !== RiderStatus::Active) {
            throw ValidationException::withMessages(['shift' => 'Your rider account is not active yet. Ask your logistics partner.']);
        }

        return DB::transaction(function () use ($rider) {
            $open = $this->openShift($rider);

            if (! $open) {
                $open = $rider->shifts()->create(['started_at' => now()]);
            }

            $rider->forceFill(['availability' => RiderAvailability::Available, 'last_seen_at' => now()])->save();

            return $open;
        });
    }

    public function signOut(RiderProfile $rider): ?RiderShift
    {
        return DB::transaction(function () use ($rider) {
            $open = $this->openShift($rider);
            $open?->forceFill(['ended_at' => now()])->save();
            $rider->forceFill(['availability' => RiderAvailability::Offline, 'last_seen_at' => now()])->save();

            return $open;
        });
    }

    /**
     * Toggle between available and unavailable while on shift.
     */
    public function setAvailable(RiderProfile $rider, bool $available): RiderProfile
    {
        if (! $this->openShift($rider)) {
            throw ValidationException::withMessages(['shift' => 'Sign in to your shift first.']);
        }

        $rider->forceFill(['availability' => $available ? RiderAvailability::Available : RiderAvailability::Busy, 'last_seen_at' => now()])->save();

        return $rider;
    }

    public function openShift(RiderProfile $rider): ?RiderShift
    {
        return $rider->shifts()->whereNull('ended_at')->latest('started_at')->first();
    }

    public function assertOnShift(RiderProfile $rider): void
    {
        if ($rider->status !== RiderStatus::Active) {
            throw ValidationException::withMessages(['status' => 'Your rider account is not active.']);
        }

        if (! $this->openShift($rider)) {
            throw ValidationException::withMessages(['status' => 'Sign in to your shift before updating deliveries.']);
        }
    }

    public function touch(RiderProfile $rider, ?float $lat = null, ?float $lng = null): void
    {
        $rider->forceFill(array_filter(['last_seen_at' => now(), 'last_lat' => $lat, 'last_lng' => $lng], fn ($value) => $value !== null))->save();
    }
}
