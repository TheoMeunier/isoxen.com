<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\Instrumentation\Support\InstrumentationUtilities;
use Isoxen\Client\Jobs\ExportTelemetry;
use Isoxen\Client\SpanType;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Incubating\Attributes\MessagingIncubatingAttributes;
use Throwable;

class QueueInstrumentation implements Instrumentation
{
    use InstrumentationUtilities;
    use SpanTimeAdapter;

    /**
     * @var array<string,SpanInterface>
     */
    protected array $activeSpans = [];

    /**
     * Job classes never worth tracing, as `Str::is()` patterns.
     *
     * This matters more than it looks. When the monitored application is
     * itself an OTLP collector — isoxen.com monitoring isoxen.com being the
     * obvious case — tracing the jobs that *store* incoming telemetry makes
     * every ingested batch produce new spans, which are exported as new
     * jobs, which are ingested, which produce more. The branching factor is
     * one export job per signal, so it grows exponentially and never
     * settles. Excluding the ingestion jobs cuts the whole branch: the
     * query/redis/cache instrumentation all bail out when no trace is
     * active, so nothing downstream of an untraced job is recorded either.
     *
     * @var string[]
     */
    protected array $excluded = [];

    /**
     * @param  array{excluded?: string[]}  $options
     */
    public function register(array $options): void
    {
        $this->excluded = array_values(array_unique(array_merge(
            // Always excluded: the job that ships telemetry must never
            // produce telemetry about shipping telemetry.
            [ExportTelemetry::class],
            $options['excluded'] ?? [],
        )));

        $this->recordJobQueueing();
        $this->recordJobProcessing();
    }

    /**
     * @param  string|null  $jobName  Fully qualified job class name.
     */
    protected function isExcluded(?string $jobName): bool
    {
        return $jobName !== null && Str::is($this->excluded, $jobName);
    }

    protected function recordJobQueueing(): void
    {
        $this->callAfterResolving('queue', $this->registerQueueInterceptor(...));

        app('events')->listen(JobQueued::class, function (JobQueued $event) {
            $uuid = $event->payload()['uuid'] ?? null;

            if (! is_string($uuid)) {
                return;
            }

            $span = $this->activeSpans[$uuid] ?? null;

            $span?->end();

            unset($this->activeSpans[$uuid]);
        });
    }

