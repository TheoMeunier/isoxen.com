<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Support\Facades\Date;

/**
 * Approximates who is online: an `enduser.id` whose latest login/logout span is a login counts as online.
 */
class OnlineUsersQuery
{
    /**
     * @return array<int, array{id: string, name: string|null, email: string|null, since: string}>
     */
    public function execute(Project $project, int $days = 30): array
    {
        $since = Date::now('UTC')->subDays($days);

        /** @var array<string, array{id: string, name: string|null, email: string|null, since: string, online: bool}> $latest */
        $latest = [];

        $project->spans()
            ->where('type', 'user')
            ->where('time', '>=', $since)
            ->whereNotNull('attributes')
            ->select(['attributes', 'time'])
            ->orderBy('time')
            ->chunk(500, function ($spans) use (&$latest): void {
                foreach ($spans as $span) {
                    $attributes = $span->attributes ?? [];
                    $id = $attributes['enduser.id'] ?? null;

                    if (!is_string($id) && !is_numeric($id)) {
                        continue;
                    }

                    $latest[(string)$id] = [
                        'id' => (string)$id,
                        'name' => is_string($attributes['user.name'] ?? null) ? $attributes['user.name'] : null,
                        'email' => is_string($attributes['user.email'] ?? null) ? $attributes['user.email'] : null,
                        'since' => $span->time->format('Y-m-d\TH:i:s.uP'),
                        'online' => ($attributes['isoxen.user.operation'] ?? null) === 'login',
                    ];
                }
            });

        return array_values(array_map(
            fn(array $user): array => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'since' => $user['since'],
            ],
            array_filter($latest, fn(array $user): bool => $user['online']),
        ));
    }
}
