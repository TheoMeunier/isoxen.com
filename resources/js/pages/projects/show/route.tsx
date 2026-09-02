import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { RouteDetailContent } from '@/components/organisms/route-detail';
import { index, show } from '@/routes/projects';
import type { RouteDetail } from '@/types/observability';
import type { Project } from '@/types/project';

export default function ProjectsShowRoute({
    project,
    route,
}: {
    project: Project;
    route: RouteDetail;
}) {
    return (
        <>
            <Head title={route.path} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Link
                    href={show({ project: project.id, category: 'requests' })}
                    className="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to {project.name}
                </Link>

                <RouteDetailContent
                    projectId={project.id}
                    detail={route}
                    showTraceLink
                />
            </div>
        </>
    );
}

ProjectsShowRoute.layout = (page: { project: Project }) => ({
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
