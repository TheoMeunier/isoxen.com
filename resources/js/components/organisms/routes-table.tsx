import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Check,
    Copy,
    Globe,
    Search,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { MethodBadge } from '@/components/molecules/method-badge';
import { EmptyState } from '@/components/organisms/empty-state';
import { Input } from '@/components/ui/input';
import { formatDurationMs } from '@/lib/datetime';
import type { RouteStat } from '@/types/observability';

type SortKey = Exclude<keyof RouteStat, 'methods' | 'path'>;

const COLUMNS: { key: SortKey; label: string }[] = [
    { key: 'success', label: '1/2/3XX' },
    { key: 'client_error', label: '4XX' },
    { key: 'server_error', label: '5XX' },
    { key: 'total', label: 'Total' },
    { key: 'avg_ms', label: 'Avg' },
    { key: 'p95_ms', label: 'P95' },
];

/**
 * The Requests page's main table: every route hit in the window, ranked by
 * traffic by default, with its status-class breakdown and duration
 * percentiles -- this replaces the raw entries list for this one category,
 * since a stream of individual requests is far less useful here than "which
 * routes are hot and which are erroring" (see RouteStatsQuery).
 */
export function RoutesTable({ routes }: { routes: RouteStat[] }) {
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState<SortKey>('total');
    const [sortDesc, setSortDesc] = useState(true);
    const [copiedPath, setCopiedPath] = useState<string | null>(null);

    const filtered = useMemo(
        () =>
            routes.filter((route) =>
                route.path.toLowerCase().includes(search.trim().toLowerCase()),
            ),
        [routes, search],
    );

    const sorted = useMemo(
        () =>
            [...filtered].sort((a, b) =>
                sortDesc ? b[sortKey] - a[sortKey] : a[sortKey] - b[sortKey],
            ),
        [filtered, sortKey, sortDesc],
    );

    function toggleSort(key: SortKey) {
        if (key === sortKey) {
            setSortDesc((desc) => !desc);
        } else {
            setSortKey(key);
            setSortDesc(true);
        }
    }

    async function copyPath(path: string) {
        try {
            await navigator.clipboard.writeText(path);
            setCopiedPath(path);
            setTimeout(
                () =>
                    setCopiedPath((current) =>
                        current === path ? null : current,
                    ),
                1500,
            );
        } catch {
            // Clipboard access can be denied by the browser -- nothing useful to
            // recover to here, so the icon just doesn't flip to a checkmark.
        }
    }

    return (
        <div>
            <div className="flex items-center justify-between gap-4 px-4 py-3">
                <p className="flex items-center gap-2 text-lg font-medium">
                    <Globe className="size-5 text-muted-foreground" />
                    {routes.length.toLocaleString()}{' '}
                    {routes.length === 1 ? 'Route' : 'Routes'}
                </p>

                {routes.length > 0 && (
                    <div className="relative w-full max-w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder="Search routes"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="h-8 pl-8 text-sm"
                        />
                    </div>
                )}
            </div>

            <div className="p-4">
                {routes.length === 0 ? (
                    <EmptyState message="Once your app starts sending requests with this project's token, you'll see every route's traffic and duration here." />
                ) : sorted.length === 0 ? (
                    <EmptyState message={`No routes match "${search}".`} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left font-mono text-xs">
                            <thead className="text-muted-foreground">
                                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                    <th className="py-2 pr-4 font-medium tracking-wide uppercase">
                                        Method
                                    </th>
                                    <th className="py-2 pr-4 font-medium tracking-wide uppercase">
                                        Path
                                    </th>
                                    {COLUMNS.map((column) => (
                                        <th
                                            key={column.key}
                                            className="py-2 pr-4 font-medium tracking-wide uppercase"
                                        >
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggleSort(column.key)
                                                }
                                                className="inline-flex items-center gap-1 hover:text-foreground"
                                            >
                                                {column.label}
                                                {sortKey === column.key ? (
                                                    sortDesc ? (
                                                        <ArrowDown className="size-3" />
                                                    ) : (
                                                        <ArrowUp className="size-3" />
                                                    )
                                                ) : (
                                                    <ArrowUpDown className="size-3 opacity-40" />
                                                )}
                                            </button>
                                        </th>
                                    ))}
                                    <th className="py-2 font-medium" />
                                </tr>
                            </thead>
                            <tbody>
                                {sorted.map((route) => (
                                    <tr
                                        key={route.path}
                                        className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/60"
                                    >
                                        <td className="py-2 pr-4 whitespace-nowrap">
                                            <div className="flex flex-wrap gap-1">
                                                {route.methods
                                                    .split('|')
                                                    .map((method) => (
                                                        <MethodBadge
                                                            key={method}
                                                            method={method}
                                                        />
                                                    ))}
                                            </div>
                                        </td>
                                        <td className="max-w-xs py-2 pr-4">
                                            <span className="flex items-center gap-1.5 truncate font-medium">
                                                <Globe className="size-3.5 shrink-0 text-muted-foreground" />
                                                <span
                                                    className="truncate"
                                                    title={route.path}
                                                >
                                                    {route.path}
                                                </span>
                                            </span>
                                        </td>
                                        <td className="py-2 pr-4 tabular-nums">
                                            {route.success.toLocaleString()}
                                        </td>
                                        <td className="py-2 pr-4 tabular-nums">
                                            {route.client_error > 0 ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-[var(--color-tone-warning)]/10 px-2 py-0.5 font-semibold text-[var(--color-tone-warning-contrast)] dark:bg-[var(--color-tone-warning)]/15">
                                                    <TriangleAlert className="size-3 shrink-0" />
                                                    {route.client_error.toLocaleString()}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    0
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4 tabular-nums">
                                            {route.server_error > 0 ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-[var(--color-tone-critical)]/10 px-2 py-0.5 font-semibold text-[var(--color-tone-critical)] dark:bg-[var(--color-tone-critical)]/15">
                                                    <TriangleAlert className="size-3 shrink-0" />
                                                    {route.server_error.toLocaleString()}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    0
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4 font-medium tabular-nums">
                                            {route.total.toLocaleString()}
                                        </td>
                                        <td className="py-2 pr-4 text-muted-foreground tabular-nums">
                                            {formatDurationMs(route.avg_ms)}
                                        </td>
                                        <td className="py-2 pr-4 tabular-nums">
                                            {formatDurationMs(route.p95_ms)}
                                        </td>
                                        <td className="py-2 pl-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    copyPath(route.path)
                                                }
                                                title="Copy path"
                                                className="rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                                            >
                                                {copiedPath === route.path ? (
                                                    <Check className="size-3.5" />
                                                ) : (
                                                    <Copy className="size-3.5" />
                                                )}
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}
