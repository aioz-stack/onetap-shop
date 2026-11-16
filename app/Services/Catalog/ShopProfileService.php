<?php

namespace App\Services\Catalog;

use App\Models\Shop;
use App\Services\Administration\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Shop profile, branding and vacation mode — shared by the Seller Center and the seller app API.
 */
class ShopProfileService
{
    public const TEMPLATES = ['classic' => 'Classic: banner on top, logo beside the name', 'spotlight' => 'Spotlight: full-width banner with the name over it', 'minimal' => 'Minimal: no banner, compact header'];

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @return array<string, mixed>
     */
    public static function profileRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:1000'],
            'shop_category_id' => ['nullable', 'exists:shop_categories,id'],
            'pickup_street' => ['required', 'string', 'max:255'],
            'pickup_barangay' => ['required', 'string', 'max:100'],
            'pickup_city' => ['required', 'string', 'max:100'],
            'pickup_province' => ['required', 'string', 'max:100'],
            'pickup_region' => ['required', 'string', 'max:100'],
            'pickup_phone' => ['required', 'string', 'regex:/^(\+63|0)9\d{9}$/'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function brandingRules(): array
    {
        return [
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=800,min_height=200'],
            'remove_logo' => ['boolean'],
            'remove_banner' => ['boolean'],
            'template' => ['required', Rule::in(array_keys(self::TEMPLATES))],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'announcement' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function vacationRules(): array
    {
        return [
            'vacation_mode' => ['required', 'boolean'],
            'vacation_message' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * Everything the settings screens show.
     *
     * @return array<string, mixed>
     */
    public function present(Shop $shop): array
    {
        return $shop->only(['id', 'name', 'slug', 'description', 'shop_category_id', 'pickup_street', 'pickup_barangay', 'pickup_city', 'pickup_province', 'pickup_region', 'pickup_phone', 'template', 'vacation_mode', 'vacation_message']) + [
            'status' => $shop->status->value,
            'logo_url' => $shop->logo ? asset('storage/'.$shop->logo) : null,
            'banner_url' => $shop->banner ? asset('storage/'.$shop->banner) : null,
            'decoration' => $shop->decoration ?? ['accent_color' => '#0f9d76', 'announcement' => ''],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated with profileRules()
     */
    public function updateProfile(Shop $shop, array $data): Shop
    {
        $old = $shop->only(array_keys($data));
        $shop->update($data);
        $this->auditLogger->log('shop.updated', $shop, $old, $data);

        return $shop;
    }

    /**
     * @param  array<string, mixed>  $data  validated with brandingRules()
     */
    public function updateBranding(Shop $shop, array $data, ?UploadedFile $logo, ?UploadedFile $banner): Shop
    {
        foreach (['logo' => $logo, 'banner' => $banner] as $field => $file) {
            if ($file || ($data["remove_{$field}"] ?? false)) {
                if ($shop->{$field}) {
                    Storage::disk('public')->delete($shop->{$field});
                }

                $shop->{$field} = $file ? $file->store("shops/{$shop->id}", 'public') : null;
            }
        }

        $shop->template = $data['template'];
        $shop->decoration = ['accent_color' => $data['accent_color'], 'announcement' => $data['announcement'] ?? ''];
        $shop->save();
        $this->auditLogger->log('shop.branding_updated', $shop, newValues: ['template' => $shop->template]);

        return $shop;
    }

    public function setVacation(Shop $shop, bool $on, ?string $message): Shop
    {
        $shop->update(['vacation_mode' => $on, 'vacation_message' => $message]);
        $this->auditLogger->log($on ? 'shop.vacation_on' : 'shop.vacation_off', $shop);

        return $shop;
    }
}
