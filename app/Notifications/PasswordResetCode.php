<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The 6-digit password reset code for the mobile apps. Always emailed: it's transactional,
 * so the "email users" setting doesn't apply.
 */
class PasswordResetCode extends Notification
{
    public function __construct(public string $code, public int $minutes) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Onetap password reset code')
            ->line('Use this code in the Onetap app to set a new password:')
            ->line("**{$this->code}**")
            ->line("It expires in {$this->minutes} minutes. If you didn't ask to reset your password, you can ignore this email.");
    }
}
