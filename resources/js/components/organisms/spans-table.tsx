import { Link } from '@inertiajs/react';
import {
    MethodBadge,
    parseHttpMethod,
    stripHttpMethod,
} from '@/components/molecules/method-badge';
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
 * The entries table shared by every span-backed category (Requests, Jobs,
 * Commands, Scheduled Tasks, Exceptions, Queries, Notifications, Mail,
 * Cache, Outgoing Requests, Users). Requests is the only category that
 * shows an HTTP method column -- everything else's `name` is already the
 * whole story, or has a `detail` field carrying it instead (see
 * ObservabilityCategories' span parsers).
 */
export function SpansTable({
    entries,
    projectId,
    showMethod,
}: {
    entries: SpanEntry[];
    projectId: number;
    showMethod: boolean;
}) {
    return (
        <table className="w-full text-left text-sm">
            <thead className="text-muted-foreground">
                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                    <th className="py-2 pr-4 font-medium">Time</th>
                    {showMethod && (
                        <th className="py-2 pr-4 font-medium">Method</th>
                    )}
                    <th className="py-2 pr-4 font-medium">Name</th>
                    <th className="py-2 pr-4 font-medium">Kind</th>
                    <th className="py-2 pr-4 font-medium">Duration</th>
                    <th className="py-2 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                {entries.map((entry, i) => {
                    const method = showMethod
                        ? parseHttpMethod(entry.name)
                        : null;
                    const isDetailed = !showMethod && entry.detail != null;
                    const name = showMethod
                        ? stripHttpMethod(entry.name, method)
                        : (entry.detail ?? entry.name ?? '—');
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
                            className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/60"
                        >
                            <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                                {formatTime(entry.time)}
                            </td>
                            {showMethod && (
                                <td className="py-2 pr-4">
                                    <MethodBadge method={method} />
                                </td>
                            )}
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
