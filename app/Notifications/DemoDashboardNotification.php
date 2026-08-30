<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A throwaway notification with no real content, meant to be fired a few
 * times from `php artisan demo:traffic` so the Notifications tab has
 * something to show while testing the dashboard -- not used anywhere in
 * the real app flow. Implementing ShouldQueue is what makes ->notify()
 * queue it instead of sending synchronously.
 */
class DemoDashboardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $sequence = 1) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Demo notification #{$this->sequence}")
            ->line("Demo notification #{$this->sequence}, sent to populate the dashboard's Notifications tab.");
    }
}
