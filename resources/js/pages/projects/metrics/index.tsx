import { Head } from '@inertiajs/react';
import { CategoryHeader } from '@/components/molecules/category-header';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { ActivityCharts } from '@/components/organisms/activity-charts';
import { EntriesPanel } from '@/components/organisms/entries-panel';
import { MetricsTable } from '@/components/organisms/metrics-table';
import { projectBreadcrumbs } from '@/lib/project-breadcrumbs';
import type {
    CategorySummary,
    MetricEntry,
    Paginated,
    StatusSegment,
    StatusTimelinePoint,
    TimelinePoint,
} from '@/types/observability';
import type { Project } from '@/types/project';

const LABEL = 'Metrics';

export default function ProjectsShowMetrics({
    project,
    entries,
    summary,
    timeline,
    statusBreakdown,
    statusTimeline,
}: {
    project: Project;
    entries: Paginated<MetricEntry>;
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
                    searchableText={(entry) => entry.name ?? ''}
                    renderTable={(rows) => <MetricsTable entries={rows} />}
                />
            </div>
        </>
    );
}

ProjectsShowMetrics.layout = (page: { project: Project }) => ({
    breadcrumbs: projectBreadcrumbs(page.project, 'metrics'),
    actions: <PeriodSelector />,
});
