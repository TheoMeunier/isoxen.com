<?php

namespace Isoxen\Client\Instrumentation\Support\Http\Server;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Isoxen\Client\Facades\Meter;
use Isoxen\Client\Facades\OpenTelemetry;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\Instrumentation\HttpServerInstrumentation;
use Isoxen\Client\SpanType;
use Isoxen\Client\Support\Suppression;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\ClientAttributes;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Attributes\NetworkAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Attributes\UrlAttributes;
use OpenTelemetry\SemConv\Attributes\UserAgentAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\HttpIncubatingAttributes;
use OpenTelemetry\SemConv\Metrics\HttpMetrics;
use Symfony\Component\HttpFoundation\Response;

class TraceRequestMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->is(HttpServerInstrumentation::getExcludedPaths())) {
            // An excluded path is usually excluded because it *is* telemetry
            // — an OTLP ingestion route. Skipping the span is not enough:
            // the process still flushes whatever it holds when the script
            // ends, which turns every ingested batch into a new outgoing
            // one. Sealing stops this request from shipping anything at all.
            Suppression::seal();

            return $next($request);
        }

        Suppression::unseal();

        if (in_array($request->method(), HttpServerInstrumentation::getExcludedMethods(), true)) {
            return $next($request);
        }

        $bootedTimestamp  = HttpServerInstrumentation::getBootedTimestamp() ?? Clock::getDefault()->now();
        $requestStartedAt = $this->requestStartTimestamp($request);

        $span  = $this->startTracing($request, $requestStartedAt);
        $scope = $span->activate();

        Tracer::updateLogContext();

        if ($bootedTimestamp > $requestStartedAt) {
            Tracer::newSpan('app bootstrap')
                ->setStartTimestamp($requestStartedAt)
                ->start()
                ->end($bootedTimestamp);
        }

        try {
            $response = $next($request);

            if ($response instanceof Response) {
                $sharedAttributes = $this->sharedTraceMetricAttributes($request, $response);

                $this->recordTraceAttributes($span, $request, $response, $sharedAttributes);
                $this->recordRequestDurationMetric($requestStartedAt, $sharedAttributes);
            }

            return $response;
        } finally {
            Tracer::terminateActiveSpansUpToRoot($span);

            $scope->detach();
            $span->end();
        }
    }

    protected function startTracing(Request $request, ?int $startTimestamp = null): SpanInterface
    {
        $context = Tracer::extractContextFromPropagationHeaders($request->headers->all());

        $route = $this->resolveRouteName($request);

        $builder = Tracer::newSpan(trim(sprintf('%s %s', $request->method(), $route ?? '')))
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::Request->value)
            ->setParent($context);

        if ($startTimestamp) {
            $builder->setStartTimestamp($startTimestamp);
        }

        $span = $builder->start();

        $this->recordHeaders($span, $request);

        return $span;
    }

    /**
     * @param  array<non-empty-string, bool|int|float|string|array|null>  $attributes
     */
    protected function recordTraceAttributes(SpanInterface $span, Request $request, Response $response, array $attributes): void
    {
        $span->setAttributes($attributes);

        $redactedQueryString = HttpServerInstrumentation::redactQueryString($request->getQueryString() ?? '');

        $fullUrl = $redactedQueryString === '' ? $request->url() : sprintf('%s?%s', $request->url(), $redactedQueryString);

        $span
            ->setAttribute(UrlAttributes::URL_FULL, $fullUrl)
            ->setAttribute(UrlAttributes::URL_PATH, $request->path() === '/' ? $request->path() : '/'.$request->path())
            ->setAttribute(UrlAttributes::URL_QUERY, $redactedQueryString)
            ->setAttribute(UserAgentAttributes::USER_AGENT_ORIGINAL, $request->userAgent())
            ->setAttribute(HttpIncubatingAttributes::HTTP_REQUEST_BODY_SIZE, $request->header('Content-Length'))
            ->setAttribute(NetworkAttributes::NETWORK_PEER_ADDRESS, $request->server('REMOTE_ADDR'))
            ->setAttribute(ClientAttributes::CLIENT_ADDRESS, $request->ip());

        if (config('isoxen.user_context') === true && $request->user() !== null) {
            $span->setAttributes(OpenTelemetry::collectUserContext($request->user()));
        }

        $span->setAttribute(HttpAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());

        if (($content = $response->getContent()) !== false) {
            $span->setAttribute(HttpIncubatingAttributes::HTTP_RESPONSE_BODY_SIZE, strlen($content));
        }

        $this->recordHeaders($span, $response);

        if ($response->isSuccessful()) {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        if ($response->isServerError()) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        }
    }

    /**
     * @param  array<non-empty-string, bool|int|float|string|array|null>  $attributes
     */
    protected function recordRequestDurationMetric(int $requestStartedAt, array $attributes): void
    {
        $duration = (Clock::getDefault()->now() - $requestStartedAt) / ClockInterface::NANOS_PER_SECOND;

        // @see https://opentelemetry.io/docs/specs/semconv/http/http-metrics/#metric-httpserverrequestduration
        Meter::histogram(
            name: HttpMetrics::HTTP_SERVER_REQUEST_DURATION,
            unit: 's',
            description: 'Duration of HTTP server requests.',
            advisory: [
                'ExplicitBucketBoundaries' => [0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0],
            ]
        )
            ->record($duration, $attributes);
    }

    /**
     * @return array<non-empty-string, bool|int|float|string|array|null>
     */
    protected function sharedTraceMetricAttributes(Request $request, Response $response): array
    {
        $protocolVersion = Str::of($request->getProtocolVersion() ?? '');

        return [
            UrlAttributes::URL_SCHEME                   => $request->getScheme(),
            HttpAttributes::HTTP_REQUEST_METHOD         => $request->method(),
            HttpAttributes::HTTP_RESPONSE_STATUS_CODE   => $response->getStatusCode(),
            HttpAttributes::HTTP_ROUTE                  => $this->resolveRouteName($request),
            ErrorAttributes::ERROR_TYPE                 => $response->isOk() ? null : (string) $response->getStatusCode(),
            NetworkAttributes::NETWORK_PROTOCOL_NAME    => 'http',
            NetworkAttributes::NETWORK_PROTOCOL_VERSION => match (true) {
                $protocolVersion->isEmpty()     => null,
                $protocolVersion->contains('/') => $protocolVersion->after('/')->toString(),
                default                         => $protocolVersion->toString(),
            },
            ServerAttributes::SERVER_ADDRESS => $request->getHttpHost(),
            ServerAttributes::SERVER_PORT    => $request->getPort(),
        ];
    }

    protected function recordHeaders(SpanInterface $span, Request|Response $http): void
    {
        $prefix = match (true) {
            $http instanceof Request  => 'http.request.header.',
            $http instanceof Response => 'http.response.header.',
        };

        foreach ($http->headers->all() as $key => $value) {
            $key = strtolower($key);

            if (! HttpServerInstrumentation::headerIsAllowed($key)) {
                continue;
            }

            $value = HttpServerInstrumentation::headerIsSensitive($key) ? ['*****'] : $value;

            $span->setAttribute($prefix.$key, $value);
        }
    }

    /**
     * @return int Timestamp in nanoseconds when the application booted
     */
    protected function requestStartTimestamp(Request $request): int
    {
        if ($request->server->has('REQUEST_TIME_FLOAT')) {
            return (int) (floatval($request->server('REQUEST_TIME_FLOAT')) * ClockInterface::NANOS_PER_SECOND); // Convert seconds to nanoseconds
        }

        if (defined('LARAVEL_START')) {
            return (int) (LARAVEL_START * ClockInterface::NANOS_PER_SECOND); // Convert seconds to nanoseconds
        }

        return Clock::getDefault()->now();
    }

    protected function resolveRouteName(Request $request): ?string
    {
        /** @var string|null $route */
        $route = rescue(fn () => Route::getRoutes()->match($request)->uri(), rescue: false);

        return $route !== null ? '/'.ltrim($route, '/') : null;
    }
}
