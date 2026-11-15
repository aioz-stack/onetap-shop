<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Default marketplace settings. Existing values are never overwritten.
     *
     * @var array<string, array<string, array{0: string, 1: bool}>>
     */
    private const DEFAULTS = [
        'general' => [
            'marketplace_name' => ['Onetap Marketplace', true],
            'support_email' => ['support@onetap.test', true],
            'support_phone' => ['', true],
            'currency' => ['PHP', true],
            'timezone' => ['Asia/Manila', false],
        ],
        'branding' => [
            'tagline' => ['Your neighborhood, one tap away.', true],
            'primary_color' => ['#0f9d76', true],
        ],
        'payment' => [
            'cod_enabled' => ['1', true],
            'cod_max_amount' => ['20000.00', true],
        ],
        'shipping' => [
            'default_delivery_fee' => ['59.00', true],
            'free_shipping_threshold' => ['0.00', true],
        ],
        'finance' => [
            'commission_rate' => ['5.00', false],
            'earnings_hold_days' => ['3', false],
            'min_withdrawal' => ['500.00', false],
        ],
        'orders' => [
            'auto_complete_days' => ['7', false],
        ],
        'security' => [
            'max_login_attempts' => ['5', false],
        ],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $group => $settings) {
            foreach ($settings as $key => [$value, $isPublic]) {
                SystemSetting::query()->firstOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => $value, 'is_public' => $isPublic],
                );
            }
        }
    }
}
