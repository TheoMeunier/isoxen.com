<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\DemoDashboardMail;
use App\Notifications\DemoDashboardNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Dispatches a handful of throwaway mails and notifications through the
 * queue, purely so the isoxen dashboard's Mail/Notifications tabs have
 * something fresh to show while testing -- mirrors how you've been
 * triggering test exceptions by hand. Requires a queue worker actually
 * running (php artisan queue:work) to see them land, since both are
 * dispatched with ->queue()/ShouldQueue rather than sent synchronously.
 */
#[Signature('demo:traffic {--mails=5} {--notifications=5} {--email=test@example.com}')]
#[Description('Queue a few demo mails and notifications to populate the dashboard')]
class GenerateDemoTraffic extends Command
{
    public function handle(): int
    {
        $email         = (string) $this->option('email');
        $mailCount     = (int) $this->option('mails');
        $notifyCount   = (int) $this->option('notifications');

        for ($i = 1; $i <= $mailCount; $i++) {
            Mail::to($email)->queue(new DemoDashboardMail($i));
        }

        for ($i = 1; $i <= $notifyCount; $i++) {
            Notification::route('mail', $email)->notify(new DemoDashboardNotification($i));
        }

        $this->info("Queued {$mailCount} mail(s) and {$notifyCount} notification(s) for {$email}.");
        $this->line('Make sure a queue worker is running (php artisan queue:work) so they actually get processed.');

        return self::SUCCESS;
    }
}
