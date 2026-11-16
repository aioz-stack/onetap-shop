<?php

namespace App\Notifications;

use App\Notifications\Concerns\PushesToDevices;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A marketplace event for one person: stored in their in-app inbox, and optionally emailed.
 * The `type` (e.g. "refund.requested") lets clients filter or group alerts.
 */
class Alert extends Notification
{
    use PushesToDevices;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public ?string $url = null,
        public bool $mail = false,
        public array $meta = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        return $this->withPush($this->mail && filled($notifiable->email ?? null) ? ['database', 'mail'] : ['database'], $notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->title)->line($this->body);

        return $this->url ? $message->action('Open in Onetap', url($this->url)) : $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => $this->type, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url] + $this->meta;
    }
}
