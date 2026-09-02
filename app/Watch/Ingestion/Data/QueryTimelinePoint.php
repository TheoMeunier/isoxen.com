<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data;

final readonly class QueryTimelinePoint
{
    public function __construct(
        public string $at,
        public int $calls,
        public ?float $avg_ms,
        public ?float $p95_ms,
    ) {}
}
