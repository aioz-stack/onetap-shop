<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\RiderProfile;
use Illuminate\Http\Request;

/**
 * Base for Rider Panel controllers. A rider without a profile cannot do anything here.
 */
abstract class RiderController extends Controller
{
    protected function rider(Request $request): RiderProfile
    {
        $rider = $request->user()->riderProfile;

        abort_unless($rider, 403, 'Your rider profile has not been set up yet.');

        return $rider->loadMissing('logisticsPartner:id,name,code,rider_fee_per_delivery');
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    protected function location(Request $request): array
    {
        return ['lat' => $request->filled('lat') ? (float) $request->input('lat') : null, 'lng' => $request->filled('lng') ? (float) $request->input('lng') : null];
    }
}
