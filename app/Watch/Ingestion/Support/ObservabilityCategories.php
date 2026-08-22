<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

final class ObservabilityCategories
{
    /**
     * @return array<string, array{group: string, label: string, source: string, type: ?string, enabled: bool}>
     */
    public static function all(): array
    {
        return [
            'requests' => ['group' => 'activity', 'label' => 'Requests', 'source' => 'span', 'type' => 'request', 'enabled' => true],
            'jobs' => ['group' => 'activity', 'label' => 'Jobs', 'source' => 'span', 'type' => 'job', 'enabled' => true],
            'commands' => ['group' => 'activity', 'label' => 'Commands', 'source' => 'span', 'type' => 'command', 'enabled' => true],
            'scheduled-tasks' => ['group' => 'activity', 'label' => 'Scheduled Tasks', 'source' => 'span', 'type' => 'scheduled_task', 'enabled' => true],
            'exceptions' => ['group' => 'activity', 'label' => 'Exceptions', 'source' => 'span', 'type' => 'exception', 'enabled' => true],
            'queries' => ['group' => 'activity', 'label' => 'Queries', 'source' => 'span', 'type' => 'query', 'enabled' => true],
            'notifications' => ['group' => 'activity', 'label' => 'Notifications', 'source' => 'span', 'type' => 'notification', 'enabled' => true],
            'mail' => ['group' => 'activity', 'label' => 'Mail', 'source' => 'span', 'type' => 'mail', 'enabled' => true],
            'cache' => ['group' => 'activity', 'label' => 'Cache', 'source' => 'span', 'type' => 'cache', 'enabled' => true],
            'outgoing-requests' => ['group' => 'activity', 'label' => 'Outgoing Requests', 'source' => 'span', 'type' => 'outgoing_request', 'enabled' => true],
            'metrics' => ['group' => 'activity', 'label' => 'Metrics', 'source' => 'metrics', 'type' => null, 'enabled' => true],
            'users' => ['group' => 'monitoring', 'label' => 'Users', 'source' => 'span', 'type' => 'user', 'enabled' => true],
            'logs' => ['group' => 'monitoring', 'label' => 'Logs', 'source' => 'logs', 'type' => null, 'enabled' => true],
        ];
    }

    public static function isValid(string $slug): bool
    {
        return array_key_exists($slug, self::all());
    }

    /**
     * @return array{group: string, label: string, source: string, type: ?string, enabled: bool}
     */
    public static function get(string $slug): array
    {
        return self::all()[$slug];
    }

    public static function default(): string
    {
        return 'requests';
    }

    public static function table(string $slug): string
    {
        return match (self::get($slug)['source']) {
            'metrics' => 'otel_metrics',
            'logs' => 'otel_logs',
            default => 'otel_spans',
        };
    }
}
