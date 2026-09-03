import { Head } from '@inertiajs/react';
import { CategoryHeader } from '@/components/molecules/category-header';
import { ActivityCharts } from '@/components/organisms/activity-charts';
import { EntriesPanel } from '@/components/organisms/entries-panel';
import { SpansTable } from '@/components/organisms/spans-table';
import type {
    CategorySummary,
    DurationPoint,
    Paginated,
    SpanEntry,
    StatusSegment,
    StatusTimelinePoint,
    TimelinePoint,
} from '@/types/observability';
import type { Project } from '@/types/project';

/**
 * The shared body for every span-backed category that has nothing bespoke to show beyond a
 * table of recent entries (Jobs, Commands, Scheduled Tasks, Notifications, Mail, Cache, Outgoing
 * Requests). Requests, Queries, Exceptions, Logs and Metrics each have their own page instead --
 * see `projects/*.tsx`.
 */
export function SpanActivityPage({
    label,
    project,
    entries,
    summary,
    timeline,
    durationTimeline,
    statusBreakdown,
    statusTimeline,
}: {
    label: string;
    project: Project;
    entries: Paginated<SpanEntry>;
    summary: CategorySummary;
    timeline: TimelinePoint[];
    durationTimeline: DurationPoint[];
    statusBreakdown: StatusSegment[];
    statusTimeline: StatusTimelinePoint[];
}) {
    return (
        <>
            <Head title={project.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <CategoryHeader label={label} />

                <ActivityCharts
                    label={label}
                    summary={summary}
                    statusBreakdown={statusBreakdown}
                    statusTimeline={statusTimeline}
                    timeline={timeline}
                    durationTimeline={durationTimeline}
                />

                <EntriesPanel
                    label={label}
                    total={summary.total}
                    entries={entries}
                    searchableText={(entry) => entry.detail ?? entry.name ?? ''}
                    renderTable={(rows) => (
                        <SpansTable entries={rows} projectId={project.id} />
                    )}
                />
            </div>
        </>
    );
}
