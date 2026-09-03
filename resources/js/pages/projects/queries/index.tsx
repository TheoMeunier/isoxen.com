import { Head } from '@inertiajs/react';
import { CategoryHeader } from '@/components/molecules/category-header';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { ActivityCharts } from '@/components/organisms/activity-charts';
import { QueriesTable } from '@/features/projects/query/queries-table';
import { projectBreadcrumbs } from '@/lib/project-breadcrumbs';
import type {
    CategorySummary,
    DurationPoint,
    QueryStat,
    StatusSegment,
    StatusTimelinePoint,
    TimelinePoint,
} from '@/types/observability';
import type { Project } from '@/types/project';

const LABEL = 'Queries';

export default function ProjectsShowQueries({
    project,
    summary,
    timeline,
    durationTimeline,
    statusBreakdown,
    statusTimeline,
    queryStats,
}: {
    project: Project;
    summary: CategorySummary;
    timeline: TimelinePoint[];
    durationTimeline: DurationPoint[];
    statusBreakdown: StatusSegment[];
    statusTimeline: StatusTimelinePoint[];
    queryStats: QueryStat[];
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
                    durationTimeline={durationTimeline}
                />

                <QueriesTable projectId={project.id} queries={queryStats} />
            </div>
        </>
    );
}

ProjectsShowQueries.layout = (page: { project: Project }) => ({
    breadcrumbs: projectBreadcrumbs(page.project, 'queries'),
    actions: <PeriodSelector />,
});
