import { formatTime } from '@/lib/datetime';
import type { MetricEntry } from '@/types/observability';

/**
 * The entries table for the Metrics category -- the only one backed by
 * `otel_metrics` instead of spans or logs, so it has its own name/type/value
 * shape rather than SpansTable's.
 */
export function MetricsTable({ entries }: { entries: MetricEntry[] }) {
    return (
        <table className="w-full text-left text-sm">
            <thead className="text-muted-foreground">
                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                    <th className="py-2 pr-4 font-medium">Time</th>
                    <th className="py-2 pr-4 font-medium">Name</th>
                    <th className="py-2 pr-4 font-medium">Type</th>
                    <th className="py-2 font-medium">Value</th>
                </tr>
            </thead>
            <tbody>
                {entries.map((entry, i) => (
                    <tr
                        key={`${entry.name}-${i}`}
                        className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/60"
                    >
                        <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                            {formatTime(entry.time)}
                        </td>
                        <td className="py-2 pr-4">{entry.name ?? '—'}</td>
                        <td className="py-2 pr-4">{entry.type ?? '—'}</td>
                        <td className="py-2">
                            {entry.value !== null ? entry.value : '—'}
                            {entry.unit ? ` ${entry.unit}` : ''}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
