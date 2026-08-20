<?php

declare(strict_types=1);

use Isoxen\Client\Support\ResourceAttributesParser;
use Isoxen\Client\TailSampling;
use Isoxen\Client\WorkerMode;
use OpenTelemetry\SDK\Common\Configuration\Variables as OTELVariables;

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Turns the whole client off without uninstalling it. Useful to keep
    | local development and the test suite quiet.
    |
    */

    'enabled' => env('ISOXEN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Where to send, and with which key
    |--------------------------------------------------------------------------
    |
    | The endpoint is your isoxen installation's root — the client appends
    | the standard OTLP paths itself (v1/traces, v1/metrics, v1/logs). The
    | token is the one shown on the project's page, and it decides which
    | project the data lands in.
    |
    */

    'endpoint' => env('ISOXEN_ENDPOINT'),

    'token' => env('ISOXEN_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    |
    | "queue" hands each payload to a job, so the process that produced the
    | spans pays for a Redis push (about a millisecond) instead of an HTTP
    | round trip (tens of them). Under Octane that difference is the whole
    | throughput of the application, because the worker is blocked for the
    | duration of the call.
    |
    | It buys that with a dependency: telemetry now needs a queue worker. Set
    | this to "http" to send inline instead — simpler to run, slower per
    | request, no worker needed.
    |
    | Supported: "queue", "http"
    |
    */

    'transport' => env('ISOXEN_TRANSPORT', 'queue'),

    /*
    |--------------------------------------------------------------------------
    | Which queue carries the telemetry
    |--------------------------------------------------------------------------
    |
    | Deliberately its own queue rather than the default one. A burst of
    | telemetry must never sit in front of the application's real work —
    | monitoring that delays what it monitors has defeated itself.
    |
    | Run a worker for it: php artisan queue:work --queue=telemetry
    |
    */

    'queue' => [
        'connection' => env('ISOXEN_QUEUE_CONNECTION'),
        'queue' => env('ISOXEN_QUEUE', 'telemetry'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service identity
    |--------------------------------------------------------------------------
    |
    | How this application identifies itself to isoxen. `service_name` ends
    | up on every span as the standard `service.name` resource attribute,
    | which lets a single isoxen project hold several services.
    | `service_instance_id` should be unique per running instance if set —
    | leave it empty to let one be generated per process.
    |
    */

    'service_name' => env('OTEL_SERVICE_NAME', env('APP_NAME', 'laravel')),

    'service_instance_id' => env('OTEL_SERVICE_INSTANCE_ID'),

    /*
    | Extra key=value resource attributes attached to every span, log and
    | metric. Reads OTEL_RESOURCE_ATTRIBUTES (format: "key1=value1,key2=value2").
    */
    'resource_attributes' => ResourceAttributesParser::parse((string) env(OTELVariables::OTEL_RESOURCE_ATTRIBUTES, '')),

    /*
    |--------------------------------------------------------------------------
    | User context
    |--------------------------------------------------------------------------
    |
    | When true, every span and log line produced while a user is
    | authenticated is tagged with their id (`enduser.id`) — never their name
    | or email, those are personal data the monitored application shouldn't
    | leak to isoxen without deciding to. This is on top of, not instead of,
    | the "users" sensor below, which records explicit login/logout events.
    |
    */

    'user_context' => env('ISOXEN_USER_CONTEXT', true),

    /*
    |--------------------------------------------------------------------------
    | Worker mode
    |--------------------------------------------------------------------------
    |
    | The OpenTelemetry SDK buffers spans and normally ships them when the
    | process ends. Octane, queue workers, and Horizon never end, so without
    | this the buffer would grow forever and nothing would ever be sent.
    | isoxen detects which of those it's running under and flushes on a
    | schedule appropriate to it, instead of a single blanket setting.
    |
    */

    'worker_mode' => [
        // Flush after every request/job instead of letting the SDK batch.
        //
        // Off by default, and think hard before turning it on. It is not
        // "cheap under the queue transport": every iteration produces up to
        // three export jobs (one per signal), and the metrics pipeline
        // exports on every collect whether or not anything changed — so
        // under Octane this is three jobs per HTTP request, forever.
        //
        // Left off, traces and logs still leave on the SDK's own batch
        // schedule, and metrics are collected on the interval below. That
        // is the same behaviour upstream ships, and it is the right default.
        'flush_after_each_iteration' => env('ISOXEN_FLUSH_AFTER_EACH_ITERATION', false),

        'metrics_collect_interval' => (int) env('ISOXEN_METRICS_COLLECT_INTERVAL', 60),

        'detectors' => [
            WorkerMode\Detectors\OctaneWorkerModeDetector::class,
            WorkerMode\Detectors\QueueWorkerModeDetector::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored request paths
    |--------------------------------------------------------------------------
    |
    | Requests matching these patterns are never recorded.
    |
    | The OTLP paths are listed by default as loop protection: when an
    | application both sends telemetry to isoxen and *is* an isoxen server
    | (or any OTLP collector), recording the ingestion endpoint would make
    | every incoming batch of spans produce another span, which produces
    | another batch, and so on.
    |
    */

    'ignore_paths' => [
        'v1/traces',
        'v1/metrics',
        'v1/logs',
        'up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling
    |--------------------------------------------------------------------------
    |
    | Head sampling decides at trace start; "always_on" keeps everything,
    | "traceidratio" keeps a fixed percentage. Tail sampling decides after a
    | trace finishes and is off by default: it costs a short buffering delay
    | in exchange for always keeping errors and slow traces regardless of the
    | head sampling ratio.
    |
    */

    'sampler' => [
        'type' => env('ISOXEN_SAMPLER_TYPE', 'always_on'),
        'ratio' => (float) env('ISOXEN_SAMPLER_RATIO', 0.05),

        'tail_sampling' => [
            'enabled' => env('ISOXEN_TAIL_SAMPLING_ENABLED', false),
            'decision_wait_ms' => (int) env('ISOXEN_TAIL_SAMPLING_DECISION_WAIT_MS', 5000),
            'keep_errors' => env('ISOXEN_TAIL_SAMPLING_KEEP_ERRORS', true),
            'keep_slow_traces' => env('ISOXEN_TAIL_SAMPLING_KEEP_SLOW_TRACES', true),
            'slow_trace_threshold_ms' => (int) env('ISOXEN_TAIL_SAMPLING_SLOW_THRESHOLD_MS', 2000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensors
    |--------------------------------------------------------------------------
    |
    | Each sensor can be switched off on its own, or given extra options by
    | using an array instead of a bare true/false (see "requests" and
    | "commands" below for examples). isoxen.com's sidebar currently has a
    | tab for: requests, queries, jobs, exceptions, outgoing_requests,
    | commands, scheduled_tasks, notifications, mail, cache and users.
    | Redis, views, livewire and scout are recorded too but don't have a
    | dedicated tab yet — see SpanType's docblock.
    |
    | Cache and events are the two worth knowing about: a cache-heavy request
    | can produce hundreds of spans, and "events" mirrors Laravel's entire
    | event bus, so both are off by default.
    |
    */

    'sensors' => [
        'requests' => [
            'enabled' => env('ISOXEN_SENSOR_REQUESTS', true),
            'excluded_paths' => [],
            'excluded_methods' => [],
            'allowed_headers' => [],
            'sensitive_headers' => [],
            'sensitive_query_parameters' => [],
        ],

        'outgoing_requests' => env('ISOXEN_SENSOR_OUTGOING_REQUESTS', true),

        'queries' => env('ISOXEN_SENSOR_QUERIES', true),

        'redis' => env('ISOXEN_SENSOR_REDIS', true),

        'jobs' => [
            'enabled' => env('ISOXEN_SENSOR_JOBS', true),

            // Job classes never worth tracing. Supports `*` wildcards.
            //
            // Critical when the monitored application is itself an OTLP
            // collector (isoxen.com monitoring isoxen.com): tracing the jobs
            // that store incoming telemetry makes every ingested batch
            // produce new spans, exported as new jobs, ingested again — one
            // export job per signal, so it grows exponentially and never
            // settles. Exclude your ingestion jobs here.
            //
            // The client's own ExportTelemetry job is always excluded and
            // doesn't need listing.
            'excluded' => array_values(array_filter(
                explode(',', (string) env('ISOXEN_SENSOR_JOBS_EXCLUDED', '')),
            )),
        ],

        'cache' => env('ISOXEN_SENSOR_CACHE', false),

        'events' => [
            'enabled' => env('ISOXEN_SENSOR_EVENTS', false),
            'excluded' => [],
        ],

        'views' => env('ISOXEN_SENSOR_VIEWS', true),

        'livewire' => env('ISOXEN_SENSOR_LIVEWIRE', true),

        'scout' => env('ISOXEN_SENSOR_SCOUT', true),

        'commands' => [
            'enabled' => env('ISOXEN_SENSOR_COMMANDS', true),
            // Commands never worth their own span: the scheduler's own run
            // is recorded per-task by the scheduled_tasks sensor instead,
            // and a queue worker's command would otherwise open a span that
            // stays open for its entire life, swallowing every job under it.
            'excluded' => ['schedule:run', 'schedule:work', 'queue:work', 'queue:listen', 'horizon'],
        ],

        'scheduled_tasks' => env('ISOXEN_SENSOR_SCHEDULED_TASKS', true),

        'exceptions' => env('ISOXEN_SENSOR_EXCEPTIONS', true),

        'mail' => env('ISOXEN_SENSOR_MAIL', true),

        'notifications' => env('ISOXEN_SENSOR_NOTIFICATIONS', true),

        'users' => env('ISOXEN_SENSOR_USERS', true),

        'logs' => env('ISOXEN_SENSOR_LOGS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    */

    'logs' => [
        // Inject the active trace id into every log line's context, so logs
        // written through channels other than isoxen's own can still be
        // correlated to the trace that produced them.
        'inject_trace_id' => true,
        'trace_id_field' => 'trace_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Propagators
    |--------------------------------------------------------------------------
    |
    | Comma separated list of propagators used to carry trace context across
    | process boundaries (outgoing HTTP calls, queued jobs). "tracecontext"
    | is the W3C standard and the only one needed to talk to isoxen or any
    | other OTLP backend; add "baggage" if you rely on OTEL baggage too.
    |
    */

    'propagators' => env(OTELVariables::OTEL_PROPAGATORS, 'tracecontext'),
];
