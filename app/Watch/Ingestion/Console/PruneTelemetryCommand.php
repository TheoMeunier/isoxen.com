<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Console;

use App\Watch\Ingestion\Models\OtelLog;
use App\Watch\Ingestion\Models\OtelMetric;
use App\Watch\Ingestion\Models\OtelRecord;
use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Projects\Models\Project;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description("Delete telemetry older than each project's retention window.")]
#[Signature('isoxen:prune-telemetry')]
class PruneTelemetryCommand extends Command
{
    /**
     * @var array<string, class-string<OtelRecord>>
     */
    private const array MODELS = [
        'traces'  => OtelSpan::class,
        'metrics' => OtelMetric::class,
        'logs'    => OtelLog::class,
    ];

    public function handle(): int
    {
        Project::query()->each(function (Project $project): void {
            $cutoff  = now()->subDays($project->retention_days);
            $deleted = 0;

            // ponytail: one plain DELETE per signal, riding the (project_id, time)
            // index. If a table ever grows big enough for that to lock too long,
            // partition the otel_* tables by time and drop partitions instead.
            foreach (self::MODELS as $model) {
                $deleted += $model::query()
                    ->where('project_id', $project->id)
                    ->where('time', '<', $cutoff)
                    ->delete();
            }

            $this->components->twoColumnDetail(
                $project->name,
                "{$deleted} rows older than {$project->retention_days} days",
            );
        });

        return self::SUCCESS;
    }
}
