import { Head } from '@inertiajs/react';
import { CategoryHeader } from '@/components/molecules/category-header';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { ActivityCharts } from '@/components/organisms/activity-charts';
import { ExceptionsTable } from '@/features/projects/exceptions/exceptions-table';
import { projectBreadcrumbs } from '@/lib/project-breadcrumbs';
import type {
    CategorySummary,
    DurationPoint,
    ExceptionGroup,
    StatusSegment,
    StatusTimelinePoint,
    TimelinePoint,
} from '@/types/observability';
import type { Project } from '@/types/project';

const LABEL = 'Exceptions';

export default function ProjectsShowExceptions({
    project,
    summary,
    timeline,
    durationTimeline,
    statusBreakdown,
    statusTimeline,
    exceptionGroups,
}: {
    project: Project;
    summary: CategorySummary;
    timeline: TimelinePoint[];
    durationTimeline: DurationPoint[];
    statusBreakdown: StatusSegment[];
    statusTimeline: StatusTimelinePoint[];
    exceptionGroups: ExceptionGroup[];
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

                <ExceptionsTable projectId={project.id} groups={exceptionGroups} />
            </div>
        </>
    );
}

ProjectsShowExceptions.layout = (page: { project: Project }) => ({
    breadcrumbs: projectBreadcrumbs(page.project, 'exceptions'),
    actions: <PeriodSelector />,
});
