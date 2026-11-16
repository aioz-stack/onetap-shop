<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\PushChannel;

/**
 * Adds the mobile push channel to an in-app (database) notification. The push reuses the
 * notification's title, body and link from toArray(), so the phone shows the same alert.
 */
trait PushesToDevices
{
    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    protected function withPush(array $channels, object $notifiable): array
    {
        return in_array('database', $channels, true) && PushChannel::wants($notifiable, $this)
            ? [...$channels, PushChannel::class]
            : $channels;
    }

    /**
     * @return array{title: string|null, body: string|null, url: string|null, data: array<string, mixed>}
     */
    public function toPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'url' => $data['url'] ?? null,
            'data' => array_filter(['type' => $data['type'] ?? null, 'url' => $data['url'] ?? null] + array_diff_key($data, array_flip(['title', 'body']))),
        ];
    }
}
