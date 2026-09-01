<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Support\ExceptionFingerprint;
use App\Watch\Ingestion\Support\OtlpAttributes;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * A single exception's full detail, addressed by the fingerprint
 * ExceptionGroupsQuery groups the Exceptions table by: every occurrence
 * within the lookback window, so the detail page can show first/last seen,
 * volume over a few windows, and the most recent stack trace -- without a
 * dedicated exception-groups table, since a project's exception volume is
 * low enough that re-scanning the window per page view is cheap (mirrors
 * RouteStatsQuery/ExceptionGroupsQuery's chunked-scan approach).
 */
class ExceptionOccurrencesQuery
{
    /**
     * @return array{type: string, message: string|null, file: string|null, line: int|null, http_status: int|null, occurrences_24h: int, occurrences_7d: int, occurrences_30d: int, first_seen: string, last_seen: string, first_trace_id: string|null, last_trace_id: string|null, stacktrace: string|null}|null
     */
    public function execute(Project $project, string $fingerprint, int $lookbackDays = 30): ?array
    {
        $since = Date::now()->subDays($lookbackDays);
        $day7  = Date::now()->subDays(7);
        $day1  = Date::now()->subHours(24);

        $type           = null;
        $message        = null;
        $file           = null;
        $line           = null;
        $httpStatus     = null;
        $occurrences24h = 0;
        $occurrences7d  = 0;
        $occurrences30d = 0;
        $firstSeen      = null;
        $lastSeen       = null;
        $firstTraceId   = null;
        $lastTraceId    = null;
        $stacktrace     = null;

        OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->where('type', 'exception')
            ->where('time', '>=', $since)
            ->select('time', 'attributes', 'trace_id', 'raw')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (
                &$type,
                &$message,
                &$file,
                &$line,
                &$httpStatus,
                &$occurrences24h,
                &$occurrences7d,
                &$occurrences30d,
                &$firstSeen,
                &$lastSeen,
                &$firstTraceId,
                &$lastTraceId,
                &$stacktrace,
                $fingerprint,
                $day7,
                $day1,
            ): void {
                foreach ($rows as $row) {
                    $attributes = json_decode((string) $row->attributes, true) ?? [];
                    $rowType    = $attributes['exception.type']                ?? null;

                    if (! is_string($rowType) || $rowType === '') {
                        continue;
                    }

                    $rowMessage    = $attributes['exception.message'] ?? null;
                    $rowMessage    = is_string($rowMessage) ? $rowMessage : null;
                    $rowFile       = $attributes['code.file.path'] ?? null;
                    $rowFile       = is_string($rowFile) ? $rowFile : null;
                    $rowLine       = $attributes['code.line.number'] ?? null;
                    $rowLine       = is_numeric($rowLine) ? (int) $rowLine : null;
                    $rowHttpStatus = $attributes['http.response.status_code'] ?? null;
                    $rowHttpStatus = is_numeric($rowHttpStatus) ? (int) $rowHttpStatus : null;

                    if (ExceptionFingerprint::make($rowType, $rowFile, $rowLine, $rowMessage) !== $fingerprint) {
                        continue;
                    }

                    /** @var Carbon $time */
                    $time = Date::parse((string) $row->time);
                    $occurrences30d++;

                    if ($time->gte($day7)) {
                        $occurrences7d++;
                    }

                    if ($time->gte($day1)) {
                        $occurrences24h++;
                    }

                    if ($firstSeen === null || $time->lt($firstSeen)) {
                        $firstSeen    = $time;
                        $firstTraceId = $row->trace_id;
                    }

                    if ($lastSeen === null || $time->gte($lastSeen)) {
                        $lastSeen    = $time;
                        $lastTraceId = $row->trace_id;
                        $type        = $rowType;
                        $message     = $rowMessage;
                        $file        = $rowFile;
                        $line        = $rowLine;
                        $httpStatus  = $rowHttpStatus;
                        $stacktrace  = $this->extractStacktrace(json_decode((string) $row->raw, true) ?? []);
                    }
                }
            });

        if (!$firstSeen instanceof \Illuminate\Support\Carbon || !$lastSeen instanceof \Illuminate\Support\Carbon || $type === null) {
            return null;
        }

        return [
            'type'            => $type,
            'message'         => $message,
            'file'            => $file,
            'line'            => $line,
            'http_status'     => $httpStatus,
            'occurrences_24h' => $occurrences24h,
            'occurrences_7d'  => $occurrences7d,
            'occurrences_30d' => $occurrences30d,
            'first_seen'      => $firstSeen->toIso8601String(),
            'last_seen'       => $lastSeen->toIso8601String(),
            'first_trace_id'  => $firstTraceId,
            'last_trace_id'   => $lastTraceId,
            'stacktrace'      => $stacktrace,
        ];
    }

    /**
     * The stack trace lives as an OTel span *event* (from
     * $span->recordException(), semconv `exception.stacktrace`), not a span
     * attribute -- separate from exception.type/message/code.file.path,
     * which the client sets directly on the span. See
     * vendor/open-telemetry/sdk/Trace/Span.php's recordException().
     *
     * @param  array<string, mixed>  $raw
     */
    private function extractStacktrace(array $raw): ?string
    {
        $events = $raw['events'] ?? [];

        if (! is_array($events)) {
            return null;
        }

        foreach ($events as $event) {
            if (! is_array($event) || ($event['name'] ?? null) !== 'exception') {
                continue;
            }

            $attributes = OtlpAttributes::toArray($event['attributes'] ?? []);
            $stacktrace = $attributes['exception.stacktrace'] ?? null;

            return is_string($stacktrace) ? $stacktrace : null;
        }

        return null;
    }
}
