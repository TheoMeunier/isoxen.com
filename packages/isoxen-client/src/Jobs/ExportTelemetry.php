<?php

declare(strict_types=1);

namespace Isoxen\Client\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Isoxen\Client\Support\Diagnostics;
use Isoxen\Client\Support\Suppression;
use Throwable;

/**
 * Ships one already-serialized OTLP payload to isoxen.
 *
 * The payload arrives fully formed from {@see \Isoxen\Client\Transport\QueuedTransport}
 * — this job never inspects or rebuilds it, it only carries it across the
 * process boundary and makes the HTTP call the web request didn't want to
 * wait for.
 */
class ExportTelemetry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Telemetry has a shelf life: a trace that arrives ten minutes late is
     * of little use, and retrying forever would let a backlog of stale
     * payloads crowd out fresh ones.
     */
    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param  string  $signal  'traces', 'metrics' or 'logs'.
     */
    public function __construct(
        public readonly string $signal,
        public readonly string $payload,
    ) {}

    /**
     * This method must never throw, and that is the whole point of it.
     *
     * A throwing telemetry job is reported by Laravel's exception handler
     * and logged — and both of those are instrumented, so each failure
     * produces an exception span *and* a log record, which are exported as
     * two new jobs, which fail, which produce four... The queue then
     * explodes precisely when the endpoint is unreachable. So failures are
     * swallowed here, retried by hand while attempts remain, and reported
     * through {@see Diagnostics}, which nothing here listens to.
     */
    public function handle(): void
    {
        $endpoint = rtrim((string) config('isoxen.endpoint'), '/');
        $token    = (string) config('isoxen.token');

        if ($endpoint === '' || $token === '') {
            // Saying so out loud, because discarding telemetry in silence
            // is indistinguishable from a pipeline that works and has
            // nothing to report — and that costs hours to tell apart.
            Diagnostics::write('ISOXEN_ENDPOINT or ISOXEN_TOKEN is not set — dropping a '.$this->signal.' batch. Run `php artisan isoxen:doctor`.');

            return;
        }

        try {
            $response = Suppression::run(fn () => Http::withToken($token)
                ->withBody($this->payload, 'application/json')
                ->timeout(10)
                ->post("{$endpoint}/v1/{$this->signal}"));
        } catch (Throwable $e) {
            $this->retryOrDrop('could not connect — '.$e->getMessage());

            return;
        }

        if ($response->successful()) {
            Diagnostics::write(sprintf(
                '%s batch accepted (HTTP %d, %d bytes)',
                $this->signal,
                $response->status(),
                strlen($this->payload),
            ));

            return;
        }

        // The response body is the most valuable thing in this whole class
        // when something is wrong: it carries the server's own explanation
        // ("Invalid ingestion token", "Missing or invalid resourceSpans").
        // Throwing it away and reporting only a status code is what turns a
        // one-line fix into an afternoon.
        $this->retryOrDrop(sprintf(
            'HTTP %d — %s',
            $response->status(),
            trim(Str::limit((string) $response->body(), 300)) ?: '(empty response body)',
        ));
    }

    /**
     * Every failed attempt is reported, not only the last one.
     *
     * A released job is indistinguishable from a successful one in the
     * worker's output — both print DONE — so without this a broken pipeline
     * looks exactly like a working one right up until the final attempt.
     */
    private function retryOrDrop(string $reason): void
    {
        $attempt = $this->attempts();

        // Released rather than rethrown: releasing re-queues the payload
        // without ever involving the failed-job machinery, so a broken
        // endpoint costs retries and nothing else.
        if ($attempt < $this->tries) {
            Diagnostics::write(sprintf(
                '%s batch failed (attempt %d/%d, retrying in %ds) — %s',
                $this->signal,
                $attempt,
                $this->tries,
                $this->backoff,
                $reason,
            ));

            $this->release($this->backoff);

            return;
        }

        Diagnostics::write(sprintf(
            'gave up on a %s batch after %d attempts — %s',
            $this->signal,
            $attempt,
            $reason,
        ));
    }
}
