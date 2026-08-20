<?php

declare(strict_types=1);

namespace Isoxen\Client\Transport;

use Illuminate\Support\Facades\Bus;
use Isoxen\Client\Jobs\ExportTelemetry;
use Isoxen\Client\Support\Diagnostics;
use Isoxen\Client\Support\Suppression;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use Throwable;

/**
 * Hands the serialized payload to a queued job instead of sending it over
 * HTTP from the process that produced it.
 *
 * OpenTelemetry splits exporting into two halves: serializing into an OTLP
 * payload, and shipping that payload somewhere. Only the second half is
 * swapped here, so the SDK's own serialization is reused untouched and what
 * reaches isoxen is ordinary, standard OTLP.
 *
 * The point is what the calling process pays. Pushing a job onto Redis
 * costs about a millisecond; the HTTP round trip it replaces costs tens of
 * them, and under Octane the worker is blocked for all of it.
 *
 * One instance exists per signal, because OTLP gives traces, metrics and
 * logs each their own endpoint.
 */
final class QueuedTransport implements TransportInterface
{
    /**
     * How many payloads this process has handed to the queue, per signal.
     *
     * Exists so `isoxen:doctor` can tell "the pipeline produced nothing" —
     * a sensor that never fired, or a provider that turned out to be a
     * no-op — apart from "it produced something that got lost downstream".
     * Without it the two look identical from the outside: an empty table.
     *
     * @var array<string, int>
     */
    public static array $dispatched = [];

    /**
     * @param  string  $signal  'traces', 'metrics' or 'logs' — the OTLP path
     *                          this transport's payloads belong to.
     */
    public function __construct(
        private readonly string $signal,
        private readonly ?string $connection,
        private readonly ?string $queue,
    ) {}

    public function contentType(): string
    {
        // JSON, because that's what isoxen's ingestion endpoints accept
        // today; protobuf support there is a separate task.
        return ContentTypes::JSON;
    }

    public function send(string $payload, ?CancellationInterface $cancellation = null): FutureInterface
    {
        // Sealed means this process is currently serving an ingestion
        // request. Shipping now would make handling one batch produce
        // another — see Suppression::seal().
        if (Suppression::sealed() || $this->servingIgnoredPath()) {
            return new CompletedFuture(null);
        }

        try {
            $job = (new ExportTelemetry($this->signal, $payload))
                ->onConnection($this->connection)
                ->onQueue($this->queue);

            // Dispatched through the bus rather than ExportTelemetry::dispatch(),
            // which returns a PendingDispatch that only really dispatches
            // when it is destroyed — and an exception thrown from a
            // destructor surfaces somewhere unrelated, long after the cause.
            //
            // Suppressed because the dispatch itself is instrumented work:
            // pushing to Redis fires a CommandExecuted event, and the point
            // of this transport is that shipping telemetry produces none.
            Suppression::run(fn () => Bus::dispatch($job));

            self::$dispatched[$this->signal] = (self::$dispatched[$this->signal] ?? 0) + 1;
        } catch (Throwable $e) {
            // Telemetry must never take the application down with it. An
            // unreachable queue means losing this batch, not failing the
            // request or the command that produced it.
            //
            // Reported through Diagnostics rather than the application's
            // logger on purpose: the logger is itself instrumented, so
            // logging an export failure is a fine way to cause another one.
            Diagnostics::write('dropped a '.$this->signal.' batch — '.$e->getMessage());
        }

        // Either the queue has it or it's gone; from the SDK's point of
        // view this export is finished. Whether isoxen accepted it is the
        // job's problem, and its retries are how that's handled.
        return new CompletedFuture(null);
    }

    /**
     * Second line of defence behind {@see Suppression::seal()}.
     *
     * The seal is set by the request-tracing middleware, which only exists
     * when the `requests` sensor is on. Turning that sensor off must not
     * quietly re-open the ingestion feedback loop, so the ignored paths are
     * checked here too — directly, and independently of any sensor.
     */
    private function servingIgnoredPath(): bool
    {
        try {
            if (! function_exists('app') || app()->runningInConsole()) {
                return false;
            }

            $ignored = (array) config('isoxen.ignore_paths', []);

            return $ignored !== [] && app('request')->is(...$ignored);
        } catch (Throwable) {
            return false;
        }
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return true;
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return true;
    }
}
