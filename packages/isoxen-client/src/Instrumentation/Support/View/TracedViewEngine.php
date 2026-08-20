<?php

namespace Isoxen\Client\Instrumentation\Support\View;

use Illuminate\Contracts\View\Engine;
use Illuminate\Contracts\View\Factory;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\SpanType;

class TracedViewEngine implements Engine
{
    public const VIEW_NAME = '__otel_view_name';

    public function __construct(
        protected string $name,
        protected Engine $engine,
        protected Factory $viewFactory,
    ) {}

    public function get($path, array $data = [])
    {
        if (! Tracer::traceStarted()) {
            return $this->engine->get($path, $data);
        }

        return Tracer::newSpan('view render')
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::View->value)
            ->setAttribute('template.name', $this->viewFactory->shared(self::VIEW_NAME, basename($path)))
            ->setAttribute('template.engine', $this->name)
            ->measure(fn () => $this->engine->get($path, $data));
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->engine->{$name}(...$arguments);
    }
}
