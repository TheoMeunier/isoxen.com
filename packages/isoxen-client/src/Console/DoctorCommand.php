<?php

namespace Isoxen\Client\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Isoxen\Client\Facades\Meter;
use Isoxen\Client\Facades\OpenTelemetry;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\SpanType;
use Isoxen\Client\Support\Suppression;
use Isoxen\Client\Transport\QueuedTransport;
use OpenTelemetry\API\Globals;
use OpenTelemetry\SemConv\Metrics\HttpMetrics;
use Throwable;

/**
 * Answers "why is nothing showing up?" without guesswork.
 *
 * Telemetry crosses several boundaries — configuration, the OpenTelemetry
 * SDK, a queue, an HTTP call, a database write — and when the end of that
 * chain is empty, any one of them could be the reason. This walks it in
 * order and says which link is broken.
 */
class DoctorCommand extends Command
{
    protected $signature = 'isoxen:doctor';

    protected $description = 'Check that this application can send telemetry to isoxen.';

    /**
     * Problems found, so the exit code reflects them and this is usable in
     * a health check rather than only by eye.
     */
    private int $problems = 0;

    /** Payloads already waiting on the queue when this ran. */
    private int $pending = 0;

    public function handle(): int
    {
        $this->configuration();

        // Stop here rather than walking the rest of the chain. With the
        // client off, every later check reports "nothing produced" — three
        // red lines pointing at the queue when the real cause is the line
        // above. A diagnostic that manufactures its own false leads is
        // worse than none.
        if (! config('isoxen.enabled', true)) {
            $this->newLine();
            $this->components->error('The client is disabled — nothing is recorded, and nothing is sent.');
            $this->line('  Set <fg=yellow>ISOXEN_ENABLED=true</> in .env, then run <fg=yellow>php artisan config:clear</>.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->providers();
        $this->roundTrip();
        $this->queueDepth();
        $this->emitSamples();
        $this->nextSteps();

        return $this->problems > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function configuration(): void
    {
        $this->components->info('Configuration');

        $token = (string) config('isoxen.token');
        $endpoint = (string) config('isoxen.endpoint');

        $this->components->twoColumnDetail('enabled', config('isoxen.enabled') ? '<fg=green>yes</>' : '<fg=red>no — nothing will be sent</>');
        $this->components->twoColumnDetail('transport', (string) config('isoxen.transport'));
        $this->components->twoColumnDetail('endpoint', $endpoint ?: '<fg=red>missing</>');
        $this->components->twoColumnDetail(
            'token',
            $token === '' ? '<fg=red>missing</>' : '<fg=green>set</> ('.substr($token, 0, 9).'…)',
        );
        $this->components->twoColumnDetail('queue', (string) config('isoxen.queue.queue'));

        if ($endpoint === '' || $token === '') {
            $this->problems++;
        }

        $this->warnAboutLoopbackEndpoint($endpoint);
    }

    /**
     * A loopback endpoint is right on a single host and wrong the moment
     * the queue worker lives anywhere else.
     *
     * Worth its own warning because the failure is silent and misleading:
     * the worker resolves `localhost` to *itself*, where no web server is
     * listening, so every export is refused while the application it is
     * supposed to be monitoring looks perfectly healthy.
     */
    private function warnAboutLoopbackEndpoint(string $endpoint): void
    {
        if ($endpoint === '' || config('isoxen.transport') !== 'queue') {
            return;
        }

        $host = parse_url($endpoint, PHP_URL_HOST);

        if (! in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            return;
        }

        $this->newLine();
        $this->components->warn("The endpoint points at {$host}, which resolves to whichever container or host runs the export.");
        $this->line('  If the queue worker runs separately from the web server, it is resolving this to itself');
        $this->line('  and every export is being refused. Use a hostname both sides can reach.');
    }

    private function providers(): void
    {
        $this->components->info('OpenTelemetry providers');

        // A "Noop" provider here is the usual reason for silence: it
        // accepts everything and discards it. It means the SDK was never
        // configured, or something else registered itself first.
        foreach ([
            'tracer' => Globals::tracerProvider(),
            'logger' => Globals::loggerProvider(),
            'meter' => Globals::meterProvider(),
        ] as $label => $provider) {
            $name = class_basename($provider);
            $noop = str_contains($name, 'Noop');

            if ($noop) {
                $this->problems++;
            }

            $this->components->twoColumnDetail(
                $label,
                $noop ? "<fg=red>{$name} — discards everything</>" : "<fg=green>{$name}</>",
            );
        }
    }

    /**
     * Actually call the ingestion endpoint and report what came back.
     *
     * Everything else in this command checks that telemetry was *produced*.
     * None of it can tell a working pipeline from one where every payload is
     * rejected on arrival — the queue drains either way, and a released job
     * prints DONE exactly like a delivered one. So this sends an empty but
     * valid OTLP batch and reports the status verbatim.
     */
    private function roundTrip(): void
    {
        $this->components->info('Ingestion round trip');

        $endpoint = rtrim((string) config('isoxen.endpoint'), '/');
        $token = (string) config('isoxen.token');

        if ($endpoint === '' || $token === '') {
            $this->components->twoColumnDetail('POST /v1/traces', '<fg=red>skipped — endpoint or token missing</>');

            return;
        }

        try {
            $response = Suppression::run(fn () => Http::withToken($token)
                ->withBody('{"resourceSpans":[]}', 'application/json')
                ->timeout(10)
                ->post("{$endpoint}/v1/traces"));
        } catch (Throwable $e) {
            $this->problems++;
            // The URL goes on its own line: twoColumnDetail pads the gap
            // between its columns with dots, and a label containing `//`
            // comes out mangled.
            $this->components->twoColumnDetail('POST /v1/traces', '<fg=red>unreachable</>');
            $this->line("  <fg=gray>{$endpoint}/v1/traces</>");
            $this->line('  <fg=red>'.$e->getMessage().'</>');
            $this->line('  The process sending telemetry cannot reach the endpoint. If the queue worker');
            $this->line('  runs in its own container, the hostname must be one *it* can resolve.');

            return;
        }

        $status = $response->status();

        if ($response->successful()) {
            $this->components->twoColumnDetail("POST {$endpoint}/v1/traces", "<fg=green>HTTP {$status}</>");

            return;
        }

        $this->problems++;
        $this->components->twoColumnDetail('POST /v1/traces', "<fg=red>HTTP {$status}</>");
        $this->line("  <fg=gray>{$endpoint}/v1/traces</>");
        $this->line('  <fg=red>'.trim(Str::limit((string) $response->body(), 300)).'</>');

        $hint = match (true) {
            $status === 401 => 'ISOXEN_TOKEN does not match any project\'s ingestion token.',
            $status === 404 => 'The OTLP routes are not registered on this endpoint.',
            $status === 415 => 'The endpoint rejected JSON — it expects a different content type.',
            $status === 419 => 'The request hit the stateful `web` middleware group; OTLP routes need `api`.',
            $status >= 500 => 'The endpoint errored — check its own logs.',
            default => null,
        };

        if ($hint !== null) {
            $this->line("  <fg=yellow>{$hint}</>");
        }
    }

    private function queueDepth(): void
    {
        if (config('isoxen.transport') !== 'queue') {
            return;
        }

        $this->components->info('Queue');

        $queue = (string) config('isoxen.queue.queue');

        try {
            $size = Queue::connection(config('isoxen.queue.connection'))->size($queue);
        } catch (Throwable $e) {
            $this->problems++;
            $this->components->twoColumnDetail($queue, '<fg=red>unreachable — '.$e->getMessage().'</>');

            return;
        }

        $this->pending = $size;

        // A queue that keeps growing means payloads are being produced but
        // nothing is consuming them — usually a worker that isn't covering
        // this queue.
        $this->components->twoColumnDetail(
            $queue.' (pending)',
            $size > 0 ? "<fg=yellow>{$size} waiting — is a worker running for this queue?</>" : '<fg=green>0</>',
        );
    }

    private function emitSamples(): void
    {
        $this->components->info('Sending one sample of each signal');

        Tracer::newSpan('isoxen:doctor')
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::Request->value)
            ->start()
            ->end();

        Log::info('isoxen:doctor test log record');

        Meter::histogram(
            name: HttpMetrics::HTTP_SERVER_REQUEST_DURATION,
            unit: 's',
            description: 'Duration of HTTP server requests.',
        )->record(0.123, [
            'http.request.method' => 'GET',
            'http.route' => 'isoxen/doctor',
            'http.response.status_code' => 200,
        ]);

        OpenTelemetry::flush();

        // What actually left the process, per signal. A signal at 0 here
        // was never produced — the pipeline is the problem, not the
        // network, the queue or the server.
        foreach (['traces', 'logs', 'metrics'] as $signal) {
            $count = QueuedTransport::$dispatched[$signal] ?? 0;

            if ($count === 0 && ! $this->httpTransport()) {
                $this->problems++;
            }

            $this->components->twoColumnDetail(
                $signal,
                $count > 0
                    ? "<fg=green>{$count} payload(s) queued</>"
                    : ($this->httpTransport() ? '<fg=green>sent inline (http transport)</>' : '<fg=red>nothing produced — this signal is not reaching the queue at all</>'),
            );
        }
    }

    /**
     * Closing advice, matched to what was actually observed — a fixed
     * "check the worker" footer is noise when the queue is draining fine.
     */
    private function nextSteps(): void
    {
        $this->newLine();

        if ($this->problems > 0) {
            $this->line('Fix the items marked in red above, then run this again.');
            $this->newLine();

            return;
        }

        $this->line('Now check the project in isoxen: the samples above should appear');
        $this->line('under Requests, Logs and Metrics within a few seconds.');

        if ($this->httpTransport()) {
            $this->newLine();

            return;
        }

        $this->newLine();

        if ($this->pending > 0) {
            $this->line("<fg=yellow>{$this->pending} payload(s) were already waiting</> — they only move when a worker covers this queue:");
        } else {
            $this->line('If they do not, the queue worker is the next thing to look at:');
        }

        $this->line('  <fg=yellow>php artisan queue:work --queue='.config('isoxen.queue.queue').'</>');
        $this->newLine();
    }

    private function httpTransport(): bool
    {
        return config('isoxen.transport') === 'http';
    }
}
