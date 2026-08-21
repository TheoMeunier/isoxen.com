<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Who's connected right now, inferred from the Users tab's own login/logout
 * spans (see UserInstrumentation): for each `enduser.id`, whichever event is
 * most recent within the window decides their status -- a login with no
 * logout since counts as online.
 *
 * This is an approximation, not a real session check: isoxen only sees the
 * login/logout spans the monitored application sends, never its actual
 * session store. A session that silently expires, or a tab closed without
 * ever firing a "Logout" event, leaves that user listed here as online
 * until some other event for them is recorded, or their last event falls
 * out of the window below. Good enough for "who's around", not a
 * concurrency/licensing count.
 */
class OnlineUsersQuery
{
    /**
     * @return array<int, array{id: string, name: string|null, email: string|null, since: string}>
     */
    public function execute(Project $project, int $days = 30): array
    {
        $since = Carbon::now('UTC')->subDays($days);

        /** @var array<string, array{id: string, name: string|null, email: string|null, since: string, online: bool}> $latest */
        $latest = [];

        DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->where('type', 'user')
            ->where('time', '>=', $since)
            ->whereNotNull('attributes')
            ->select(['attributes', 'time'])
            // Ascending, deliberately: scanned oldest to newest so that
            // overwriting `$latest[$id]` below always leaves each user's
            // *most recent* event standing, with no extra "is this newer"
            // check needed.
            ->orderBy('time')
            ->chunk(500, function ($rows) use (&$latest): void {
                foreach ($rows as $row) {
                    $attributes = json_decode((string) $row->attributes, true);
                    $id         = $attributes['enduser.id'] ?? null;

                    if (! is_string($id) && ! is_numeric($id)) {
                        continue;
                    }

                    $latest[(string) $id] = [
                        'id'     => (string) $id,
                        'name'   => is_string($attributes['user.name'] ?? null) ? $attributes['user.name'] : null,
                        'email'  => is_string($attributes['user.email'] ?? null) ? $attributes['user.email'] : null,
                        'since'  => Carbon::parse($row->time)->format('Y-m-d\TH:i:s.uP'),
                        'online' => ($attributes['isoxen.user.operation'] ?? null) === 'login',
                    ];
                }
            });

        return array_values(array_map(
            fn (array $user): array => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'since' => $user['since'],
            ],
            array_filter($latest, fn (array $user): bool => $user['online']),
        ));
    }
}
