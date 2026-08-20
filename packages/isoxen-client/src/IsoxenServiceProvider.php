<?php

namespace Isoxen\Client;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Config;
use Isoxen\Client\Support\CarbonClock;
use Isoxen\Client\Support\NoopSpanExporter;
use Isoxen\Client\Support\OpenTelemetryMonologHandler;
use Isoxen\Client\Support\PropagatorBuilder;
use Isoxen\Client\Support\ResourceBuilder;
use Isoxen\Client\Support\SamplerBuilder;
use Isoxen\Client\Support\UserContextResolver;
use Isoxen\Client\TailSampling\TailSamplingProcessor;
use Isoxen\Client\TailSampling\TailSamplingRuleInterface;
use Isoxen\Client\Transport\QueuedTransport;
use Isoxen\Client\WorkerMode\WorkerModeDetectorInterface;
use Isoxen\Client\WorkerMode\WorkerModeManager;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\NoopTextMapPropagator;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter as OtlpSpanExporter;
use OpenTelemetry\SDK\Common\Configuration\Variables as OTELVariables;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Logs\Exporter\NoopExporter as LogsNoopExporter;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\NoopLoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricExporter\NoopMetricExporter;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\MetricReaderInterface;
use OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Throwable;

class IsoxenServiceProvider extends PackageServiceProvider
{
    /**
     * Which sensor config key drives which instrumentation class.
     *
     * Keeping this map here (rather than putting class names in
     * config/isoxen.php, like the upstream package this was forked from
     * does) is the one deliberate simplification trade-off: the config file
     * stays a flat, readable list of on/off switches instead of a list of
     * fully-qualified class names.
     */
    protected const SENSOR_MAP = [
        'requests' => Instrumentation\HttpServerInstrumentation::class,
        'outgoing_requests' => Instrumentation\HttpClientInstrumentation::class,
        'queries' => Instrumentation\QueryInstrumentation::class,
        'redis' => Instrumentation\RedisInstrumentation::class,
        'jobs' => Instrumentation\QueueInstrumentation::class,
        'cache' => Instrumentation\CacheInstrumentation::class,
        'events' => Instrumentation\EventInstrumentation::class,
        'views' => Instrumentation\ViewInstrumentation::class,
        'livewire' => Instrumentation\LivewireInstrumentation::class,
        'scout' => Instrumentation\ScoutInstrumentation::class,
        'commands' => Instrumentation\ConsoleInstrumentation::class,
        'scheduled_tasks' => Instrumentation\ScheduledTaskInstrumentation::class,
        'mail' => Instrumentation\MailInstrumentation::class,
        'notifications' => Instrumentation\NotificationInstrumentation::class,
        'users' => Instrumentation\UserInstrumentation::class,
        'logs' => Instrumentation\LogInstrumentation::class,
    ];

    public function packageRegistered(): void
    {
        $this->app->singleton(Meter::class);
        $this->app->singleton(Tracer::class);
        $this->app->singleton(Logger::class);
        $this->app->singleton(OpenTelemetry::class);
        $this->app->singleton(UserContextResolver::class);

        $this->configureEnvironmentVariables();
        $this->injectConfig();
        $this->resolveInstrumentationConfig();
        $this->initWorkerModeManager();
    }

    public function packageBooted(): void
    {
        $this->initOtelSdk();
        $this->bootSingletons();
        $this->registerInstrumentation();
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('isoxen')
            ->hasConfigFile('isoxen')
            ->hasCommand(Console\DoctorCommand::class);
    }

