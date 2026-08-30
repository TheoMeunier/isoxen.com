import { Link } from '@inertiajs/react';
import { ArrowUpRight, Globe } from 'lucide-react';
import { HttpStatusBadge } from '@/components/molecules/http-status-badge';
import { MethodBadge } from '@/components/molecules/method-badge';
import { StatChartPanel } from '@/components/organisms/stat-chart-panel';
import { formatDurationMs, formatTime } from '@/lib/datetime';
import { show as showTrace } from '@/routes/projects/traces';
import type { RouteDetail } from '@/types/observability';

/**
 * A receipt-style row: label, a dotted rule filling the gap, then the
 * value -- same treatment as QueryDetailContent's DetailRow, so the two
 * detail panels read consistently even though they show different fields.
 */
function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex min-w-0 items-baseline gap-2 text-sm">
            <span className="shrink-0 text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </span>
            <span className="h-px min-w-2 flex-1 translate-y-[-3px] border-b border-dotted border-sidebar-border/70 dark:border-sidebar-border" />
            <span className="min-w-0 text-right font-medium break-words tabular-nums">
                {value}
            </span>
        </div>
    );
}

/**
 * One request in the Recent requests list, styled as its own lightly-shaded
 * card the same way QueryDetailContent's CallRow is -- method and status on
 * the left, duration on the right, the full URL and caller address below,
 * and a trace link in the corner on the full detail page only (it competes
 * with the drawer's own close affordance for the same corner there, so it's
 * left out in the drawer, matching CallRow's `showTraceLink`).
 */
function RequestRow({
    projectId,
    occurrence,
    showTraceLink = false,
}: {
    projectId: number;
    occurrence: RouteDetail['occurrences'][number];
    showTraceLink?: boolean;
}) {
    return (
        <div className="flex items-start gap-3 rounded-lg bg-muted/40 px-3 py-2.5 text-sm">
            <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <MethodBadge method={occurrence.method} />
                        <HttpStatusBadge status={occurrence.status_code} />
                    </div>
                    <span className="text-xs font-semibold tabular-nums">
                        {formatDurationMs(occurrence.duration_ms)}
                    </span>
                </div>

                <div
                    className="mt-1.5 flex min-w-0 items-center gap-1.5 truncate font-mono text-xs text-muted-foreground"
                    title={occurrence.url ?? undefined}
                >
                    <span className="truncate">
                        {occurrence.url ?? 'Unknown URL'}
                    </span>
                </div>

                <div className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                    <span className="tabular-nums">
                        {formatTime(occurrence.time)}
                    </span>
                    {occurrence.client_address && (
                        <>
                            <span aria-hidden>·</span>
                            <span>{occurrence.client_address}</span>
                        </>
                    )}
                </div>
            </div>

            {showTraceLink && occurrence.trace_id ? (
                <Link
                    href={showTrace({
                        project: projectId,
                        trace: occurrence.trace_id,
                    })}
                    className="inline-flex shrink-0 items-center gap-1 rounded-full border border-sidebar-border/70 bg-background py-1 pr-2.5 pl-2 text-xs font-medium text-muted-foreground hover:border-sidebar-border hover:bg-accent hover:text-foreground dark:border-sidebar-border"
                    title="View trace"
                >
                    <ArrowUpRight className="size-3" />
                    Trace
                </Link>
            ) : null}
        </div>
    );
}

/**
 * The full picture of one route -- shared by the Routes table's drawer and
 * its dedicated detail page (see RouteOccurrencesQuery), so a quick peek
 * from the table and the full page always show the same data, just at two
 * different sizes. `chartLayout` lets the drawer stack the two chart panels
 * instead of sitting them side by side, the same tradeoff
 * QueryDetailContent makes at its fixed, narrower drawer width.
 */
export function RouteDetailContent({
    projectId,
    detail,
    chartLayout = 'row',
    showTraceLink = false,
}: {
    projectId: number;
    detail: RouteDetail;
    chartLayout?: 'row' | 'stacked';
    showTraceLink?: boolean;
}) {
    return (
        <div className="space-y-6">
            <section className="flex items-center gap-2 font-mono text-sm">
                <Globe className="size-4 shrink-0 text-muted-foreground" />
                <span className="truncate" title={detail.path}>
                    {detail.path}
                </span>
            </section>

            <div
                className={
                    chartLayout === 'stacked'
                        ? 'flex flex-col gap-4'
                        : 'flex flex-col gap-4 sm:flex-row'
                }
            >
                <StatChartPanel
                    title="Requests"
                    headline={detail.total.toLocaleString()}
                    pills={[
                        {
                            label: '4XX',
                            value: detail.client_error.toLocaleString(),
                            tone: 'warning',
                        },
                        {
                            label: '5XX',
                            value: detail.server_error.toLocaleString(),
                            tone: 'critical',
                        },
                    ]}
                    points={detail.timeline.map((point) => ({
                        at: point.at,
                        segments: [
                            {
                                label: '1XX-3XX',
                                value: point.success,
                                tone: 'neutral' as const,
                            },
                            {
                                label: '4XX',
                                value: point.client_error,
                                tone: 'warning' as const,
                            },
                            {
                                label: '5XX',
                                value: point.server_error,
                                tone: 'critical' as const,
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
                <div className="space-y-2">
                    <DetailRow
                        label="Route name"
                        value={detail.route_name ?? '—'}
                    />
                    <DetailRow label="Action" value={detail.action ?? '—'} />
                    <DetailRow label="Methods" value={detail.methods} />
                    <DetailRow
                        label="Total"
                        value={detail.total.toLocaleString()}
                    />
                    <DetailRow
                        label="Avg duration"
                        value={formatDurationMs(detail.avg_ms)}
                    />
                    <DetailRow
                        label="P95"
                        value={formatDurationMs(detail.p95_ms)}
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
                        {detail.total.toLocaleString()} Requests
                        <span className="ml-2 text-xs font-normal text-muted-foreground">
                            most recent {detail.occurrences.length}
                        </span>
                    </p>
                </div>

                {detail.occurrences.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        No requests recorded in this window.
                    </p>
                ) : (
                    <div className="max-h-96 space-y-2 overflow-y-auto p-3">
                        {detail.occurrences.map((occurrence, index) => (
                            <RequestRow
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
