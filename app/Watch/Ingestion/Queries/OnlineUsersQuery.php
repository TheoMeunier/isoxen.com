<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Queries;

use App\Watch\Projects\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OnlineUsersQuery
{
    /**
     * @return array<int, array{id: string, name: string|null, email: string|null, since: string}>
     */
    public function execute(Project $project, int $days = 30): array
    {
        $since = \Illuminate\Support\Facades\Date::now('UTC')->subDays($days);

        /** @var array<string, array{id: string, name: string|null, email: string|null, since: string, online: bool}> $latest */
        $latest = [];

        DB::table('otel_spans')
            ->where('project_id', $project->id)
            ->where('type', 'user')
            ->where('time', '>=', $since)
            ->whereNotNull('attributes')
            ->select(['attributes', 'time'])
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
                        'since'  => \Illuminate\Support\Facades\Date::parse($row->time)->format('Y-m-d\TH:i:s.uP'),
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