    protected function registerQueueInterceptor(QueueManager $queue): void
    {
        try {
            $queue->createPayloadUsing(function (string $connection, ?string $queue, array $payload) {
                $jobName = Arr::get($payload, 'displayName', 'unknown');

                if ($this->isExcluded($jobName)) {
                    return $payload;
                }

                if (! Tracer::traceStarted()) {
                    return $payload;
                }

                $uuid = $payload['uuid'];

                if (! is_string($uuid)) {
                    return $payload;
                }
                $queueName = Str::after($queue ?? 'default', 'queues:');
                /** @var int|null $payloadSize */
                $payloadSize = rescue(fn () => strlen(\Safe\json_encode($payload)), report: false);

                $span = Tracer::newSpan(sprintf('send %s', $queueName))
                    ->setSpanKind(SpanKind::KIND_PRODUCER)
                    ->setAttribute(SpanType::ATTRIBUTE, SpanType::Job->value)
                    ->setAttribute(MessagingIncubatingAttributes::MESSAGING_SYSTEM, $this->connectionDriver($connection))
                    ->setAttribute(MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE, 'send')
                    ->setAttribute(MessagingIncubatingAttributes::MESSAGING_MESSAGE_ID, $uuid)
                    ->setAttribute(MessagingIncubatingAttributes::MESSAGING_DESTINATION_NAME, $queueName)
                    ->setAttribute(MessagingIncubatingAttributes::MESSAGING_MESSAGE_ENVELOPE_SIZE, $payloadSize)
                    ->setAttribute('messaging.message.job_name', $jobName)
                    ->setAttribute('messaging.message.attempts', $payload['attempts'] ?? 0)
                    ->setAttribute('messaging.message.max_exceptions', $payload['maxExceptions'] ?? null)
                    ->setAttribute('messaging.message.max_tries', $payload['maxTries'] ?? null)
                    ->setAttribute('messaging.message.retry_until', $payload['retryUntil'] ?? null)
                    ->setAttribute('messaging.message.timeout', $payload['timeout'] ?? null)
                    ->start();

                $context = $span->storeInContext(Tracer::currentContext());

                $this->activeSpans[$uuid] = $span;

                return Tracer::propagationHeaders($context);
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function recordJobProcessing(): void
    {
        app('events')->listen(JobProcessing::class, function (JobProcessing $event) {
            if ($this->isExcluded($event->job->resolveName())) {
                return;
            }

            // The sync queue driver never dispatches JobQueued, so the producer span would otherwise leak and never be exported.
            // Close any still-open producer span for this job before starting the consumer span.
            // For async drivers JobQueued has already ended/removed it, so this is a no-op.
            $producerUuid = $event->job->uuid();
            if ($producerUuid !== null && isset($this->activeSpans[$producerUuid])) {
                $this->activeSpans[$producerUuid]->end();
                unset($this->activeSpans[$producerUuid]);
            }

            $context = Tracer::extractContextFromPropagationHeaders($event->job->payload());

            $span = Tracer::newSpan(sprintf('process %s', $event->job->getQueue()))
                ->setSpanKind(SpanKind::KIND_CONSUMER)
                ->setParent($context)
                ->setAttribute(SpanType::ATTRIBUTE, SpanType::Job->value)
                ->setAttribute(MessagingIncubatingAttributes::MESSAGING_SYSTEM, $this->connectionDriver($event->connectionName))
                ->setAttribute(MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE, 'process')
                ->setAttribute(MessagingIncubatingAttributes::MESSAGING_MESSAGE_ID, $event->job->uuid())
                ->setAttribute(MessagingIncubatingAttributes::MESSAGING_DESTINATION_NAME, $event->job->getQueue())
                ->setAttribute(MessagingIncubatingAttributes::MESSAGING_MESSAGE_ENVELOPE_SIZE, strlen($event->job->getRawBody()))
                ->setAttribute('messaging.message.job_name', $event->job->resolveName())
                ->setAttribute('messaging.message.attempts', $event->job->attempts())
                ->setAttribute('messaging.message.max_exceptions', $event->job->maxExceptions())
                ->setAttribute('messaging.message.max_tries', $event->job->maxTries())
                ->setAttribute('messaging.message.retry_until', $event->job->retryUntil())
                ->setAttribute('messaging.message.timeout', $event->job->timeout())
                ->start();

            $span->activate();

            Tracer::updateLogContext();
        });

        app('events')->listen(JobProcessed::class, function (JobProcessed $event) {
            if ($this->isExcluded($event->job->resolveName())) {
                return;
            }

            $this->finishActiveJobSpan();
        });

        app('events')->listen(JobFailed::class, function (JobFailed $event) {
            if ($this->isExcluded($event->job->resolveName())) {
                return;
            }

            $this->finishActiveJobSpan($event->exception);
        });

        app('events')->listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event) {
            if ($event->job->hasFailed() || $this->isExcluded($event->job->resolveName())) {
                return;
            }

            $this->finishActiveJobSpan($event->exception);
        });
    }

    protected function connectionDriver(string $connection): string
    {
        return config(sprintf('queue.connections.%s.driver', $connection), 'unknown');
    }

    protected function finishActiveJobSpan(?Throwable $exception = null): void
    {
        $scope = Tracer::activeScope();
        $span  = Tracer::activeSpan();

        if ($exception !== null) {
            $span->recordException($exception)
                ->setStatus(StatusCode::STATUS_ERROR);
        }

        $scope?->detach();
        $span->end();
    }
}
