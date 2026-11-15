<?php

namespace App\Http\Controllers\Rider;

use App\Services\Logistics\RiderShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class Shift extends RiderController
{
    public function __construct(private RiderShiftService $shifts) {}

    public function signIn(Request $request): RedirectResponse
    {
        $this->shifts->signIn($this->rider($request));

        return back()->with('success', 'You are signed in and available for jobs.');
    }

    public function signOut(Request $request): RedirectResponse
    {
        $this->shifts->signOut($this->rider($request));

        return back()->with('success', 'Shift ended. You are offline.');
    }

    public function availability(Request $request): RedirectResponse
    {
        $validated = $request->validate(['available' => ['required', 'boolean']]);
        $this->shifts->setAvailable($this->rider($request), (bool) $validated['available']);

        return back()->with('success', $validated['available'] ? 'You are available for new jobs.' : 'You are marked unavailable. Your current jobs stay with you.');
    }
}
