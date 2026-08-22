import { LogSeverityBadge } from '@/components/molecules/log-severity-badge';
import { formatTime } from '@/lib/datetime';
import type { LogEntry } from '@/types/observability';

/**
 * The entries table for the Logs category -- the only one backed by
 * `otel_logs` instead of spans or metrics, so it has its own
 * severity/body shape rather than SpansTable's.
 */
export function LogsTable({ entries }: { entries: LogEntry[] }) {
    return (
        <table className="w-full text-left text-sm">
            <thead className="text-muted-foreground">
                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                    <th className="py-2 pr-4 font-medium">Time</th>
                    <th className="py-2 pr-4 font-medium">Severity</th>
                    <th className="py-2 font-medium">Message</th>
                </tr>
            </thead>
            <tbody>
                {entries.map((entry, i) => (
                    <tr
                        key={`${entry.trace_id}-${i}`}
                        className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/60"
                    >
                        <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                            {formatTime(entry.time)}
                        </td>
                        <td className="py-2 pr-4">
                            <LogSeverityBadge entry={entry} />
                        </td>
                        <td className="py-2">{entry.body ?? '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
