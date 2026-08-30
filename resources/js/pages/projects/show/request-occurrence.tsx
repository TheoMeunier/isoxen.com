import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { RequestOccurrenceCard } from '@/components/organisms/route-detail';
import { index, show } from '@/routes/projects';
import type { RequestOccurrenceDetail } from '@/types/observability';
import type { Project } from '@/types/project';

/**
 * One request's full Info/Details breakdown as its own page -- reached by
 * clicking a specific row in a route's "Recent requests" list (see
 * RouteDetailContent's RequestRow), addressed by trace id + span id (see
 * ShowRequestOccurrenceController).
 */
export default function ProjectsShowRequestOccurrence({
    project,
    request,
}: {
    project: Project;
    request: RequestOccurrenceDetail;
}) {
    return (
        <>
            <Head title="Request" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Link
                    href={show({ project: project.id, category: 'requests' })}
                    className="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to {project.name}
                </Link>

                <RequestOccurrenceCard
                    projectId={project.id}
                    occurrence={request}
                    showRouteLink
                    showTraceLink
                />
            </div>
        </>
    );
}

ProjectsShowRequestOccurrence.layout = (page: { project: Project }) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: page.project.name,
            href: show({ project: page.project.id, category: 'requests' }),
        },
    ],
});
