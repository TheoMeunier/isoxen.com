<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data;

final readonly class QueryOccurrence
{
    public function __construct(
        public string $time,
        public float $duration_ms,
        public ?string $trace_id,
        public ?string $span_id,
        public ?string $caller,
        public ?string $caller_file,
        public ?int $caller_line,
        public ?string $called_from,
        public ?string $called_from_type,
    ) {}
}
