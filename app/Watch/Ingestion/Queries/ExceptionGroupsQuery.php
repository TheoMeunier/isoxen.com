<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Ingestion\Models\OtelSpan;
use App\Watch\Ingestion\Support\ExceptionFingerprint;
use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * The Exceptions page's main table: every distinct exception fingerprinted
 * by type+file+line (see ExceptionFingerprint) and grouped across its
 * occurrences in the window, ranked by last seen -- so a failure that keeps
 * recurring surfaces at the top instead of being buried in a stream of
 * individual entries the way the generic activity list would show it.
 */
class ExceptionGroupsQuery
{
    /**
     * @return Collection<int, object{fingerprint: string, type: string, message: string|null, file: string|null, line: int|null, http_status: int|null, occurrences: int, first_seen: string, last_seen: string, trace_id: string|null}>
     */
    public function execute(Project $project, int $hours = 24): Collection
    {
        $since = Date::now()->subHours($hours);

        /** @var array<string, array{type: string, message: string|null, file: string|null, line: int|null, http_status: int|null, occurrences: int, first_seen: Carbon, last_seen: Carbon, trace_id: string|null}> $groups */
        $groups = [];

        OtelSpan::query()->toBase()
            ->where('project_id', $project->id)
            ->where('type', 'exception')
            ->where('time', '>=', $since)
            ->select('time', 'attributes', 'trace_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$groups): void {
                foreach ($rows as $row) {
                    $attributes = json_decode((string) $row->attributes, true) ?? [];
                    $type       = $attributes['exception.type']                ?? null;

                    if (! is_string($type) || $type === '') {
                        continue;
                    }

                    $message    = $attributes['exception.message'] ?? null;
                    $message    = is_string($message) ? $message : null;
                    $file       = $attributes['code.file.path'] ?? null;
                    $file       = is_string($file) ? $file : null;
                    $line       = $attributes['code.line.number'] ?? null;
                    $line       = is_numeric($line) ? (int) $line : null;
                    $httpStatus = $attributes['http.response.status_code'] ?? null;
                    $httpStatus = is_numeric($httpStatus) ? (int) $httpStatus : null;

                    $fingerprint = ExceptionFingerprint::make($type, $file, $line, $message);
                    $time        = Date::parse((string) $row->time);
                    $traceId     = is_string($row->trace_id) ? $row->trace_id : null;

                    $groups[$fingerprint] ??= [
                        'type'        => $type,
                        'message'     => $message,
                        'file'        => $file,
                        'line'        => $line,
                        'http_status' => $httpStatus,
                        'occurrences' => 0,
                        'first_seen'  => $time,
                        'last_seen'   => $time,
                        'trace_id'    => $traceId,
                    ];

                    $groups[$fingerprint]['occurrences']++;

                    if ($time->lt($groups[$fingerprint]['first_seen'])) {
                        $groups[$fingerprint]['first_seen'] = $time;
                    }

                    // Keep the most recent occurrence's message/trace/status,
                    // since that's what the row should reflect when the same
                    // fingerprint has drifted in message wording over time.
                    if ($time->gte($groups[$fingerprint]['last_seen'])) {
                        $groups[$fingerprint]['last_seen']   = $time;
                        $groups[$fingerprint]['message']     = $message    ?? $groups[$fingerprint]['message'];
                        $groups[$fingerprint]['http_status'] = $httpStatus ?? $groups[$fingerprint]['http_status'];
                        $groups[$fingerprint]['trace_id']    = $traceId;
                    }
                }
            });

        return collect($groups)
            ->map(fn (array $group, string $fingerprint): object => (object) [
                'fingerprint' => $fingerprint,
                'type'        => $group['type'],
                'message'     => $group['message'],
                'file'        => $group['file'],
                'line'        => $group['line'],
                'http_status' => $group['http_status'],
                'occurrences' => $group['occurrences'],
                'first_seen'  => $group['first_seen']->toIso8601String(),
                'last_seen'   => $group['last_seen']->toIso8601String(),
                'trace_id'    => $group['trace_id'],
            ])
            ->sortByDesc('last_seen')
            ->values();
    }
}
