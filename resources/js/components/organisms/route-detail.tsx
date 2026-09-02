import { Link } from '@inertiajs/react';
import { ArrowUpRight, Globe } from 'lucide-react';
import { HttpStatusBadge } from '@/components/molecules/http-status-badge';
import { MethodBadge } from '@/components/molecules/method-badge';
import { StatChartPanel } from '@/components/organisms/stat-chart-panel';
import { formatDurationMs, formatTime } from '@/lib/datetime';
import {
    occurrence as showOccurrence,
    show as showRoute,
} from '@/routes/projects/requests';
import { show as showTrace } from '@/routes/projects/traces';
import type { RouteDetail, RouteOccurrence } from '@/types/observability';

export function RequestOccurrenceCard({
    projectId,
    occurrence,
    showRouteLink = false,
    showTraceLink = false,
    showHeader = true,
    bare = false,
}: {
    projectId: number;
    occurrence: RouteOccurrence & {
        path?: string | null;
        fingerprint?: string | null;
        route_name?: string | null;
        action?: string | null;
    };
    showRouteLink?: boolean;
    showTraceLink?: boolean;
    showHeader?: boolean;
    bare?: boolean;
}) {
    return (
        <div
            className={
                bare
                    ? ''
                    : 'rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border'
            }
        >
            {showHeader && (
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 space-y-1.5">
                        <div className="flex items-center gap-2">
                            <MethodBadge method={occurrence.method} />
                            <HttpStatusBadge status={occurrence.status_code} />
                            <span className="text-xs font-semibold text-muted-foreground tabular-nums">
                                {formatDurationMs(occurrence.duration_ms)}
                            </span>
                        </div>

                        <div
                            className="truncate font-mono text-xs text-muted-foreground"
                            title={occurrence.url ?? undefined}
                        >
                            {occurrence.url ?? 'Unknown URL'}
                        </div>

                        {showRouteLink && occurrence.fingerprint && (
                            <Link
                                href={showRoute({
                                    project: projectId,
                                    fingerprint: occurrence.fingerprint,
                                })}
                                className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                            >
                                {occurrence.route_name ??
                                    occurrence.path ??
                                    'View route'}
                                <ArrowUpRight className="size-3" />
                            </Link>
                        )}
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
            )}
        </div>
    );
}

function RequestRow({
    projectId,
    occurrence,
    showTraceLink = false,
}: {
    projectId: number;
    occurrence: RouteDetail['occurrences'][number];
    showTraceLink?: boolean;
}) {
    const summary = (
        <>
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <MethodBadge method={occurrence.method} />
                    <HttpStatusBadge status={occurrence.status_code} />
                </div>
                <span className="shrink-0 text-xs font-semibold tabular-nums">
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
        </>
    );

    return (
        <div className="flex items-start gap-3 rounded-lg bg-muted/40 px-3 py-2.5 text-sm">
            {occurrence.trace_id && occurrence.span_id ? (
                <Link
                    href={showOccurrence({
                        project: projectId,
                        trace: occurrence.trace_id,
                        span: occurrence.span_id,
                    })}
                    className="min-w-0 flex-1 hover:opacity-80"
                >
                    {summary}
                </Link>
            ) : (
                <div className="min-w-0 flex-1">{summary}</div>
            )}

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
                <div className="flex flex-wrap items-center gap-1">
                    {detail.methods.split('|').map((method) => (
                        <MethodBadge key={method} method={method} />
                    ))}
                </div>
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

            {detail.occurrences[0] && (
                <section className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <RequestOccurrenceCard
                        projectId={projectId}
                        occurrence={{
                            ...detail.occurrences[0],
                            route_name: detail.route_name,
                            action: detail.action,
                        }}
                        showHeader={false}
                        bare
                    />
                </section>
            )}

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
