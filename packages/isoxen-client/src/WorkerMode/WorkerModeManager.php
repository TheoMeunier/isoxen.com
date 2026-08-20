<?php

namespace Isoxen\Client\WorkerMode;

use Carbon\Carbon;
use Illuminate\Queue\Events\JobAttempted;
use Isoxen\Client\Facades\Meter;
use Isoxen\Client\Facades\OpenTelemetry;
use Isoxen\Client\Jobs\ExportTelemetry;

class WorkerModeManager
{
    protected int $lastMetricsExportTimestamp;

    public function __construct(
        protected bool $flushAfterEachIteration = false,
        protected int $metricsExportInterval = 60,
        /**
         * @var WorkerModeDetectorInterface[]
         */
        protected array $detectors = []
    ) {
        $this->lastMetricsExportTimestamp = Carbon::now()->getTimestamp();

        $this->initDetectors();
    }

    protected function initDetectors(): void
    {
        foreach ($this->detectors as $detector) {
            if ($detector->detect()) {
                $detector->onIterationEnded(fn ($event = null) => $this->handleIterationEnded($event));
            }
        }
    }

    /**
     * @param  object|null  $event  The framework event that ended this
     *                              iteration — a queue JobAttempted, an
     *                              Octane RequestTerminated, or null.
     */
    protected function handleIterationEnded(?object $event = null): void
    {
        // Never let the telemetry export job drive a flush.
        //
        // `JobAttempted` fires for *every* job a worker runs, including
        // this package's own ExportTelemetry. Without this guard, a worker
        // covering the telemetry queue is a perpetual motion machine:
        // processing one export job ends an iteration, which flushes, which
        // dispatches another export job, which ends an iteration... The
        // queue then grows forever with no application activity at all,
        // because each job's only product is its own successor.
        if ($event instanceof JobAttempted && $event->job->resolveName() === ExportTelemetry::class) {
            return;
        }

        if ($this->flushAfterEachIteration) {
            OpenTelemetry::flush();

            return;
        }

        $timestamp = Carbon::now()->getTimestamp();
        if ($timestamp - $this->lastMetricsExportTimestamp >= $this->metricsExportInterval) {
            Meter::collect();
            $this->lastMetricsExportTimestamp = $timestamp;
        }
    }
}
