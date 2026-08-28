import { router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Check,
    Copy,
    Database,
    PanelRight,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { MouseEvent } from 'react';
import { EmptyState } from '@/components/organisms/empty-state';
import { QueryDetailContent } from '@/components/organisms/query-detail';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDurationLong, formatDurationMs } from '@/lib/datetime';
import { SqlHighlight } from '@/lib/sql-highlight';
import { formatCompactNumber } from '@/lib/utils';
import { show as showQuery } from '@/routes/projects/queries';
import type { QueryDetail, QueryStat } from '@/types/observability';

type SortKey = Exclude<keyof QueryStat, 'query' | 'fingerprint' | 'system'>;

const COLUMNS: { key: SortKey; label: string }[] = [
    { key: 'calls', label: 'Calls' },
    { key: 'total_ms', label: 'Total' },
    { key: 'avg_ms', label: 'Avg' },
    { key: 'p95_ms', label: 'P95' },
];

/**
 * The Queries page's main table: every SQL statement run in the window,
 * ranked by how often it ran by default, with its cumulative time and
 * duration percentiles -- this replaces the raw entries list for this one
 * category, since a stream of individual query spans is far less useful
 * here than "which statements run often or run slow" (see QueryStatsQuery).
 * Column order and shape mirror Laravel Nightwatch's own Queries table.
 *
 * A row click navigates to that statement's full detail page; the drawer
 * button fetches the same data as JSON (see ShowQueryController) and shows
 * it in a side panel instead, for a look without leaving the table.
 */
export function QueriesTable({
    projectId,
    queries,
}: {
    projectId: number;
    queries: QueryStat[];
}) {
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState<SortKey>('calls');
    const [sortDesc, setSortDesc] = useState(true);
    const [copiedQuery, setCopiedQuery] = useState<string | null>(null);
    const [drawerFingerprint, setDrawerFingerprint] = useState<string | null>(
        null,
    );
    const [drawerDetail, setDrawerDetail] = useState<QueryDetail | null>(null);
    const [drawerLoading, setDrawerLoading] = useState(false);

    const filtered = useMemo(
        () =>
            queries.filter((query) =>
                query.query.toLowerCase().includes(search.trim().toLowerCase()),
            ),
        [queries, search],
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

    async function copyQuery(event: MouseEvent, query: string) {
        event.stopPropagation();

        try {
            await navigator.clipboard.writeText(query);
            setCopiedQuery(query);
            setTimeout(
                () =>
                    setCopiedQuery((current) =>
                        current === query ? null : current,
                    ),
                1500,
            );
        } catch {
            // Clipboard access can be denied by the browser -- nothing useful to
            // recover to here, so the icon just doesn't flip to a checkmark.
        }
    }

    function openRow(fingerprint: string) {
        router.visit(showQuery({ project: projectId, fingerprint }).url);
    }

    async function openDrawer(event: MouseEvent, fingerprint: string) {
        event.stopPropagation();
        setDrawerFingerprint(fingerprint);
        setDrawerDetail(null);
        setDrawerLoading(true);

        try {
            const response = await fetch(
                showQuery({ project: projectId, fingerprint }).url,
                { headers: { Accept: 'application/json' } },
            );

            if (response.ok) {
                setDrawerDetail((await response.json()) as QueryDetail);
            }
        } finally {
            setDrawerLoading(false);
        }
    }

    return (
        <div>
            <div className="flex items-center justify-between gap-4 px-4 py-3">
                <p className="flex items-center gap-2 text-lg font-medium">
                    <Database className="size-5 text-muted-foreground" />
                    {queries.length.toLocaleString()}{' '}
                    {queries.length === 1 ? 'Query' : 'Queries'}
                </p>

                {queries.length > 0 && (
                    <div className="relative w-full max-w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder="Search queries"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="h-8 pl-8 text-sm"
                        />
                    </div>
                )}
            </div>

            <div className="p-4">
                {queries.length === 0 ? (
                    <EmptyState message="Once your app starts running queries with this project's token, you'll see every statement's volume and duration here." />
                ) : sorted.length === 0 ? (
                    <EmptyState message={`No queries match "${search}".`} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left font-mono text-xs">
                            <thead className="text-muted-foreground">
                                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                    <th className="py-2 pr-4 font-medium tracking-wide uppercase">
                                        Query
                                    </th>
                                    <th className="py-2 pr-4 font-medium tracking-wide uppercase">
                                        Connection
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
                                {sorted.map((query) => (
                                    <tr
                                        key={query.fingerprint}
                                        onClick={() =>
                                            openRow(query.fingerprint)
                                        }
                                        className="cursor-pointer border-b border-sidebar-border/40 last:border-0 hover:bg-accent/50 dark:border-sidebar-border/60"
                                    >
                                        <td className="max-w-xl py-2 pr-4">
                                            <span
                                                className="block truncate"
                                                title={query.query}
                                            >
                                                <SqlHighlight
                                                    sql={query.query}
                                                />
                                            </span>
                                        </td>
                                        <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                                            {query.system ?? '—'}
                                        </td>
                                        <td className="py-2 pr-4 tabular-nums">
                                            <span
                                                className={
                                                    sortKey === 'calls'
                                                        ? 'inline-block rounded bg-muted px-1.5 py-0.5 font-semibold'
                                                        : undefined
                                                }
                                            >
                                                {formatCompactNumber(
                                                    query.calls,
                                                )}
                                            </span>
                                        </td>
                                        <td className="py-2 pr-4 tabular-nums">
                                            {formatDurationLong(query.total_ms)}
                                        </td>
                                        <td className="py-2 pr-4 text-muted-foreground tabular-nums">
                                            {formatDurationMs(query.avg_ms)}
                                        </td>
                                        <td className="py-2 pr-4 tabular-nums">
                                            {formatDurationMs(query.p95_ms)}
                                        </td>
                                        <td className="py-2 pl-2">
                                            <div className="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    onClick={(e) =>
                                                        copyQuery(
                                                            e,
                                                            query.query,
                                                        )
                                                    }
                                                    title="Copy query"
                                                    className="rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                >
                                                    {copiedQuery ===
                                                    query.query ? (
                                                        <Check className="size-3.5" />
                                                    ) : (
                                                        <Copy className="size-3.5" />
                                                    )}
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={(e) =>
                                                        openDrawer(
                                                            e,
                                                            query.fingerprint,
                                                        )
                                                    }
                                                    title="Open details"
                                                    className="rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                >
                                                    <PanelRight className="size-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Sheet
                open={drawerFingerprint !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDrawerFingerprint(null);
                        setDrawerDetail(null);
                    }
                }}
            >
                <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>Query details</SheetTitle>
                    </SheetHeader>

                    <div className="px-4 pb-4">
                        {drawerLoading || !drawerDetail ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Loading…
                            </p>
                        ) : (
                            <QueryDetailContent
                                projectId={projectId}
                                detail={drawerDetail}
                                chartLayout="stacked"
                            />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    );
}
