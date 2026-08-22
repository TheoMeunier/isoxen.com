import { Head } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { CategoryHeader } from '@/components/molecules/category-header';
import { PagerLinks } from '@/components/molecules/pager-links';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { EmptyState } from '@/components/organisms/empty-state';
import { LogsTable } from '@/components/organisms/logs-table';
import { MetricsTable } from '@/components/organisms/metrics-table';
import { SlowEndpointsTable } from '@/components/organisms/slow-endpoints-table';
import { SpansTable } from '@/components/organisms/spans-table';
import { StatChartPanel } from '@/components/organisms/stat-chart-panel';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/projects';
import type {
    CategorySummary,
    DurationPoint,
    EndpointStat,
    LogEntry,
    MetricEntry,
    ObservabilityCategory,
    Paginated,
    SpanEntry,
    StatusSegment,
    StatusTimelinePoint,
    TimelinePoint,
} from '@/types/observability';
import type { Project } from '@/types/project';

// The 11 telemetry categories this page renders -- everything
// ObservabilityCategories lists except the pseudo-category Information and
// the Users tab, which each have their own page (see
// ShowProjectController::render()).
type ActivityCategory = Exclude<ObservabilityCategory, 'users'>;

const LOGS_CATEGORY: ActivityCategory = 'logs';
const METRICS_CATEGORY: ActivityCategory = 'metrics';

const CATEGORY_LABELS: Record<ActivityCategory, string> = {
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
    logs: 'Logs',
};

type Entries =
    Paginated<SpanEntry> | Paginated<MetricEntry> | Paginated<LogEntry>;

export default function ProjectsShowActivity({
    project,
    activeCategory,
    entries,
    summary,
    timeline,
    durationTimeline,
    statusBreakdown,
    statusTimeline,
    slowEndpoints,
}: {
    project: Project;
    activeCategory: ActivityCategory;
    entries: Entries;
    summary: CategorySummary;
    timeline: TimelinePoint[];
    durationTimeline: DurationPoint[];
    statusBreakdown: StatusSegment[];
    statusTimeline: StatusTimelinePoint[];
    slowEndpoints: EndpointStat[];
}) {
    const [search, setSearch] = useState('');

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

ProjectsShowActivity.layout = (page: {
    project: Project;
    activeCategory: ActivityCategory;
}) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: page.project.name,
            href: show({
                project: page.project.id,
                category: page.activeCategory,
            }),
        },
    ],
    actions: <PeriodSelector />,
});
