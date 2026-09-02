<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Data;

final readonly class QueryDetail
{
    public function __construct(
        public string $query,
        public string $fingerprint,
        public ?string $system,
        public ?string $namespace,
        public ?string $host,
        public ?int $port,
        public int $calls,
        public float $total_ms,
        public float $avg_ms,
        public float $p95_ms,
        public ?string $first_seen,
        public ?string $last_seen,
        public array $timeline,
        public array $occurrences,
    ) {}
}
