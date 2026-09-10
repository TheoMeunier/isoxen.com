<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\ErrorRateAlert;
use App\Watch\Ingestion\Queries\RequestStatusBreakdownQuery;
use App\Watch\Projects\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * Evaluates each project's error-rate alert and mails its owner when the
 * threshold is crossed. Scheduled every five minutes -- see routes/console.php.
 */
class EvaluateAlerts extends Command
{
    /**
     * Projects quieter than this in the last hour are skipped: on tiny volumes
     * a single failure is 100% and the alert says nothing useful.
     */
    private const MINIMUM_REQUESTS = 20;

    protected $signature = 'alerts:evaluate';

    protected $description = "Notify project owners whose 5XX rate crossed their project's threshold";

    public function handle(RequestStatusBreakdownQuery $requestStatusBreakdownQuery): int
    {
        $cooldownStart = Date::now()->subHour();

        Project::query()
            ->whereNotNull('alert_error_rate_threshold')
            ->where(function ($query) use ($cooldownStart): void {
                $query->whereNull('alert_notified_at')->orWhere('alert_notified_at', '<', $cooldownStart);
            })
            ->with('user')
            ->each(function (Project $project) use ($requestStatusBreakdownQuery): void {
                $breakdown = $requestStatusBreakdownQuery->execute($project, hours: 1);
                $requests  = array_sum($breakdown);

                if ($requests < self::MINIMUM_REQUESTS) {
                    return;
                }

                $errorRate = $breakdown['server_error'] / $requests * 100;

                if ($errorRate < $project->alert_error_rate_threshold) {
                    return;
                }

                $project->user->notify(new ErrorRateAlert($project, $errorRate, $requests));

                // ponytail: no "resolved" notification and no state machine --
                // just a one-hour cooldown. Revisit if the noise becomes a problem.
                $project->forceFill(['alert_notified_at' => Date::now()])->save();

                $this->line("Alerted {$project->name}: {$errorRate}% errors over {$requests} requests.");
            });

        return self::SUCCESS;
    }
}
