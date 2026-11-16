<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Sends a notification to the phones a user registered for the matching mobile app.
 *
 * A notification picks its app from its in-app link: /seller… → seller app, /rider… → rider app,
 * customer pages (/account…, /products…, /) → customer app. Staff links (/admin, /logistics)
 * have no mobile app, so nothing is pushed for them.
 *
 * Drivers (config/services.php → push.driver): "log" (development: writes the push to the log),
 * "none". A provider driver such as Firebase Cloud Messaging goes in `deliver()`.
 */
class PushChannel
{
    /**
     * Notifications opt in by implementing toPush(): ['title' => …, 'body' => …, 'url' => …, 'data' => [...]].
     */
    public static function wants(object $notifiable, Notification $notification): bool
    {
        return method_exists($notification, 'toPush')
            && $notifiable instanceof User
            && config('services.push.driver', 'log') !== 'none'
            && $notifiable->deviceTokens()->exists();
    }

    public static function appFor(?string $url): ?string
    {
        $path = '/'.ltrim((string) parse_url((string) $url, PHP_URL_PATH), '/');

        return match (true) {
            str_starts_with($path, '/seller') => 'seller',
            str_starts_with($path, '/rider') => 'rider',
            str_starts_with($path, '/admin'), str_starts_with($path, '/logistics') => null,
            default => 'customer',
        };
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toPush')) {
            return;
        }

        $payload = $notification->toPush($notifiable);
        $app = self::appFor($payload['url'] ?? null);

        if (! $app) {
            return;
        }

        $devices = $notifiable->deviceTokens()->where('app', $app)->get();

        if ($devices->isEmpty()) {
            return;
        }

        $this->deliver($devices->all(), $app, $payload);
        $notifiable->deviceTokens()->whereKey($devices->modelKeys())->update(['last_used_at' => now()]);
    }

    /**
     * @param  list<DeviceToken>  $devices
     * @param  array<string, mixed>  $payload
     */
    protected function deliver(array $devices, string $app, array $payload): void
    {
        match (config('services.push.driver', 'log')) {
            'log' => Log::info('Push notification', [
                'app' => $app,
                'devices' => count($devices),
                'platforms' => array_values(array_unique(array_map(fn ($device) => $device->platform, $devices))),
                'title' => $payload['title'] ?? null,
                'body' => $payload['body'] ?? null,
                'url' => $payload['url'] ?? null,
            ]),
            default => Log::warning('Push driver "'.config('services.push.driver').'" is not installed; push skipped.'),
        };
    }
}
