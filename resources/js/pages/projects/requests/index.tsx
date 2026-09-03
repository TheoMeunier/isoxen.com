import { Head } from '@inertiajs/react';
import { CategoryHeader } from '@/components/molecules/category-header';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { ActivityCharts } from '@/components/organisms/activity-charts';
import { RoutesTable } from '@/features/projects/requests/components/request-table';
import { projectBreadcrumbs } from '@/lib/project-breadcrumbs';
import type {
    CategorySummary,
    DurationPoint,
    RouteStat,
    StatusSegment,
    StatusTimelinePoint,
    TimelinePoint,
} from '@/types/observability';
import type { Project } from '@/types/project';

const LABEL = 'Requests';

export default function ProjectsShowRequests({
    project,
    summary,
    timeline,
    durationTimeline,
    statusBreakdown,
    statusTimeline,
    routes,
}: {
    project: Project;
    summary: CategorySummary;
    timeline: TimelinePoint[];
    durationTimeline: DurationPoint[];
    statusBreakdown: StatusSegment[];
    statusTimeline: StatusTimelinePoint[];
    routes: RouteStat[];
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

                <RoutesTable projectId={project.id} routes={routes} />
            </div>
        </>
    );
}

ProjectsShowRequests.layout = (page: { project: Project }) => ({
    breadcrumbs: projectBreadcrumbs(page.project, 'requests'),
    actions: <PeriodSelector />,
});