    /**
     * Turns each `sensors.*` config entry into an [InstrumentationClass =>
     * options] pair for {@see InstrumentationServiceProvider}, and merges
     * the global `ignore_paths` into the requests sensor's excluded paths
     * (isoxen's own OTLP ingestion routes must never trace themselves, or
     * every batch of spans would produce another span to export).
     */
    protected function resolveInstrumentationConfig(): void
    {
        $resolved = [];

        foreach (self::SENSOR_MAP as $sensorKey => $class) {
            $value = config("isoxen.sensors.{$sensorKey}", true);
            $enabled = is_array($value) ? (bool) ($value['enabled'] ?? true) : (bool) $value;

            if (! $enabled) {
                continue;
            }

            $options = is_array($value) ? $value : [];

            if ($sensorKey === 'requests') {
                $options['excluded_paths'] = array_values(array_unique(array_merge(
                    $options['excluded_paths'] ?? [],
                    config('isoxen.ignore_paths', []),
                )));
            }

            $resolved[$class] = $options;
        }

        Config::set('isoxen.instrumentation', $resolved);
    }

    protected function initOtelSdk(): void
    {
        Clock::setDefault(new CarbonClock);

        $resource = ResourceBuilder::build();

        $propagator = match (Sdk::isDisabled()) {
            true => new NoopTextMapPropagator,
            false => PropagatorBuilder::new()->build(config('isoxen.propagators')),
        };

        $meterProvider = $this->buildMeterProvider($resource);
        $tracerProvider = $this->buildTracerProvider($resource, meterProvider: $meterProvider);
        $loggerProvider = $this->buildLoggerProvider($resource, meterProvider: $meterProvider);

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setLoggerProvider($loggerProvider)
            ->setMeterProvider($meterProvider)
            ->setPropagator($propagator)
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        $instrumentation = new CachedInstrumentation(name: 'isoxen-laravel-client');

        $this->app->singleton(TextMapPropagatorInterface::class, fn () => $propagator);
        $this->app->singleton(MeterInterface::class, fn () => $instrumentation->meter());
        $this->app->singleton(TracerInterface::class, fn () => $instrumentation->tracer());
        $this->app->singleton(LoggerInterface::class, fn () => $instrumentation->logger());
    }

    protected function registerInstrumentation(): void
    {
        if (Sdk::isDisabled()) {
            return;
        }

        $this->app->register(InstrumentationServiceProvider::class);

        $this->callAfterResolving(ExceptionHandlerContract::class, function (ExceptionHandlerContract $handler) {
            if (! method_exists($handler, 'reportable')) {
                return;
            }

            if (! $this->sensorEnabled('exceptions')) {
                return;
            }

            $handler->reportable(function (Throwable $e) {
                // An exception raised while shipping telemetry must not
                // become telemetry: that span would be exported, that
                // export would fail the same way, and each failure would
                // produce another. See Support\Suppression.
                if (Support\Suppression::active()) {
                    return;
                }

                // Attached to whatever span is currently open, so a failing
                // request/job/command shows as failed in its own trace...
                Facades\Tracer::activeSpan()
                    ->recordException($e)
                    ->setStatus(StatusCode::STATUS_ERROR);

                // ...and recorded a second time as its own span, so the
                // Exceptions tab can show it even when nothing else about
                // the surrounding trace is interesting.
                $span = Facades\Tracer::newSpan(class_basename($e))
                    ->setAttribute(SpanType::ATTRIBUTE, SpanType::Exception->value)
                    ->setAttribute('exception.type', $e::class)
                    ->setAttribute('exception.message', $e->getMessage())
                    ->setAttribute('code.file.path', $e->getFile())
                    ->setAttribute('code.line.number', $e->getLine())
                    ->start();

                $span->recordException($e)->setStatus(StatusCode::STATUS_ERROR);
                $span->end();
            });
        });
    }

    protected function sensorEnabled(string $sensor): bool
    {
        $value = config("isoxen.sensors.{$sensor}", true);

        return is_array($value) ? (bool) ($value['enabled'] ?? true) : (bool) $value;
    }

