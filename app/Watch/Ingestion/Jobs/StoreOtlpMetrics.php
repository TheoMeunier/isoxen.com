<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Jobs;

use App\Watch\Ingestion\Data\MetricRow;
use App\Watch\Ingestion\Parsing\OtlpMetricsParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreOtlpMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  Decoded OTLP/JSON ExportMetricsServiceRequest body.
     */
    public function __construct(
        public readonly int $projectId,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $rows = OtlpMetricsParser::toRows($this->projectId, $this->payload);

        $rows->chunk(500)->each(function (Collection $chunk): void {
            DB::table('otel_metrics')->insert(
                $chunk->map(fn (MetricRow $row): array => $row->toDatabaseRow())->all(),
            );
        });
    }
}
