<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Isoxen\Client\Facades\Meter;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\Instrumentation\Support\CallerLocator;
use Isoxen\Client\SpanType;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use OpenTelemetry\SemConv\Attributes\DbAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Metrics\DbMetrics;

class QueryInstrumentation implements Instrumentation
{
    use SpanTimeAdapter;

    /**
     * See config('isoxen.sensors.queries.capture_caller') -- attributes the
     * span to the application class/method that issued it. Never applied to
     * the duration metric below: a label per call site would give that
     * histogram a new series per unique file:line, which is exactly the
     * kind of unbounded cardinality metrics backends choke on.
     */
    private bool $captureCaller = true;

    public function register(array $options): void
    {
        $this->captureCaller = (bool) ($options['capture_caller'] ?? true);

        app('events')->listen(QueryExecuted::class, [$this, 'recordQuery']);
    }

    public function recordQuery(QueryExecuted $event): void
    {
        if (! Tracer::traceStarted()) {
            return;
        }

        $operationName = (string) Str::of($event->sql)
            ->before(' ')
            ->upper()
            ->when(
                value: fn (Stringable $operationName) => in_array($operationName, ['SELECT', 'INSERT', 'UPDATE', 'DELETE']),
                callback: fn (Stringable $operationName) => $operationName->toString(),
                default: fn () => ''
            );

        if ($operationName === '') {
            return;
        }

        $sharedAttributes = $this->sharedTraceMetricAttributes($event, $operationName);

        $this->recordTraceSpan($event, $operationName, $sharedAttributes);
        $this->recordOperationDurationMetric($event, $sharedAttributes);
    }

    /**
     * @param  array<non-empty-string, bool|int|float|string|array|null>  $attributes
     */
    protected function recordTraceSpan(QueryExecuted $event, string $operationName, array $attributes): void
    {
        $span = Tracer::newSpan($operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::Query->value)
            ->setStartTimestamp($this->getEventStartTimestampNs($event->time))
            ->setAttributes([...$attributes, ...$this->callerAttributes()])
            ->start();

        $span->end();
    }

    /**
     * @param  array<non-empty-string, bool|int|float|string|array|null>  $attributes
     */
    protected function recordOperationDurationMetric(QueryExecuted $event, array $attributes): void
    {
        $duration = (Clock::getDefault()->now() - $this->getEventStartTimestampNs($event->time)) / ClockInterface::NANOS_PER_SECOND;

        // @see https://opentelemetry.io/docs/specs/semconv/db/database-metrics/#metric-dbclientoperationduration
        Meter::histogram(
            name: DbMetrics::DB_CLIENT_OPERATION_DURATION,
            unit: 's',
            description: 'Duration of database client operations.',
            advisory: [
                'ExplicitBucketBoundaries' => [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1.0, 5.0, 10.0],
            ]
        )
            ->record($duration, $attributes);
    }

    /**
     * @return array<non-empty-string, bool|int|float|string|array|null>
     */
    protected function sharedTraceMetricAttributes(QueryExecuted $event, string $operationName): array
    {
        return [
            DbAttributes::DB_SYSTEM_NAME     => $event->connection->getDriverName(),
            DbAttributes::DB_NAMESPACE       => $event->connection->getDatabaseName(),
            DbAttributes::DB_OPERATION_NAME  => $operationName,
            DbAttributes::DB_QUERY_TEXT      => Str::limit($event->sql, 500),
            ServerAttributes::SERVER_ADDRESS => $event->connection->getConfig('host'),
            ServerAttributes::SERVER_PORT    => $event->connection->getConfig('port'),
        ];
    }

    /**
     * The application class/method that issued this query, plus where it
     * did so -- span-only (see $captureCaller's docblock). Silently empty
     * when disabled or when {@see CallerLocator} can't find a non-vendor
     * frame (a query issued from vendor code itself, e.g. a package's own
     * migration).
     *
     * @return array<non-empty-string, bool|int|float|string|array|null>
     */
    protected function callerAttributes(): array
    {
        if (! $this->captureCaller) {
            return [];
        }

        $caller = CallerLocator::locate();

        if ($caller === null) {
            return [];
        }

        $functionName = $caller['class'] !== null
            ? $caller['class'].($caller['type'] ?? '::').($caller['function'] ?? '')
            : $caller['function'];

        return array_filter([
            CodeAttributes::CODE_FILE_PATH     => $caller['file'],
            CodeAttributes::CODE_LINE_NUMBER   => $caller['line'],
            CodeAttributes::CODE_FUNCTION_NAME => $functionName,
        ], fn (mixed $value): bool => $value !== null);
    }
}
