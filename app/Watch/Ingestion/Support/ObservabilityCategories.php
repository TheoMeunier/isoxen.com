<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

/**
 * Single source of truth for the project sidebar's categories (mirrors
 * Laravel Nightwatch's "Activity"/"Monitoring" layout).
 *
 * Each entry maps a URL-facing `slug` to where its data actually comes
 * from:
 * - `source: 'span'` reads from `otel_spans` filtered by the given `type`
 *   (populated from the `isoxen.type` attribute -- see OtlpSpansParser).
 * - `source: 'logs'` / `'metrics'` read from their respective tables as-is.
 *
 * `enabled: false` marks a category the UI shows (to match the reference
 * layout) but that isn't wired to real data yet, because it requires
 * instrumentation isoxen's own client doesn't implement yet (e.g. hooking
 * Artisan commands or the scheduler). These render disabled in the sidebar.
 *
 * NOTE: the frontend (resources/js/pages/projects/show.tsx) currently
 * duplicates this list for icons/labels since there's no shared codegen
 * between this PHP array and TypeScript. Keep the two in sync by hand until
 * that's worth automating.
 */
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
            'commands' => ['group' => 'activity', 'label' => 'Commands', 'source' => 'span', 'type' => 'command', 'enabled' => false],
            'scheduled-tasks' => ['group' => 'activity', 'label' => 'Scheduled Tasks', 'source' => 'span', 'type' => 'scheduled_task', 'enabled' => false],
            'exceptions' => ['group' => 'activity', 'label' => 'Exceptions', 'source' => 'span', 'type' => 'exception', 'enabled' => true],
            'queries' => ['group' => 'activity', 'label' => 'Queries', 'source' => 'span', 'type' => 'query', 'enabled' => true],
            'notifications' => ['group' => 'activity', 'label' => 'Notifications', 'source' => 'span', 'type' => 'notification', 'enabled' => false],
            'mail' => ['group' => 'activity', 'label' => 'Mail', 'source' => 'span', 'type' => 'mail', 'enabled' => false],
            'cache' => ['group' => 'activity', 'label' => 'Cache', 'source' => 'span', 'type' => 'cache', 'enabled' => false],
            'outgoing-requests' => ['group' => 'activity', 'label' => 'Outgoing Requests', 'source' => 'span', 'type' => 'outgoing_request', 'enabled' => true],
            'metrics' => ['group' => 'activity', 'label' => 'Metrics', 'source' => 'metrics', 'type' => null, 'enabled' => true],
            'users' => ['group' => 'monitoring', 'label' => 'Users', 'source' => 'span', 'type' => 'user', 'enabled' => false],
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
}
