<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Collection;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\SpanType;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;
use WeakMap;

/**
 * Unlike the package this was forked from — which only traces commands
 * explicitly listed in config (an opt-in whitelist, empty by default) —
 * this records every command by default and exposes an opt-out `excluded`
 * list instead. That matches how the rest of isoxen's sensors work, and a
 * short exclude list (the scheduler's own run, queue workers, Horizon) is
 * simpler to keep in sync than an allowlist of every command worth seeing.
 */
class ConsoleInstrumentation implements Instrumentation
{
    /**
     * @var WeakMap<InputInterface, array{0: SpanInterface, 1: ScopeInterface}>
     */
    protected WeakMap $startedCommands;

    /**
     * @var string[]
     */
    protected array $excluded = [];

    /**
     * @var string[]
     */
    protected array $excludedWildcards = [];

    /**
     * @param  array{
     *     excluded?: string[]
     * }  $options
     */
    public function register(array $options): void
    {
        $this->startedCommands = new WeakMap;

        /**
         * @var Collection<int, string> $wildcards
         * @var Collection<int, string> $fullCommands
         */
        [$wildcards, $fullCommands] = Collection::make($options['excluded'] ?? [])
            ->map(function (string $command) {
                if (class_exists($command)) {
                    try {
                        return app($command)->getName();
                    } catch (Throwable) {
                        return null;
                    }
                }

                return $command;
            })
            ->filter()
            ->partition(fn (string $command) => str_ends_with($command, '*'));

        $this->excluded          = $fullCommands->values()->all();
        $this->excludedWildcards = $wildcards->values()->all();

        app('events')->listen(CommandStarting::class, [$this, 'commandStarting']);
        app('events')->listen(CommandFinished::class, [$this, 'commandFinished']);
    }

    public function commandStarting(CommandStarting $event): void
    {
        if (! $event->command) {
            return;
        }

        if ($this->isExcluded($event->command)) {
            return;
        }

        $span = Tracer::newSpan($event->command)
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::Command->value)
            ->setAttribute('console.command', $event->command)
            ->start();

        $this->recordCommandArguments($span, $event->input);

        $scope = $span->activate();

        $this->startedCommands[$event->input] = [$span, $scope];
    }

    public function commandFinished(CommandFinished $event): void
    {
        $trace = $this->startedCommands[$event->input] ?? null;

        if ($trace === null) {
            return;
        }

        /**
         * @var SpanInterface $span
         * @var ScopeInterface $scope
         */
        [$span, $scope] = $trace;

        Tracer::terminateActiveSpansUpToRoot($span);

        if ($event->exitCode !== 0) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        $scope->detach();
        $span->end();
    }

    protected function recordCommandArguments(SpanInterface $span, InputInterface $input): void
    {
        foreach ($input->getArguments() as $key => $value) {
            if ($key === 'command') {
                continue;
            }

            $span->setAttribute('console.argument.'.$key, $value);
        }

        foreach ($input->getOptions() as $key => $value) {
            if ($value === false) {
                continue;
            }

            $span->setAttribute('console.option.'.$key, $value);
        }
    }

    protected function isExcluded(string $command): bool
    {
        if (in_array($command, $this->excluded, true)) {
            return true;
        }

        foreach ($this->excludedWildcards as $wildcard) {
            if (str_starts_with($command, rtrim($wildcard, '*'))) {
                return true;
            }
        }

        return false;
    }
}
