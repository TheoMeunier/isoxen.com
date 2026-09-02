<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data;

final readonly class QueryStat
{
    public function __construct(
        public string $query,
        public string $fingerprint,
        public ?string $system,
        public int $calls,
        public float $total_ms,
        public float $avg_ms,
        public float $p95_ms,
    ) {}
}
