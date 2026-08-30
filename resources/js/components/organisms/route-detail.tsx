import { Link } from '@inertiajs/react';
import { ArrowUpRight, Globe } from 'lucide-react';
import type { ReactNode } from 'react';
import { HttpStatusBadge } from '@/components/molecules/http-status-badge';
import { MethodBadge } from '@/components/molecules/method-badge';
import { StatChartPanel } from '@/components/organisms/stat-chart-panel';
import { formatDurationMs, formatTime } from '@/lib/datetime';
import { formatBytes } from '@/lib/utils';
import {
    occurrence as showOccurrence,
    show as showRoute,
} from '@/routes/projects/requests';
import { show as showTrace } from '@/routes/projects/traces';
import type { RouteDetail, RouteOccurrence } from '@/types/observability';

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
 * The same receipt-row treatment as DetailRow, sized for the per-request
 * panel (see RequestActivityPanel) -- `text-sm`, same as DetailRow itself,
 * so a request's Info/Details read as comfortably as the route-level
 * Details rows just above them, not as a cramped afterthought.
 */
function MiniRow({ label, value }: { label: string; value: string }) {
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
 * A named group of MiniRows within RequestActivityPanel -- "Info" and
 * "Details" themselves, plus the "User" and "Notifications and Mail"
 * sub-groups Nightwatch breaks out from them. A real heading (not a small
 * muted label) is what separates this from feeling like one dense block of
 * rows: each group reads as its own section.
 */
function ActivityGroup({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2.5">
            <p className="text-sm font-semibold text-foreground">{title}</p>
            <div className="space-y-2">{children}</div>
        </div>
    );
}

/**
 * One request's full Info/Details breakdown -- everything Nightwatch's own
 * request page shows for one occurrence: sizes and status on the left
 * (grouped under "Info", with who made the request broken out under its
 * own "User" heading below), and what it triggered downstream (read back
 * from its trace's child spans, see RouteRequestActivityQuery) on the
 * right (grouped under "Details", with mail/notifications broken out under
 * "Notifications and Mail"). Server and deployment aren't shown here --
 * isoxen doesn't capture them today.
 *
 * Lives here (rather than the route it's most often shown next to) so both
 * the dedicated request page (ShowRequestOccurrenceController) and this
 * route's own "Latest request" block above can import the same panel.
 */
export function RequestActivityPanel({
    occurrence,
    noTopBorder = false,
}: {
    occurrence: RouteDetail['occurrences'][number];
    noTopBorder?: boolean;
}) {
    const user = occurrence.user;
    const userLabel = user ? (user.email ?? user.name ?? user.id ?? '—') : '—';
    const {
        queries,
        outgoing_requests,
        queued_jobs,
        cache,
        mail,
        notifications,
    } = occurrence.activity;
    const { storage, hydrated_models } = occurrence;

    return (
        <div
            className={
                noTopBorder
                    ? 'grid grid-cols-1 gap-x-10 gap-y-6 sm:grid-cols-2'
                    : 'mt-4 grid grid-cols-1 gap-x-10 gap-y-6 border-t border-sidebar-border/70 pt-4 sm:grid-cols-2 dark:border-sidebar-border'
            }
        >
            <div className="space-y-6">
                <ActivityGroup title="Info">
                    <MiniRow
                        label="Status code"
                        value={
                            occurrence.status_code !== null
                                ? String(occurrence.status_code)
                                : '—'
                        }
                    />
                    <MiniRow
                        label="Duration"
                        value={formatDurationMs(occurrence.duration_ms)}
                    />
                    <MiniRow
                        label="Request size"
                        value={
                            occurrence.request_size !== null
                                ? formatBytes(occurrence.request_size)
                                : '—'
                        }
                    />
                    <MiniRow
                        label="Response size"
                        value={
                            occurrence.response_size !== null
                                ? formatBytes(occurrence.response_size)
                                : '—'
                        }
                    />
                    <MiniRow
                        label="Peak memory"
                        value={
                            occurrence.peak_memory !== null
                                ? formatBytes(occurrence.peak_memory)
                                : '—'
                        }
                    />
                </ActivityGroup>

                <ActivityGroup title="User">
                    <MiniRow label="User" value={userLabel} />
                    <MiniRow
                        label="IP address"
                        value={occurrence.client_address ?? '—'}
                    />
                </ActivityGroup>
            </div>

            <div className="space-y-6">
                <ActivityGroup title="Details">
                    <MiniRow
                        label="Queries"
                        value={`${queries.count.toLocaleString()} / ${formatDurationMs(queries.total_ms)}`}
                    />
                    <MiniRow
                        label="Outgoing requests"
                        value={outgoing_requests.count.toLocaleString()}
                    />
                    <MiniRow
                        label="Queued jobs"
                        value={queued_jobs.count.toLocaleString()}
                    />
                    <MiniRow
                        label="Storage"
                        value={`${storage.reads.toLocaleString()} Reads / ${storage.writes.toLocaleString()} Writes`}
                    />
                    <MiniRow
                        label="Cache"
                        value={`${cache.hits} hit / ${cache.misses} miss / ${cache.writes} write`}
                    />
                    <MiniRow
                        label="Hydrated models"
                        value={hydrated_models.toLocaleString()}
                    />
                </ActivityGroup>

                <ActivityGroup title="Notifications and Mail">
                    <MiniRow label="Mail" value={mail.count.toLocaleString()} />
                    <MiniRow
                        label="Notifications"
                        value={notifications.count.toLocaleString()}
                    />
                </ActivityGroup>
            </div>
        </div>
    );
}

/**
 * A request's summary header (method, status, duration, URL, and links back
 * to its route/trace) plus its full RequestActivityPanel below -- the
 * self-contained card shown on the dedicated request page (see
 * ShowLatestRequestController/ShowRequestOccurrenceController) and reused
 * here for a route's own page to surface its most recent request without
 * requiring a click through to that separate page. `showRouteLink` is off
 * on a route's own page (already there, so the link back would be
 * redundant) and on wherever a request isn't already scoped to one route
 * (the two dedicated request pages). `bare` drops its own border/padding
 * for the route page, where it sits inside the route's own Details card
 * instead of getting one of its own (see RouteDetailContent) -- Nightwatch
 * keeps both halves in one card, not two stacked ones.
 */
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

            <RequestActivityPanel
                occurrence={occurrence}
                noTopBorder={!showHeader}
            />
        </div>
    );
}

/**
 * One request in the Recent requests list, styled as its own lightly-shaded
 * card the same way QueryDetailContent's CallRow is -- method and status on
 * the left, duration on the right, the full URL and caller address below,
 * and a trace link in the corner on the full detail page only (it competes
 * with the drawer's own close affordance for the same corner there, so it's
 * left out in the drawer, matching CallRow's `showTraceLink`). The summary
 * itself links to that one request's own page (see
 * ShowRequestOccurrenceController) rather than expanding a full
 * Info/Details breakdown in place here (see RequestActivityPanel) -- only
 * when both id halves are present, since a request needs its trace id
 * *and* its own span id within that trace to be addressed uniquely.
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

                {detail.occurrences[0] && (
                    <div className="mt-4 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border">
                        <RequestOccurrenceCard
                            projectId={projectId}
                            occurrence={detail.occurrences[0]}
                            showHeader={false}
                            bare
                        />
                    </div>
                )}
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
