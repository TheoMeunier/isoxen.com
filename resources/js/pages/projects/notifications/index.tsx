import { PeriodSelector } from '@/components/molecules/period-selector';
import { SpanActivityPage } from '@/features/projects/activity/span-activity-page';
import { projectBreadcrumbs } from '@/lib/project-breadcrumbs';
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

const LABEL = 'Notifications';

export default function ProjectsShowNotifications(props: {
    project: Project;
    entries: Paginated<SpanEntry>;
    summary: CategorySummary;
    timeline: TimelinePoint[];
    durationTimeline: DurationPoint[];
    statusBreakdown: StatusSegment[];
    statusTimeline: StatusTimelinePoint[];
}) {
    return <SpanActivityPage label={LABEL} {...props} />;
}

ProjectsShowNotifications.layout = (page: { project: Project }) => ({
    breadcrumbs: projectBreadcrumbs(page.project, 'notifications'),
    actions: <PeriodSelector />,
});
