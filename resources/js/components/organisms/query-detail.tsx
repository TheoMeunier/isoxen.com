import { Link } from '@inertiajs/react';
import { ArrowUpRight, FileCode } from 'lucide-react';
import { StatChartPanel } from '@/components/organisms/stat-chart-panel';
import {
    formatDurationLong,
    formatDurationMs,
    formatTime,
} from '@/lib/datetime';
import { SqlHighlight } from '@/lib/sql-highlight';
import { show as showTrace } from '@/routes/projects/traces';
import type { QueryDetail } from '@/types/observability';

/**
 * "scheduled_task" -> "Scheduled task" -- the raw span `type` values read
 * fine capitalised on their own; there's no separate label table for them
 * the way ObservabilityCategories has one for category slugs.
 */
function formatSpanType(type: string | null): string {
    if (!type) {
        return 'Unknown';
    }

    const spaced = type.replace(/_/g, ' ');

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

/**
 * A receipt-style row: label, a dotted rule filling the gap, then the
 * value -- reads as one line even though the value's width varies row to
 * row, which a plain flex `justify-between` doesn't manage nearly as well
 * once labels are short and values are short too.
 */
function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline gap-2 text-sm">
            <span className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </span>
            <span className="h-px flex-1 translate-y-[-3px] border-b border-dotted border-sidebar-border/70 dark:border-sidebar-border" />
            <span className="font-medium tabular-nums">{value}</span>
        </div>
    );
}

/**
 * One call in the Recent calls list, styled as its own lightly-shaded card
 * rather than a row in a flat divided list -- matching Nightwatch's
 * reference layout, where each call sits in its own rounded block instead
 * of being separated only by a hairline. The two-line time/duration and
 * caller text sits on the left; a small pill button to the triggering
 * request/job sits on the right, top-aligned with the time/duration line
 * rather than centered against the card's full height. Its label
 * (Request, Job, Command...) says what it links to instead of just "go
 * somewhere". It only shows on the full detail page (`showTraceLink`) --
 * in the drawer it competed with the drawer's own close affordance for the
 * same corner, so it's left out there.
 */
function CallRow({
    projectId,
    occurrence,
    showTraceLink = false,
}: {
    projectId: number;
    occurrence: QueryDetail['occurrences'][number];
    showTraceLink?: boolean;
}) {
    const location = occurrence.caller_file
        ? `${occurrence.caller_file}${occurrence.caller_line !== null ? `:${occurrence.caller_line}` : ''}`
        : null;

    return (
        <div className="flex items-start gap-3 rounded-lg bg-muted/40 px-3 py-2.5 text-sm">
            <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-3">
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {formatTime(occurrence.time)}
                    </span>
                    <span className="text-xs font-semibold tabular-nums">
                        {formatDurationMs(occurrence.duration_ms)}
                    </span>
                </div>

                <div
                    className="mt-1.5 flex min-w-0 items-center gap-1.5 truncate font-mono text-xs text-muted-foreground"
                    title={occurrence.caller ?? undefined}
                >
                    <FileCode className="size-3.5 shrink-0" />
                    <span className="truncate">
                        {location ?? occurrence.caller ?? 'Unknown caller'}
                    </span>
                </div>
            </div>

            {showTraceLink && occurrence.trace_id ? (
                <Link
                    href={showTrace({
                        project: projectId,
                        trace: occurrence.trace_id,
                    })}
                    className="inline-flex shrink-0 items-center gap-1 rounded-full border border-sidebar-border/70 bg-background py-1 pr-2.5 pl-2 text-xs font-medium text-muted-foreground hover:border-sidebar-border hover:bg-accent hover:text-foreground dark:border-sidebar-border"
                    title={`View ${formatSpanType(occurrence.called_from_type).toLowerCase()}`}
                >
                    <ArrowUpRight className="size-3" />
                    {formatSpanType(occurrence.called_from_type)}
                </Link>
            ) : null}
        </div>
    );
}

