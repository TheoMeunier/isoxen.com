<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Watch\Ingestion\Support\ObservabilityCategories;
use App\Watch\Projects\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a project's owner when its 5XX rate over the last hour crosses the
 * threshold configured on the project. Fired from `alerts:evaluate`.
 */
class ErrorRateAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Project $project,
        private readonly float $errorRate,
        private readonly int $requests,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rate = number_format($this->errorRate, 1);

        return (new MailMessage)
            ->error()
            ->subject("[{$this->project->name}] {$rate}% of requests are failing")
            ->line("{$rate}% of the last hour's {$this->requests} requests returned a 5XX response, above the {$this->project->alert_error_rate_threshold}% threshold set on this project.")
            ->action('Open requests', route('projects.show', [
                'project'  => $this->project,
                'category' => ObservabilityCategories::default(),
            ]));
    }
}
