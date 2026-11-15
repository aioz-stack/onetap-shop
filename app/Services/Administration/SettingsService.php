<?php

namespace App\Services\Administration;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write access to `system_settings`, cached per request cycle.
 */
class SettingsService
{
    private const CACHE_KEY = 'system_settings.all';

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return $this->all()[$group][$key] ?? $default;
    }

    public function set(string $group, string $key, ?string $value): void
    {
        SystemSetting::query()->updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'.public');
    }

    /**
     * Settings flagged public, safe to share with the frontend.
     *
     * @return array<string, array<string, string|null>>
     */
    public function public(): array
    {
        return Cache::remember(self::CACHE_KEY.'.public', 300, fn () => SystemSetting::query()
            ->where('is_public', true)
            ->get()
            ->groupBy('group')
            ->map(fn ($rows) => $rows->pluck('value', 'key')->all())
            ->all());
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, fn () => SystemSetting::query()
            ->get()
            ->groupBy('group')
            ->map(fn ($rows) => $rows->pluck('value', 'key')->all())
            ->all());
    }
}