    protected function configureEnvironmentVariables(): void
    {
        $envRepository = Env::getRepository();

        $envRepository->set(
            OTELVariables::OTEL_SDK_DISABLED,
            Config::boolean('isoxen.enabled', true) ? 'false' : 'true'
        );

        $envRepository->set(OTELVariables::OTEL_SERVICE_NAME, config('isoxen.service_name'));

        // Disable debug scopes wrapping
        $envRepository->set('OTEL_PHP_DEBUG_SCOPES_DISABLED', '1');
    }

    protected function buildTracerProvider(ResourceInfo $resource, MeterProviderInterface $meterProvider): TracerProviderInterface
    {
        $spanExporter = match (Sdk::isDisabled()) {
            true => new NoopSpanExporter,
            false => new OtlpSpanExporter($this->buildTransport('traces')),
        };
        $this->app->bind(SpanExporterInterface::class, fn () => $spanExporter);

        if (Sdk::isDisabled()) {
            return new NoopTracerProvider;
        }

        $batchProcessor = new BatchSpanProcessor(
            exporter: $spanExporter,
            clock: Clock::getDefault(),
            meterProvider: $meterProvider
        );

        $sampler = SamplerBuilder::new()->build(
            (string) config('isoxen.sampler.type', 'always_on'),
            parentBased: true,
            args: ['ratio' => config('isoxen.sampler.ratio', 0.05)],
        );

        $tailSamplingConfig = config('isoxen.sampler.tail_sampling', []);
        $tailSamplingEnabled = (bool) ($tailSamplingConfig['enabled'] ?? false);

        return TracerProvider::builder()
            ->setResource($resource)
            ->setSampler(match ($tailSamplingEnabled) {
                true => new AlwaysOnSampler,
                false => $sampler,
            })
            ->addSpanProcessor(match ($tailSamplingEnabled) {
                true => $this->buildTailSamplingProcessor($batchProcessor, $sampler, $tailSamplingConfig),
                false => $batchProcessor,
            })
            ->build();
    }

    protected function buildMeterProvider(ResourceInfo $resource): MeterProviderInterface
    {
        $metricsExporter = match (Sdk::isDisabled()) {
            true => new NoopMetricExporter,
            false => new MetricExporter($this->buildTransport('metrics')),
        };
        $this->app->singleton(MetricExporterInterface::class, fn () => $metricsExporter);
        $metricsReader = new ExportingReader($metricsExporter);
        $this->app->singleton(MetricReaderInterface::class, fn () => $metricsReader);

        if (Sdk::isDisabled()) {
            return new NoopMeterProvider;
        }

        return MeterProvider::builder()
            ->setResource($resource)
            ->addReader($metricsReader)
            ->build();
    }

    protected function buildLoggerProvider(ResourceInfo $resource, MeterProviderInterface $meterProvider): LoggerProviderInterface
    {
        $logExporter = match (Sdk::isDisabled()) {
            true => new LogsNoopExporter,
            false => new LogsExporter($this->buildTransport('logs')),
        };
        $this->app->bind(LogRecordExporterInterface::class, fn () => $logExporter);

        if (Sdk::isDisabled()) {
            return new NoopLoggerProvider;
        }

        $logProcessor = new BatchLogRecordProcessor(
            exporter: $logExporter,
            clock: Clock::getDefault(),
            meterProvider: $meterProvider
        );

        return LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor($logProcessor)
            ->build();
    }

    /**
     * Where every signal ultimately goes: either a queued job (see
     * {@see QueuedTransport}) or, for apps that don't want to run a worker
     * for it, a direct OTLP/HTTP+JSON call to isoxen.
     *
     * Unlike the package this was forked from, there's no protocol
     * negotiation here (grpc / http+protobuf / http+json) and no other
     * destination to point at (Zipkin, console, memory): isoxen only speaks
     * OTLP/HTTP+JSON today, so that's the only path either mode builds.
     *
     * @param  'traces'|'metrics'|'logs'  $signal
     */
    protected function buildTransport(string $signal): TransportInterface
    {
        if (config('isoxen.transport') === 'http') {
            return $this->buildHttpTransport($signal);
        }

        return new QueuedTransport(
            $signal,
            config('isoxen.queue.connection'),
            config('isoxen.queue.queue'),
        );
    }

