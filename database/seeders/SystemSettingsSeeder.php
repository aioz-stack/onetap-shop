<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Services\Administration\SettingsSchema;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Seed every setting declared in SettingsSchema. Existing values are never overwritten.
     */
    public function run(): void
    {
        SystemSetting::query()->firstOrCreate(['group' => 'general', 'key' => 'currency'], ['value' => 'PHP', 'is_public' => true]);

        foreach (SettingsSchema::groups() as $group => $definition) {
            foreach ($definition['fields'] as $key => $field) {
                SystemSetting::query()->firstOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => $field['default'], 'is_public' => $field['public']],
                );
            }
        }
    }
}
