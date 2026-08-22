<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IngestionStatusCommand extends Command
{
    protected $signature = 'isoxen:ingestion-status';

    protected $description = 'Show what has been ingested, per signal.';

    private const TABLES = [
        'traces' => 'otel_spans',
        'metrics' => 'otel_metrics',
        'logs' => 'otel_logs',
    ];

    public function handle(): int
    {
        $this->components->info('Stored per signal');

        foreach (self::TABLES as $signal => $table) {
            $this->reportTable($signal, $table);
        }

        $this->reportFailedJobs();

        return self::SUCCESS;
    }

    private function reportTable(string $signal, string $table): void
    {
        if (!Schema::hasTable($table)) {
            $this->components->twoColumnDetail($signal, '<fg=red>table ' . $table . ' is missing — run migrations</>');

            return;
        }

        $total = DB::table($table)->count();

        if ($total === 0) {
            $this->components->twoColumnDetail($signal, '<fg=yellow>empty</>');

            return;
        }

        $latest = DB::table($table)->max('time');
        $age = $latest === null ? null : Carbon::parse($latest)->diffForHumans();

        $this->components->twoColumnDetail(
            $signal,
            "<fg=green>{$total}</> rows, most recent " . ($age ?? 'unknown'),
        );
    }

    private function reportFailedJobs(): void
    {
        if (!Schema::hasTable('failed_jobs')) {
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
