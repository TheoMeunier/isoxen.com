<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Database\Eloquent\Model;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\Support\RequestActivityCounters;

/**
 * Counts Eloquent model hydrations for the active request, using the
 * native {@see Model::retrieved()} event — no method interception or
 * decoration needed, unlike the filesystem sensor.
 *
 * This deliberately does not open a span per model: a single request can
 * hydrate hundreds of rows, and what the "Details" panel needs is a count,
 * not a timeline. {@see \Isoxen\Client\Support\RequestActivityCounters}
 * holds the running total; {@see
 * \Isoxen\Client\Instrumentation\Support\Http\Server\TraceRequestMiddleware}
 * resets it per request and attaches the final count to the request span.
 */
class ModelInstrumentation implements Instrumentation
{
    public function register(array $options): void
    {
        Model::retrieved(function (): void {
            if (! Tracer::traceStarted()) {
                return;
            }

            RequestActivityCounters::increment('hydrated_models');
        });
    }
}
