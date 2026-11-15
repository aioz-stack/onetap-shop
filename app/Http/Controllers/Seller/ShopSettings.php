<?php

namespace App\Http\Controllers\Seller;

use App\Models\ShopCategory;
use App\Services\Administration\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shop profile, branding, decoration/template and vacation mode.
 */
class ShopSettings extends SellerController
{
    public const TEMPLATES = ['classic' => 'Classic: banner on top, logo beside the name', 'spotlight' => 'Spotlight: full-width banner with the name over it', 'minimal' => 'Minimal: no banner, compact header'];

    public function __construct(private AuditLogger $auditLogger) {}

    public function edit(Request $request): Response
    {
        $shop = $this->shop($request);

        return Inertia::render('Seller/Shop/Edit', [
            'shop' => $shop->only(['id', 'name', 'slug', 'description', 'shop_category_id', 'pickup_street', 'pickup_barangay', 'pickup_city', 'pickup_province', 'pickup_region', 'pickup_phone', 'template', 'vacation_mode', 'vacation_message']) + [
                'logo_url' => $shop->logo ? asset('storage/'.$shop->logo) : null,
                'banner_url' => $shop->banner ? asset('storage/'.$shop->banner) : null,
                'decoration' => $shop->decoration ?? ['accent_color' => '#0f9d76', 'announcement' => ''],
            ],
            'categories' => ShopCategory::query()->orderBy('name')->get(['id', 'name']),
            'templates' => collect(self::TEMPLATES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'shop_category_id' => ['nullable', 'exists:shop_categories,id'],
            'pickup_street' => ['required', 'string', 'max:255'],
            'pickup_barangay' => ['required', 'string', 'max:100'],
            'pickup_city' => ['required', 'string', 'max:100'],
            'pickup_province' => ['required', 'string', 'max:100'],
            'pickup_region' => ['required', 'string', 'max:100'],
            'pickup_phone' => ['required', 'string', 'regex:/^(\+63|0)9\d{9}$/'],
        ], ['pickup_phone.regex' => 'Enter a valid Philippine mobile number.']);

        $old = $shop->only(array_keys($validated));
        $shop->update($validated);
        $this->auditLogger->log('shop.updated', $shop, $old, $validated);

        return back()->with('success', 'Shop profile saved.');
    }

    public function branding(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=800,min_height=200'],
            'remove_logo' => ['boolean'],
            'remove_banner' => ['boolean'],
            'template' => ['required', Rule::in(array_keys(self::TEMPLATES))],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'announcement' => ['nullable', 'string', 'max:160'],
        ]);

        foreach (['logo', 'banner'] as $field) {
            if ($request->hasFile($field) || $request->boolean("remove_{$field}")) {
                if ($shop->{$field}) {
                    Storage::disk('public')->delete($shop->{$field});
                }

                $shop->{$field} = $request->hasFile($field) ? $request->file($field)->store("shops/{$shop->id}", 'public') : null;
            }
        }

        $shop->template = $validated['template'];
        $shop->decoration = ['accent_color' => $validated['accent_color'], 'announcement' => $validated['announcement'] ?? ''];
        $shop->save();
        $this->auditLogger->log('shop.branding_updated', $shop, newValues: ['template' => $shop->template]);

        return back()->with('success', 'Branding saved.');
    }

    public function vacation(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate([
            'vacation_mode' => ['required', 'boolean'],
            'vacation_message' => ['nullable', 'string', 'max:160'],
        ]);

        $shop->update($validated);
        $this->auditLogger->log($validated['vacation_mode'] ? 'shop.vacation_on' : 'shop.vacation_off', $shop);

        return back()->with('success', $validated['vacation_mode'] ? 'Vacation mode is on. Customers cannot order until you turn it off.' : 'Welcome back! Your shop is taking orders again.');
    }
}
