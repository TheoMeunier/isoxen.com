# isoxen/laravel-client

Sends a Laravel application's activity — requests, jobs, queries, exceptions, mail,
notifications, scheduled tasks and more — to [isoxen](https://isoxen.com) as OpenTelemetry
spans, logs and metrics.

## How it works

This package is a fork of [keepsuit/laravel-opentelemetry](https://github.com/keepsuit/laravel-opentelemetry)
(MIT), trimmed down to the one thing it needs to do — talk to isoxen — and extended with the
sensors isoxen's original hand-written client had that keepsuit didn't: mail, notifications,
scheduled tasks, and explicit user login/logout events. See `LICENSE.md` for the full
attribution.

Concretely, that means:

- **Depth kept from keepsuit**: HTTP client tracing with real W3C trace-context propagation,
  database queries and Redis commands with per-operation duration metrics (not just spans),
  queue jobs whose producer and consumer spans are properly linked across the queue boundary,
  views, Livewire, Scout, and Octane/Horizon-aware flushing (see `WorkerMode/`).
- **Complexity stripped out**: no gRPC or protobuf transport, no Zipkin/console/memory
  exporters, no arbitrary custom processor injection. isoxen only speaks OTLP/HTTP+JSON, so
  that's the only path the client builds.
- **isoxen's own transport kept**: spans/logs/metrics are handed to a queued job
  (`Transport/QueuedTransport.php` + `Jobs/ExportTelemetry.php`) instead of sent over HTTP from
  the process that produced them — see "Run a worker for it" below.

Each span that isoxen categorizes carries an `isoxen.type` attribute (`request`, `query`,
`job`, ...) — see `SpanType.php`. This is the contract with the isoxen server
(`App\Watch\Ingestion\Support\ObservabilityCategories`), which uses it to drive a project's
sidebar. **Redis, view, Livewire and Scout spans are recorded and tagged, but isoxen's sidebar
doesn't have a tab for them yet** — they're stored, just uncategorized in the UI until the
server side is extended to know about them.

## Installation

```bash
composer require isoxen/laravel-client
```

The package only depends on the OpenTelemetry **API**. The application supplies the SDK and
the exporter that actually ship the spans:

```bash
composer require open-telemetry/sdk open-telemetry/exporter-otlp
```

Then point it at your isoxen project, using the ingestion token from the project's page:

```dotenv
OTEL_SERVICE_NAME=my-application
ISOXEN_ENDPOINT=https://isoxen.com
ISOXEN_TOKEN=proj_your_token_here
ISOXEN_QUEUE=telemetry
```

### Run a worker for it

By default the client doesn't send spans from the process that produced them — it hands each
payload to a queued job. Pushing to the queue costs about a millisecond; the HTTP call it
replaces costs tens of them, and under Octane your worker is blocked for every one of them.

That means telemetry needs a worker:

```bash
php artisan queue:work --queue=telemetry
```

It gets **its own queue** on purpose. A burst of telemetry must never sit in front of your
application's real jobs — monitoring that delays what it monitors has defeated itself. The
queue instrumentation also knows to never trace the export job itself, so shipping telemetry
can't turn into a loop that ships telemetry about shipping telemetry.

### If your application is itself an OTLP collector

Read this if the app you're monitoring also *receives* telemetry — isoxen.com monitoring
isoxen.com being the obvious case. Tracing the jobs that store incoming telemetry makes every
ingested batch produce new spans, exported as new jobs, ingested again. There's one export job
per signal, so the branching factor is three: the queue grows exponentially and never settles.

`ignore_paths` already stops the *request* side of that loop. The job side needs the ingestion
jobs excluded:

```dotenv
ISOXEN_SENSOR_JOBS_EXCLUDED="App\Watch\Ingestion\Jobs\*"
```

That one exclusion cuts the whole branch — query, Redis and cache instrumentation all bail out
when no trace is active, so nothing downstream of an untraced job is recorded either.

Two things keep that worker from feeding itself, and both matter: the export job never
triggers a flush (`WorkerMode\WorkerModeManager`), and `flush_after_each_iteration` is off by
default. Turn the latter on and every request or job produces up to three export jobs — under
Octane, forever. Traces and logs still leave on the SDK's own batch schedule without it;
metrics are collected on `metrics_collect_interval`.

If you'd rather not run a worker, send inline instead:

```dotenv
ISOXEN_TRANSPORT=http
```

Note that isoxen accepts OTLP over **HTTP with JSON** — protobuf isn't supported yet.

## Configuration

```bash
php artisan vendor:publish --tag=isoxen-config
```

Every sensor can be turned off individually in `config/isoxen.php`, under the `sensors` key —
a flat list of on/off switches (some, like `requests` and `commands`, accept an array instead
of a bare boolean for a few extra options). **Cache and events are off by default**: a
cache-heavy request can emit hundreds of spans, and `events` mirrors Laravel's entire event
bus, so both are opt-in.

To silence the client entirely — in tests, for instance:

```dotenv
ISOXEN_ENABLED=false
```

## What gets recorded

| Category | Source |
|---|---|
| Requests | `HttpServerInstrumentation` — the whole middleware stack, named after the matched route |
| Outgoing requests | `HttpClientInstrumentation` — Laravel's HTTP client, with real trace propagation to the called service |
| Queries | `QueryInstrumentation` — `db.client.operation.duration` metric plus a span per query |
| Redis | `RedisInstrumentation` — same shape as queries, not in the sidebar yet |
| Jobs | `QueueInstrumentation` — producer and consumer spans linked across the queue |
| Commands | `ConsoleInstrumentation` — every command by default, with an exclude list (scheduler/queue-worker commands are excluded out of the box) |
| Scheduled tasks | `ScheduledTaskInstrumentation` |
| Exceptions | the application's exception handler — tagged on the current span and recorded as its own span |
| Mail | `MailInstrumentation` — `MessageSent` |
| Notifications | `NotificationInstrumentation` — `NotificationSent` |
| Cache | `CacheInstrumentation` — off by default |
| Views | `ViewInstrumentation`, not in the sidebar yet |
| Livewire | `LivewireInstrumentation`, not in the sidebar yet |
| Scout | `ScoutInstrumentation`, not in the sidebar yet |
| Users | `UserInstrumentation` — explicit login/logout spans, plus every span/log is auto-tagged with `enduser.id` while authenticated |
| Logs | `LogInstrumentation` — `MessageLogged`, correlated to the surrounding trace |
| Metrics | `http.server.request.duration`, `db.client.operation.duration`, `http.client.request.duration`, keyed by route/operation, never by raw URL or query bindings |

Only a user's identifier is sent by default, never their name or email address — see
`user_context` in `config/isoxen.php` and `Support/UserContextResolver.php` to customize what's
collected.

## Diagnosing a silent pipeline

```bash
php artisan isoxen:doctor
```

Walks configuration, the OpenTelemetry providers, the queue, and sends one sample of each
signal — telling you which link in the chain (config, SDK, queue, network) is the reason
nothing is showing up, instead of leaving you to guess.

## Octane

Supported. `WorkerMode\WorkerModeManager` detects Octane and queue/Horizon workers and adapts
flush behavior accordingly, instead of a single blanket "flush after every request" setting.
