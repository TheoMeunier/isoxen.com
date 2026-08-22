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
 * layout) but that isn't wired to real data yet. Every category is enabled
 * as of the client gaining instrumentation for commands, the scheduler,
 * mail, notifications, cache and users; the flag remains for categories
 * added here before their sensor exists, which render disabled.
 *
 * An enabled category can still be legitimately empty: Cache is off by
 * default in the client (ISOXEN_SENSOR_CACHE) because a cache-heavy request
 * emits hundreds of spans, and Users only fills on login/logout events.
 *
 * This is also the list the {category} route segment is constrained against
 * (see routes/projects.php) and, via HandleInertiaRequests, what the app
 * sidebar (resources/js/components/organisms/sidebar/nav-project.tsx) reads
 * to build its nav -- this array is the only source of truth for the
 * slug/label/type/enabled data at runtime. The frontend still keeps its own
 * local slug -> icon map, since an icon has no PHP-side equivalent.
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

    /**
     * The table a category's entries are read from.
     */
    public static function table(string $slug): string
    {
        return match (self::get($slug)['source']) {
            'metrics' => 'otel_metrics',
            'logs' => 'otel_logs',
            default => 'otel_spans',
        };
    }
}
