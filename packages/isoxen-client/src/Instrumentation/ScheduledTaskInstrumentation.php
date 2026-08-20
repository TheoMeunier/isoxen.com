<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\SpanType;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use WeakMap;

/**
 * Ported from isoxen's original bespoke sensor: the package this client was
 * forked from has no equivalent (its ConsoleInstrumentation only listens to
 * CommandStarting/CommandFinished, which the scheduler's individual task
 * runs don't go through).
 */
class ScheduledTaskInstrumentation implements Instrumentation
{
    /**
     * @var WeakMap<ScheduledEvent, array{0: SpanInterface, 1: ScopeInterface}>
     */
    protected WeakMap $running;

    public function register(array $options): void
    {
        $this->running = new WeakMap;

        app('events')->listen(ScheduledTaskStarting::class, [$this, 'starting']);
        app('events')->listen(ScheduledTaskFinished::class, [$this, 'finished']);
        app('events')->listen(ScheduledTaskFailed::class, [$this, 'failed']);
    }

    public function starting(ScheduledTaskStarting $event): void
    {
        $span = Tracer::newSpan($this->name($event->task))
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::ScheduledTask->value)
            ->setAttribute('isoxen.scheduled_task.expression', $event->task->expression)
            ->setAttribute('isoxen.scheduled_task.description', $event->task->description)
            ->start();

        $scope = $span->activate();

        $this->running[$event->task] = [$span, $scope];
    }

    public function finished(ScheduledTaskFinished $event): void
    {
        $trace = $this->running[$event->task] ?? null;

        if ($trace === null) {
            return;
        }

        [$span, $scope] = $trace;

        $span->setAttribute('isoxen.scheduled_task.runtime', $event->runtime);
        $span->setStatus(StatusCode::STATUS_OK);

        $scope->detach();
        $span->end();

        unset($this->running[$event->task]);
    }

    public function failed(ScheduledTaskFailed $event): void
    {
        $trace = $this->running[$event->task] ?? null;

        if ($trace === null) {
            return;
        }

        [$span, $scope] = $trace;

        $span->recordException($event->exception)->setStatus(StatusCode::STATUS_ERROR);

        $scope->detach();
        $span->end();

        unset($this->running[$event->task]);
    }

    private function name(ScheduledEvent $task): string
    {
        return $task->description ?: ($task->command ?: 'scheduled task');
    }
}
