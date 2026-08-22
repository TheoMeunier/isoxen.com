<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Console;

use App\Watch\Ingestion\Models\OtelLog;
use App\Watch\Ingestion\Models\OtelMetric;
use App\Watch\Ingestion\Models\OtelRecord;
use App\Watch\Ingestion\Models\OtelSpan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[\Illuminate\Console\Attributes\Description('Show what has been ingested, per signal.')]
#[\Illuminate\Console\Attributes\Signature('isoxen:ingestion-status')]
class IngestionStatusCommand extends Command
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
        $this->components->info('Stored per signal');

        foreach (self::MODELS as $signal => $model) {
            $this->reportModel($signal, $model);
        }

        $this->reportFailedJobs();

        return self::SUCCESS;
    }

    /**
     * @param  class-string<OtelRecord>  $model
     */
    private function reportModel(string $signal, string $model): void
    {
        $table = (new $model)->getTable();

        if (! Schema::hasTable($table)) {
            $this->components->twoColumnDetail($signal, '<fg=red>table '.$table.' is missing — run migrations</>');

            return;
        }

        $total = $model::query()->count();

        if ($total === 0) {
            $this->components->twoColumnDetail($signal, '<fg=yellow>empty</>');

            return;
        }

        $latest = $model::query()->max('time');
        $age    = $latest === null ? null : \Illuminate\Support\Facades\Date::parse($latest)->diffForHumans();

        $this->components->twoColumnDetail(
            $signal,
            "<fg=green>{$total}</> rows, most recent ".($age ?? 'unknown'),
        );
    }

    private function reportFailedJobs(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            return;
        }

        $this->components->info('Failed jobs');

        $failed = DB::table('failed_jobs')->count();

        $this->components->twoColumnDetail(
            'failed_jobs',
            $failed > 0
                ? "<fg=red>{$failed}</> — inspect with `php artisan queue:failed`"
                : '<fg=green>0</>',
        );
    }
}
