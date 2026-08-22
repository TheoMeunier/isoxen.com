<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Log\Events\MessageLogged;
use Isoxen\Client\Support\Suppression;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Logs\Severity;

/**
 * Forwards the application's log entries as OTEL log records.
 *
 * Listening to `MessageLogged` rather than pushing a Monolog handler (the
 * package this client was forked from offers one, kept available as
 * `logging.channels.otlp` but not used by default) catches whichever
 * channel the application logs through without extra config.
 */
class LogInstrumentation implements Instrumentation
{
    /**
     * Guards against a log written while exporting logs producing another
     * log record, and so on. Static because the reentrancy it protects
     * against happens within one process, not one request.
     */
    private static bool $emitting = false;

    public function register(array $options): void
    {
        app('events')->listen(MessageLogged::class, [$this, 'record']);
    }

    public function record(MessageLogged $event): void
    {
        // Anything logged while shipping telemetry — a failed export, a
        // connection error — must not become a log record to ship.
        if (self::$emitting || Suppression::active()) {
            return;
        }

        self::$emitting = true;

        try {
            $record = (new LogRecord($event->message))
                ->setSeverityNumber($this->severity($event->level))
                ->setSeverityText(strtoupper($event->level))
                ->setAttributes($this->attributes($event->context));

            // No trace id is set by hand: the log record picks up whatever
            // span is active, which is what correlates a log line with the
            // request that produced it.
            Globals::loggerProvider()
                ->getLogger('isoxen-laravel-client')
                ->emit($record);
        } finally {
            self::$emitting = false;
        }
    }

    private function severity(string $level): Severity
    {
        return match (strtolower($level)) {
            'debug'     => Severity::DEBUG,
            'info'      => Severity::INFO,
            'notice'    => Severity::INFO2,
            'warning'   => Severity::WARN,
            'error'     => Severity::ERROR,
            'critical'  => Severity::ERROR2,
            'alert'     => Severity::ERROR3,
            'emergency' => Severity::FATAL,
            default     => Severity::INFO,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function attributes(array $context): array
    {
        $attributes = [];

        foreach ($context as $key => $value) {
            // Attribute values have to be scalars or lists of scalars;
            // anything richer is rendered rather than dropped, so the
            // information survives even if its shape doesn't.
            $attributes["log.context.{$key}"] = match (true) {
                is_scalar($value), $value === null => $value,
                $value instanceof \Throwable       => $value->getMessage(),
                default                            => json_encode($value),
            };
        }

        return $attributes;
    }
}
