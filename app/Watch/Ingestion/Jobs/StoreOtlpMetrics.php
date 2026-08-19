<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Jobs;

use App\Watch\Ingestion\Parsing\OtlpMetricsParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class StoreOtlpMetrics implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

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

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('otel_metrics')->insert($chunk);
        }
    }
}
