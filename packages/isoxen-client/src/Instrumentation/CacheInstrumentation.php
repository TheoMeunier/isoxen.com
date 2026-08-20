<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\SpanType;

/**
 * Unlike the package this was forked from — which annotates whatever span
 * is currently active with a cache "event" — this records each cache
 * operation as its own standalone span tagged `isoxen.type=cache`. isoxen's
 * ingestion reads the Cache tab from `otel_spans` filtered by that type, so
 * an annotation-only approach would leave that tab always empty.
 *
 * Cache events carry no duration, so each one is recorded as a point in
 * time rather than an interval: what matters is the hit/miss ratio and
 * which keys are involved, not how long the driver took.
 */
class CacheInstrumentation implements Instrumentation
{
    public function register(array $options): void
    {
        app('events')->listen(CacheHit::class, [$this, 'recordCacheHit']);
        app('events')->listen(CacheMissed::class, [$this, 'recordCacheMiss']);
        app('events')->listen(KeyWritten::class, [$this, 'recordCacheSet']);
        app('events')->listen(KeyForgotten::class, [$this, 'recordCacheForget']);
    }

    public function recordCacheHit(CacheHit $event): void
    {
        $this->record('hit', $event->key, $event->storeName);
    }

    public function recordCacheMiss(CacheMissed $event): void
    {
        $this->record('miss', $event->key, $event->storeName);
    }

    public function recordCacheSet(KeyWritten $event): void
    {
        $this->record('write', $event->key, $event->storeName);
    }

    public function recordCacheForget(KeyForgotten $event): void
    {
        $this->record('forget', $event->key, $event->storeName);
    }

    private function record(string $operation, string $key, ?string $store): void
    {
        // Only within traced work. A cache-heavy background process with no
        // active trace would otherwise emit a flood of orphan spans that
        // belong to nothing — and, when the application is its own
        // collector, feed the ingestion loop that `sensors.jobs.excluded`
        // exists to break.
        if (! Tracer::traceStarted()) {
            return;
        }

        Tracer::newSpan("cache {$operation}")
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::Cache->value)
            ->setAttribute('cache.operation', $operation)
            ->setAttribute('cache.key', $key)
            ->setAttribute('cache.store', $store)
            ->start()
            ->end();
    }
}