/**
 * The full picture of one SQL statement -- shared by the Queries table's
 * drawer and its dedicated detail page (see QueryOccurrencesQuery), so a
 * quick peek from the table and the full page always show the same data,
 * just at two different sizes. `chartLayout` lets the drawer stack the two
 * chart panels instead of sitting them side by side: at the drawer's
 * fixed, narrower width "side by side" only ever means "two charts too
 * thin to read", where the full page has room to spare.
 */
export function QueryDetailContent({
    projectId,
    detail,
    chartLayout = 'row',
    showTraceLink = false,
}: {
    projectId: number;
    detail: QueryDetail;
    chartLayout?: 'row' | 'stacked';
    showTraceLink?: boolean;
}) {
    return (
        <div className="space-y-6">
            <section>
                <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    SQL
                </p>
                <pre className="overflow-x-auto rounded-lg bg-muted p-3 font-mono text-xs break-words whitespace-pre-wrap">
                    <SqlHighlight sql={detail.query} />
                </pre>
            </section>

            <div
                className={
                    chartLayout === 'stacked'
                        ? 'flex flex-col gap-4'
                        : 'flex flex-col gap-4 sm:flex-row'
                }
            >
                <StatChartPanel
                    title="Calls"
                    headline={detail.calls.toLocaleString()}
                    pills={[]}
                    points={detail.timeline.map((point) => ({
                        at: point.at,
                        segments: [
                            {
                                label: 'Calls',
                                value: point.calls,
                                tone: 'neutral' as const,
                            },
                        ],
                    }))}
                />

                <StatChartPanel
                    chart="line"
                    title="Duration"
                    headline={formatDurationMs(detail.avg_ms)}
                    pills={[
                        {
                            label: 'Avg',
                            value: formatDurationMs(detail.avg_ms),
                            tone: 'neutral',
                        },
                        {
                            label: 'P95',
                            value: formatDurationMs(detail.p95_ms),
                            tone: 'warning',
                        },
                    ]}
                    points={detail.timeline.map((point) => ({
                        at: point.at,
                        segments: [
                            {
                                label: 'Avg',
                                value: point.avg_ms ?? 0,
                                tone: 'neutral' as const,
                            },
                            {
                                label: 'P95',
                                value: point.p95_ms ?? 0,
                                tone: 'warning' as const,
                            },
                        ],
                    }))}
                    valueFormat={(value) => `${value.toFixed(1)} ms`}
                />
            </div>

            <section className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p className="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Details
                </p>
                <div className="grid grid-cols-1 gap-x-8 gap-y-2 sm:grid-cols-2">
                    <DetailRow
                        label="Connection"
                        value={detail.system ?? '—'}
                    />
                    <DetailRow
                        label="Database"
                        value={detail.namespace ?? '—'}
                    />
                    <DetailRow label="Host" value={detail.host ?? '—'} />
                    <DetailRow
                        label="Port"
                        value={detail.port !== null ? String(detail.port) : '—'}
                    />
                    <DetailRow
                        label="Total time"
                        value={formatDurationLong(detail.total_ms)}
                    />
                    <DetailRow
                        label="First seen"
                        value={
                            detail.first_seen
                                ? formatTime(detail.first_seen)
                                : '—'
                        }
                    />
                    <DetailRow
                        label="Last seen"
                        value={
                            detail.last_seen
                                ? formatTime(detail.last_seen)
                                : '—'
                        }
                    />
                </div>
            </section>

            <section className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div className="flex items-center justify-between gap-4 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <p className="font-medium">
                        {detail.calls.toLocaleString()} Calls
                        <span className="ml-2 text-xs font-normal text-muted-foreground">
                            most recent {detail.occurrences.length}
                        </span>
                    </p>
                </div>

                {detail.occurrences.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        No calls recorded in this window.
                    </p>
                ) : (
                    <div className="max-h-96 space-y-2 overflow-y-auto p-3">
                        {detail.occurrences.map((occurrence, index) => (
                            <CallRow
                                key={`${occurrence.trace_id ?? 'unknown'}-${occurrence.span_id ?? index}`}
                                projectId={projectId}
                                occurrence={occurrence}
                                showTraceLink={showTraceLink}
                            />
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}
