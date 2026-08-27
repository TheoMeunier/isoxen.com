import { Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/molecules/status-badge';
import { formatDuration, formatTime } from '@/lib/datetime';
import { show as showTrace } from '@/routes/projects/traces';
import type { SpanEntry } from '@/types/observability';

const SPAN_KINDS: Record<number, string> = {
    0: 'Unspecified',
    1: 'Internal',
    2: 'Server',
    3: 'Client',
    4: 'Producer',
    5: 'Consumer',
};

/**
 * The entries table shared by every span-backed category except Requests,
 * which shows the Routes table instead (see RoutesTable) -- Jobs, Commands,
 * Scheduled Tasks, Exceptions, Queries, Notifications, Mail, Cache,
 * Outgoing Requests, and Users. Each category's `name` is already the whole
 * story, or has a `detail` field carrying it instead (see
 * ObservabilityCategories' span parsers).
 */
export function SpansTable({
    entries,
    projectId,
}: {
    entries: SpanEntry[];
    projectId: number;
}) {
    return (
        <table className="w-full text-left text-sm">
            <thead className="text-muted-foreground">
                <tr className="border-sidebar-border/70 dark:border-sidebar-border border-b">
                    <th className="py-2 pr-4 font-medium">Time</th>
                    <th className="py-2 pr-4 font-medium">Name</th>
                    <th className="py-2 pr-4 font-medium">Kind</th>
                    <th className="py-2 pr-4 font-medium">Duration</th>
                    <th className="py-2 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                {entries.map((entry, i) => {
                    const isDetailed = entry.detail != null;
                    const name = entry.detail ?? entry.name ?? '—';
                    const nameContent = isDetailed ? (
                        <span
                            className="block max-w-[42rem] truncate font-mono text-xs"
                            title={name}
                        >
                            {name}
                        </span>
                    ) : (
                        name
                    );

                    return (
                        <tr
                            key={`${entry.trace_id}-${entry.span_id}-${i}`}
                            className="border-sidebar-border/40 dark:border-sidebar-border/60 border-b last:border-0"
                        >
                            <td className="text-muted-foreground whitespace-nowrap py-2 pr-4">
                                {formatTime(entry.time)}
                            </td>
                            <td className="py-2 pr-4">
                                {entry.trace_id ? (
                                    <Link
                                        href={showTrace.url({
                                            project: projectId,
                                            trace: entry.trace_id,
                                        })}
                                        className="hover:underline"
                                    >
                                        {nameContent}
                                    </Link>
                                ) : (
                                    nameContent
                                )}
                            </td>
                            <td className="py-2 pr-4">
                                {entry.kind !== null
                                    ? (SPAN_KINDS[entry.kind] ?? entry.kind)
                                    : '—'}
                            </td>
                            <td className="py-2 pr-4">
                                {formatDuration(entry.duration_nanos)}
                            </td>
                            <td className="py-2">
                                <StatusBadge code={entry.status_code} />
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}
