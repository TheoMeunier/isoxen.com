import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { InformationPanel } from '@/components/organisms/information-panel';
import { index, show } from '@/routes/projects';
import type { Project } from '@/types/project';

/**
 * The Information tab: the project's own settings (rename, ingestion token,
 * delete). There's no telemetry behind it -- see
 * ShowProjectController::renderInformation() -- so this page is just the
 * project itself, none of the activity pipeline's props.
 */
export default function ProjectsShowInformation({
    project,
}: {
    project: Project;
}) {
    return (
        <>
            <Head title={project.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title={project.name} description={project.slug} />

                <InformationPanel project={project} />
            </div>
        </>
    );
}

ProjectsShowInformation.layout = (page: { project: Project }) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: page.project.name,
            // The Information tab has no observability category of its own,
            // so the breadcrumb falls back to the project's default
            // category (mirrors ObservabilityCategories::default()).
            href: show({ project: page.project.id, category: 'requests' }),
        },
    ],
    actions: <PeriodSelector />,
});
