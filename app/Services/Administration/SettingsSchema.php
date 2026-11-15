<?php

namespace App\Services\Administration;

/**
 * The editable system settings, grouped as they appear in Admin → Settings.
 * Each field declares its input type, validation rules and whether the frontend may read it.
 */
class SettingsSchema
{
    /**
     * @return array<string, array{label: string, description: string, fields: array<string, array{label: string, type: string, rules: list<string>, public: bool, hint?: string, default: string, options?: list<string>}>}>
     */
    public static function groups(): array
    {
        return [
            'general' => [
                'label' => 'General',
                'description' => 'Marketplace name and support contacts.',
                'fields' => [
                    'marketplace_name' => ['label' => 'Marketplace name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'public' => true, 'default' => 'Onetap Marketplace'],
                    'support_email' => ['label' => 'Support email', 'type' => 'email', 'rules' => ['required', 'email', 'max:255'], 'public' => true, 'default' => 'support@onetap.test'],
                    'support_phone' => ['label' => 'Support phone', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:30'], 'public' => true, 'default' => ''],
                    'timezone' => ['label' => 'Timezone', 'type' => 'select', 'rules' => ['required', 'timezone'], 'public' => false, 'default' => 'Asia/Manila', 'options' => ['Asia/Manila', 'UTC']],
                ],
            ],
            'branding' => [
                'label' => 'Branding',
                'description' => 'How the marketplace presents itself.',
                'fields' => [
                    'tagline' => ['label' => 'Tagline', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:120'], 'public' => true, 'default' => 'Your neighborhood, one tap away.'],
                    'primary_color' => ['label' => 'Primary color', 'type' => 'color', 'rules' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'public' => true, 'default' => '#0f9d76', 'hint' => 'Used for emails and future mobile apps. The web theme uses the built-in palette.'],
                ],
            ],
            'payment' => [
                'label' => 'Payment',
                'description' => 'Payment methods offered at checkout.',
                'fields' => [
                    'cod_enabled' => ['label' => 'Cash on Delivery enabled', 'type' => 'boolean', 'rules' => ['required', 'boolean'], 'public' => true, 'default' => '1'],
                    'cod_max_amount' => ['label' => 'Maximum COD order (₱)', 'type' => 'money', 'rules' => ['required', 'numeric', 'min:0', 'max:1000000'], 'public' => true, 'default' => '20000.00'],
                ],
            ],
            'shipping' => [
                'label' => 'Shipping',
                'description' => 'Default delivery pricing. Logistics partners may override per area.',
                'fields' => [
                    'default_delivery_fee' => ['label' => 'Default delivery fee (₱)', 'type' => 'money', 'rules' => ['required', 'numeric', 'min:0', 'max:10000'], 'public' => true, 'default' => '59.00'],
                    'free_shipping_threshold' => ['label' => 'Free shipping from (₱)', 'type' => 'money', 'rules' => ['required', 'numeric', 'min:0'], 'public' => true, 'default' => '0.00', 'hint' => '0 turns free shipping off.'],
                ],
            ],
            'finance' => [
                'label' => 'Finance',
                'description' => 'Commission and seller payout rules.',
                'fields' => [
                    'commission_rate' => ['label' => 'Commission rate (%)', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0', 'max:50'], 'public' => false, 'default' => '5.00'],
                    'earnings_hold_days' => ['label' => 'Earnings hold (days)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:0', 'max:60'], 'public' => false, 'default' => '3', 'hint' => 'Days after completion before earnings become withdrawable.'],
                    'min_withdrawal' => ['label' => 'Minimum withdrawal (₱)', 'type' => 'money', 'rules' => ['required', 'numeric', 'min:0'], 'public' => false, 'default' => '500.00'],
                ],
            ],
            'orders' => [
                'label' => 'Orders',
                'description' => 'Order lifecycle timing.',
                'fields' => [
                    'auto_complete_days' => ['label' => 'Auto-complete delivered orders after (days)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:30'], 'public' => false, 'default' => '7'],
                ],
            ],
            'notifications' => [
                'label' => 'Notifications',
                'description' => 'Which events notify the admin team, and whether users are also emailed.',
                'fields' => [
                    'admin_email' => ['label' => 'Admin notification email', 'type' => 'email', 'rules' => ['nullable', 'email'], 'public' => false, 'default' => ''],
                    'notify_seller_applications' => ['label' => 'New seller applications', 'type' => 'boolean', 'rules' => ['required', 'boolean'], 'public' => false, 'default' => '1'],
                    'notify_refund_requests' => ['label' => 'New refund requests', 'type' => 'boolean', 'rules' => ['required', 'boolean'], 'public' => false, 'default' => '1'],
                    'notify_withdrawal_requests' => ['label' => 'New withdrawal requests', 'type' => 'boolean', 'rules' => ['required', 'boolean'], 'public' => false, 'default' => '1'],
                    'notify_unrouted_shipments' => ['label' => 'Shipments no logistics partner covers', 'type' => 'boolean', 'rules' => ['required', 'boolean'], 'public' => false, 'default' => '1'],
                    'email_users' => ['label' => 'Also email users their account alerts', 'type' => 'boolean', 'rules' => ['required', 'boolean'], 'public' => false, 'default' => '0', 'hint' => 'Customers, sellers, logistics partners and riders always get alerts in their Onetap inbox. Turn this on to email them too (uses the mail settings in .env).'],
                ],
            ],
            'api' => [
                'label' => 'API',
                'description' => 'Limits for the /api/v1 mobile API.',
                'fields' => [
                    'rate_limit_per_minute' => ['label' => 'Requests per minute per user', 'type' => 'number', 'rules' => ['required', 'integer', 'min:10', 'max:1000'], 'public' => false, 'default' => '60'],
                    'token_expiry_days' => ['label' => 'Token lifetime (days)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:0', 'max:365'], 'public' => false, 'default' => '30', 'hint' => '0 means tokens never expire.'],
                ],
            ],
            'security' => [
                'label' => 'Security',
                'description' => 'Sign-in protection.',
                'fields' => [
                    'max_login_attempts' => ['label' => 'Failed logins before lockout', 'type' => 'number', 'rules' => ['required', 'integer', 'min:3', 'max:20'], 'public' => false, 'default' => '5'],
                    'lockout_seconds' => ['label' => 'Lockout duration (seconds)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:30', 'max:3600'], 'public' => false, 'default' => '60'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function group(string $key): ?array
    {
        return self::groups()[$key] ?? null;
    }
}
