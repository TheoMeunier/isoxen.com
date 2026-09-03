import { Head } from '@inertiajs/react';
import { CategoryHeader } from '@/components/molecules/category-header';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { ActivityCharts } from '@/components/organisms/activity-charts';
import { EntriesPanel } from '@/components/organisms/entries-panel';
import { LogsTable } from '@/components/organisms/logs-table';
import { projectBreadcrumbs } from '@/lib/project-breadcrumbs';
import type {
    CategorySummary,
    LogEntry,
    Paginated,
    StatusSegment,
    StatusTimelinePoint,
    TimelinePoint,
} from '@/types/observability';
import type { Project } from '@/types/project';

const LABEL = 'Logs';

export default function ProjectsShowLogs({
    project,
    entries,
    summary,
    timeline,
    statusBreakdown,
    statusTimeline,
}: {
    project: Project;
    entries: Paginated<LogEntry>;
    summary: CategorySummary;
    timeline: TimelinePoint[];
    statusBreakdown: StatusSegment[];
    statusTimeline: StatusTimelinePoint[];
}) {
    return (
        <>
            <Head title={project.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <CategoryHeader label={LABEL} />

                <ActivityCharts
                    label={LABEL}
                    summary={summary}
                    statusBreakdown={statusBreakdown}
                    statusTimeline={statusTimeline}
                    timeline={timeline}
                    durationTimeline={[]}
                    hasDuration={false}
                />

                <EntriesPanel
                    label={LABEL}
                    total={summary.total}
                    entries={entries}
                    searchableText={(entry) => entry.body ?? ''}
                    renderTable={(rows) => <LogsTable entries={rows} />}
                />
            </div>
        </>
    );
}

ProjectsShowLogs.layout = (page: { project: Project }) => ({
    breadcrumbs: projectBreadcrumbs(page.project, 'logs'),
    actions: <PeriodSelector />,
});
