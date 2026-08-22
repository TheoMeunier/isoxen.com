<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Jobs;

use App\Watch\Ingestion\Data\SpanRow;
use App\Watch\Ingestion\Parsing\OtlpSpansParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreOtlpSpans implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  Decoded OTLP/JSON ExportTraceServiceRequest body.
     */
    public function __construct(
        public readonly int $projectId,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $rows = OtlpSpansParser::toRows($this->projectId, $this->payload);

        $rows->chunk(500)->each(function (Collection $chunk): void {
            DB::table('otel_spans')->insert(
                $chunk->map(fn (SpanRow $row): array => $row->toDatabaseRow())->all(),
            );
        });
    }
}