    /**
     * @param  'traces'|'metrics'|'logs'  $signal
     */
    protected function buildHttpTransport(string $signal): TransportInterface
    {
        $endpoint = rtrim((string) config('isoxen.endpoint'), '/');
        $token = (string) config('isoxen.token');

        return (new OtlpHttpTransportFactory)->create(
            endpoint: "{$endpoint}/v1/{$signal}",
            contentType: ContentTypes::JSON,
            headers: $token !== '' ? ['Authorization' => "Bearer {$token}"] : [],
            timeout: 10,
            maxRetries: 3,
        );
    }

    /**
     * @param  array{
     *     enabled?: bool,
     *     decision_wait_ms?: int,
     *     keep_errors?: bool,
     *     keep_slow_traces?: bool,
     *     slow_trace_threshold_ms?: int,
     * }  $config
     */
    protected function buildTailSamplingProcessor(SpanProcessorInterface $downstreamProcessor, SamplerInterface $sampler, array $config): SpanProcessorInterface
    {
        $rules = [];

        foreach ([
            TailSampling\Rules\ErrorsRule::class => (bool) ($config['keep_errors'] ?? true),
            TailSampling\Rules\SlowTraceRule::class => [
                'enabled' => (bool) ($config['keep_slow_traces'] ?? true),
                'threshold_ms' => (int) ($config['slow_trace_threshold_ms'] ?? 2000),
            ],
        ] as $ruleClass => $options) {
            if (is_bool($options)) {
                $options = ['enabled' => $options];
            }

            if (! ($options['enabled'] ?? true)) {
                continue;
            }

            $rule = $this->app->make($ruleClass);

            if (! $rule instanceof TailSamplingRuleInterface) {
                continue;
            }

            $rule->initialize($options);
            $rules[] = $rule;
        }

        return new TailSamplingProcessor(
            $downstreamProcessor,
            $sampler,
            $rules,
            decisionWait: max(1, (int) ($config['decision_wait_ms'] ?? 5000))
        );
    }

    protected function injectConfig(): void
    {
        $this->callAfterResolving(Repository::class, function (Repository $config) {
            if ($config->has('logging.channels.otlp')) {
                return;
            }

            $config->set('logging.channels.otlp', [
                'driver' => 'monolog',
                'handler' => OpenTelemetryMonologHandler::class,
                'level' => 'debug',
            ]);
        });
    }

    protected function initWorkerModeManager(): void
    {
        $this->app->singleton(WorkerModeManager::class, function () {
            $detectors = Collection::make(config()->array('isoxen.worker_mode.detectors', []))
                ->map(function (string $detectorClass) {
                    if (! class_exists($detectorClass)) {
                        return null;
                    }

                    $detector = Container::getInstance()->make($detectorClass);

                    return $detector instanceof WorkerModeDetectorInterface ? $detector : null;
                })
                ->filter()
                ->all();

            return new WorkerModeManager(
                flushAfterEachIteration: config()->boolean('isoxen.worker_mode.flush_after_each_iteration', false),
                metricsExportInterval: config()->integer('isoxen.worker_mode.metrics_collect_interval', 60),
                detectors: $detectors
            );
        });
    }

    /**
     * This ensures that singletons are resolved before handling any request when running in worker mode.
     * This prevents them from being flushed between requests.
     */
    protected function bootSingletons(): void
    {
        $singletons = [
            Meter::class,
            Tracer::class,
            Logger::class,
            OpenTelemetry::class,
            UserContextResolver::class,
            WorkerModeManager::class,
        ];

        foreach ($singletons as $singleton) {
            if ($this->app->bound($singleton)) {
                $this->app->make($singleton);
            }
        }
    }
}
