import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { CategoryHeader } from '@/components/molecules/category-header';
import { LogSeverityBadge } from '@/components/molecules/log-severity-badge';
import {
    MethodBadge,
    parseHttpMethod,
    stripHttpMethod,
} from '@/components/molecules/method-badge';
import { PagerLinks } from '@/components/molecules/pager-links';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { StatusBadge } from '@/components/molecules/status-badge';
import { InformationPanel } from '@/components/organisms/information-panel';
import { OnlineUsersPanel } from '@/components/organisms/online-users-panel';
import { SlowEndpointsTable } from '@/components/organisms/slow-endpoints-table';
import { StatChartPanel } from '@/components/organisms/stat-chart-panel';
import { Input } from '@/components/ui/input';
import { formatDuration, formatTime } from '@/lib/datetime';
import { index, show } from '@/routes/projects';
import { show as showTrace } from '@/routes/projects/traces';
import type {
    CategorySummary,
    DurationPoint,
    EndpointStat,
    LogEntry,
    MetricEntry,
    ObservabilityCategory,
    OnlineUser,
    Paginated,
    SpanEntry,
    StatusSegment,
    StatusTimelinePoint,
    TimelinePoint,
} from '@/types/observability';
import type { Project } from '@/types/project';
const ONLINE_USERS_POLL_MS = 12000;
type ActiveTab = ObservabilityCategory | 'information';
const SPAN_KINDS: Record<number, string> = {
    0: 'Unspecified',
    1: 'Internal',
    2: 'Server',
    3: 'Client',
    4: 'Producer',
    5: 'Consumer',
};
const LOGS_CATEGORY: ObservabilityCategory = 'logs';
const METRICS_CATEGORY: ObservabilityCategory = 'metrics';
const CATEGORY_LABELS: Record<ObservabilityCategory, string> = {
    requests: 'Requests',
    jobs: 'Jobs',
    commands: 'Commands',
    'scheduled-tasks': 'Scheduled Tasks',
    exceptions: 'Exceptions',
    queries: 'Queries',
    notifications: 'Notifications',
    mail: 'Mail',
    cache: 'Cache',
    'outgoing-requests': 'Outgoing Requests',
    metrics: 'Metrics',
    users: 'Users',
    logs: 'Logs',
};
function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
            <p className="text-sm font-medium">No data received yet</p>
            <p className="max-w-sm text-sm text-muted-foreground">{message}</p>
        </div>
    );
}
function SpansTable({
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
function MetricsTable({ entries }: { entries: MetricEntry[] }) {
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
function LogsTable({ entries }: { entries: LogEntry[] }) {
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
type Entries =
    Paginated<SpanEntry> | Paginated<MetricEntry> | Paginated<LogEntry>;
export default function ProjectsShow({
    project,
    activeCategory,
    entries,
    summary,
    timeline,
    durationTimeline,
    statusBreakdown,
    statusTimeline,
    slowEndpoints,
    onlineUsers,
}: {
    project: Project;
    activeCategory: ActiveTab;
    entries: Entries;
    summary: CategorySummary;
    timeline: TimelinePoint[];
    durationTimeline: DurationPoint[];
    statusBreakdown: StatusSegment[];
    statusTimeline: StatusTimelinePoint[];
    slowEndpoints: EndpointStat[];
    onlineUsers: OnlineUser[];
}) {
    const [search, setSearch] = useState('');
    useEffect(() => {
        if (activeCategory !== 'users') {
            return;
        }

        const interval = setInterval(() => {
            router.reload({ only: ['onlineUsers'] });
        }, ONLINE_USERS_POLL_MS);

        return () => clearInterval(interval);
    }, [activeCategory]);

    if (activeCategory === 'information') {
        return (
            <>
                <Head title={project.name} />

                <div className="flex flex-1 flex-col gap-6 p-4">
                    <Heading title={project.name} description={project.slug} />

                    <InformationPanel project={project} />
                </div>
            </>
        );
    }

    if (activeCategory === 'users') {
        return (
            <>
                <Head title={project.name} />

                <div className="flex flex-1 flex-col gap-6 p-4">
                    <CategoryHeader label={CATEGORY_LABELS.users} />

                    <OnlineUsersPanel users={onlineUsers} />
                </div>
            </>
        );
    }

    const searchedEntries = entries.data.filter((entry) => {
        if (!search.trim()) {
            return true;
        }

        const haystack =
            'body' in entry
                ? (entry.body ?? '')
                : (('detail' in entry ? entry.detail : null) ??
                  entry.name ??
                  '');

        return haystack.toLowerCase().includes(search.trim().toLowerCase());
    });
    const hasDuration =
        activeCategory !== LOGS_CATEGORY && activeCategory !== METRICS_CATEGORY;

    return (
        <>
            <Head title={project.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <CategoryHeader label={CATEGORY_LABELS[activeCategory]} />

                <div className="flex flex-col gap-4 lg:flex-row">
                    <StatChartPanel
                        title={CATEGORY_LABELS[activeCategory].toUpperCase()}
                        headline={summary.total.toLocaleString()}
                        pills={statusBreakdown.map((segment) => ({
                            label: segment.label,
                            value: segment.value.toLocaleString(),
                            tone: segment.tone,
                        }))}
                        points={
                            statusTimeline.length > 0
                                ? statusTimeline
                                : timeline.map((point) => ({
                                      at: point.at,
                                      segments: [
                                          {
                                              label: CATEGORY_LABELS[
                                                  activeCategory
                                              ],
                                              value: point.count,
                                              tone: 'neutral' as const,
                                          },
                                      ],
                                  }))
                        }
                    />

                    {hasDuration && (
                        <StatChartPanel
                            title="Duration"
                            headline={
                                summary.avg_ms !== null
                                    ? `${summary.avg_ms} ms`
                                    : '—'
                            }
                            pills={[
                                {
                                    label: 'Avg',
                                    value:
                                        summary.avg_ms !== null
                                            ? `${summary.avg_ms} ms`
                                            : '—',
                                    tone: 'neutral',
                                },
                                {
                                    label: 'P95',
                                    value:
                                        summary.slowest_ms !== null
                                            ? `${summary.slowest_ms} ms`
                                            : '—',
                                    tone: 'warning',
                                },
                            ]}
                            points={durationTimeline.map((point) => ({
                                at: point.at,
                                segments: [
                                    {
                                        label: 'Avg',
                                        value: point.avg_ms ?? 0,
                                        tone: 'neutral' as const,
                                    },
                                ],
                            }))}
                            valueFormat={(value) => `${value.toFixed(1)} ms`}
                        />
                    )}
                </div>

                {activeCategory === 'requests' && slowEndpoints.length > 0 && (
                    <SlowEndpointsTable endpoints={slowEndpoints} />
                )}

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="flex items-center justify-between gap-4 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <p className="font-medium">
                            {summary.total.toLocaleString()}{' '}
                            {CATEGORY_LABELS[activeCategory]}
                        </p>

                        {entries.data.length > 0 && (
                            <div className="relative w-full max-w-64">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    placeholder={`Search ${CATEGORY_LABELS[activeCategory].toLowerCase()} on this page`}
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="h-8 pl-8 text-sm"
                                />
                            </div>
                        )}
                    </div>

                    <div className="p-4">
                        {entries.data.length === 0 ? (
                            <EmptyState
                                message={`Once your app starts sending ${CATEGORY_LABELS[activeCategory].toLowerCase()} with this token, they'll show up here.`}
                            />
                        ) : searchedEntries.length === 0 ? (
                            <EmptyState
                                message={`No ${CATEGORY_LABELS[activeCategory].toLowerCase()} on this page match "${search}".`}
                            />
                        ) : (
                            <div className="space-y-4">
                                {activeCategory === LOGS_CATEGORY && (
                                    <LogsTable
                                        entries={searchedEntries as LogEntry[]}
                                    />
                                )}
                                {activeCategory === METRICS_CATEGORY && (
                                    <MetricsTable
                                        entries={
                                            searchedEntries as MetricEntry[]
                                        }
                                    />
                                )}
                                {activeCategory !== LOGS_CATEGORY &&
                                    activeCategory !== METRICS_CATEGORY && (
                                        <SpansTable
                                            entries={
                                                searchedEntries as SpanEntry[]
                                            }
                                            projectId={project.id}
                                            showMethod={
                                                activeCategory === 'requests'
                                            }
                                        />
                                    )}

                                <PagerLinks
                                    meta={entries}
                                    itemLabel={CATEGORY_LABELS[
                                        activeCategory
                                    ].toLowerCase()}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
ProjectsShow.layout = (page: { project: Project }) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: page.project.name,
            href: show(page.project.id),
        },
    ],
    actions: <PeriodSelector />,
});
