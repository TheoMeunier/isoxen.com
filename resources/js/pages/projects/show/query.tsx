import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import { QueryDetailContent } from '@/features/projects/query/query-detail';
import { index, show } from '@/routes/projects';
import type { QueryDetail } from '@/types/observability';
import type { Project } from '@/types/project';

/**
 * A single SQL statement's full detail page -- the same content the
 * Queries table's drawer shows (see QueryDetailContent), just as its own
 * page with room to breathe, for whoever wants a link to one specific
 * statement instead of a side panel.
 */
export default function ProjectsShowQuery({
    project,
    query,
}: {
    project: Project;
    query: QueryDetail;
}) {
    return (
        <>
            <Head title={query.query} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Link
                    href={show({ project: project.id, category: 'queries' })}
                    className="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to {project.name}
                </Link>

                <Heading
                    title="Query"
                    description={`${query.calls.toLocaleString()} calls in the last 24h`}
                />

                <QueryDetailContent
                    projectId={project.id}
                    detail={query}
                    showTraceLink
                />
            </div>
        </>
    );
}

ProjectsShowQuery.layout = (page: { project: Project }) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: page.project.name,
            href: show({ project: page.project.id, category: 'queries' }),
        },
    ],
});
