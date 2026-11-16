<?php

namespace App\Http\Controllers\Seller;

use App\Models\ShopCategory;
use App\Services\Catalog\ShopProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shop profile, branding, decoration/template and vacation mode. The rules and changes live in
 * ShopProfileService, shared with the seller app API.
 */
class ShopSettings extends SellerController
{
    public const TEMPLATES = ShopProfileService::TEMPLATES;

    public function __construct(private ShopProfileService $profiles) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('Seller/Shop/Edit', [
            'shop' => $this->profiles->present($this->shop($request)),
            'categories' => ShopCategory::query()->orderBy('name')->get(['id', 'name']),
            'templates' => collect(self::TEMPLATES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(ShopProfileService::profileRules(), ['pickup_phone.regex' => 'Enter a valid Philippine mobile number.']);
        $this->profiles->updateProfile($this->shop($request), $validated);

        return back()->with('success', 'Shop profile saved.');
    }

    public function branding(Request $request): RedirectResponse
    {
        $validated = $request->validate(ShopProfileService::brandingRules());
        $this->profiles->updateBranding($this->shop($request), $validated + ['remove_logo' => $request->boolean('remove_logo'), 'remove_banner' => $request->boolean('remove_banner')], $request->file('logo'), $request->file('banner'));

        return back()->with('success', 'Branding saved.');
    }

    public function vacation(Request $request): RedirectResponse
    {
        $validated = $request->validate(ShopProfileService::vacationRules());
        $this->profiles->setVacation($this->shop($request), (bool) $validated['vacation_mode'], $validated['vacation_message'] ?? null);

        return back()->with('success', $validated['vacation_mode'] ? 'Vacation mode is on. Customers cannot order until you turn it off.' : 'Welcome back! Your shop is taking orders again.');
    }
}
