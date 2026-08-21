import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { EndpointStat } from '@/types/observability';

type SortKey = Exclude<keyof EndpointStat, 'name'>;

const COLUMNS: { key: SortKey; label: string }[] = [
    { key: 'total', label: 'Requests' },
    { key: 'errors', label: 'Errors' },
    { key: 'avg_ms', label: 'Avg' },
    { key: 'p50_ms', label: 'p50' },
    { key: 'p95_ms', label: 'p95' },
    { key: 'p99_ms', label: 'p99' },
];

function formatMs(ms: number): string {
    return ms < 1 ? `${ms.toFixed(2)} ms` : `${ms.toFixed(1)} ms`;
}

/**
 * Every endpoint the Requests category has seen recently, ranked by how
 * slow it is rather than how recently it fired -- the question a plain
 * "most recent requests" table can't answer without reading every row by
 * eye. Sorted by p95 by default, since a handful of slow outliers would
 * otherwise hide behind a healthy average.
 */
export function SlowEndpointsTable({
    endpoints,
}: {
    endpoints: EndpointStat[];
}) {
    const [sortKey, setSortKey] = useState<SortKey>('p95_ms');
    const [sortDesc, setSortDesc] = useState(true);

    const sorted = useMemo(() => {
        return [...endpoints].sort((a, b) =>
            sortDesc ? b[sortKey] - a[sortKey] : a[sortKey] - b[sortKey],
        );
    }, [endpoints, sortKey, sortDesc]);

    function toggleSort(key: SortKey) {
        if (key === sortKey) {
            setSortDesc((desc) => !desc);
        } else {
            setSortKey(key);
            setSortDesc(true);
        }
    }

    return (
        <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <div className="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <p className="font-medium">Slowest endpoints</p>
                <p className="text-sm text-muted-foreground">
                    Grouped by endpoint over the last 24 hours, ranked by p95
                    duration.
                </p>
            </div>

            <div className="overflow-x-auto p-4">
                <table className="w-full text-left text-sm">
                    <thead className="text-muted-foreground">
                        <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <th className="py-2 pr-4 font-medium">Endpoint</th>
                            {COLUMNS.map((column) => (
                                <th
                                    key={column.key}
                                    className="py-2 pr-4 font-medium"
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleSort(column.key)}
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
                        </tr>
                    </thead>
                    <tbody>
                        {sorted.map((endpoint) => (
                            <tr
                                key={endpoint.name}
                                className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/60"
                            >
                                <td className="max-w-xs truncate py-2 pr-4 font-medium">
                                    {endpoint.name}
                                </td>
                                <td className="py-2 pr-4 tabular-nums">
                                    {endpoint.total}
                                </td>
                                <td className="py-2 pr-4 tabular-nums">
                                    {endpoint.errors > 0 ? (
                                        <span className="text-[var(--color-tone-critical)]">
                                            {endpoint.errors}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            0
                                        </span>
                                    )}
                                </td>
                                <td className="py-2 pr-4 text-muted-foreground tabular-nums">
                                    {formatMs(endpoint.avg_ms)}
                                </td>
                                <td className="py-2 pr-4 text-muted-foreground tabular-nums">
                                    {formatMs(endpoint.p50_ms)}
                                </td>
                                <td className="py-2 pr-4 tabular-nums">
                                    {formatMs(endpoint.p95_ms)}
                                </td>
                                <td className="py-2 pr-4 text-muted-foreground tabular-nums">
                                    {formatMs(endpoint.p99_ms)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
