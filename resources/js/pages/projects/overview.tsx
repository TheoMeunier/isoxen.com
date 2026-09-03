import { Head } from '@inertiajs/react';
import { Activity, TriangleAlert, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { StatTile } from '@/components/molecules/stat-tile';
import { CategoryHealthGrid } from '@/components/organisms/category-health-grid';
import { index, show } from '@/routes/projects';
import type { CategoryHealth } from '@/types/observability';
import type { Project } from '@/types/project';

/**
 * The Overview tab: this project's whole health, one screen -- every
 * category's 24h volume and whether it's clean or erroring, without having
 * to click through each tab to find out. The project's default landing
 * page is still Requests (see ObservabilityCategories::default()); this is
 * reached from the sidebar or by clicking a project card on the dashboard.
 */
export default function ProjectsShowOverview({
    project,
    categories,
    onlineUsers,
}: {
    project: Project;
    categories: CategoryHealth[];
    onlineUsers: number;
}) {
    const totalEvents = categories.reduce(
        (sum, category) => sum + category.total,
        0,
    );
    const categoriesWithErrors = categories.filter(
        (category) => category.errors > 0,
    );
    const totalErrors = categoriesWithErrors.reduce(
        (sum, category) => sum + category.errors,
        0,
    );
    const hasAnyData = totalEvents > 0;

    return (
        <>
            <Head title={project.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title={project.name} description={project.slug} />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatTile
                        icon={Activity}
                        label="Events · 24h"
                        value={totalEvents.toLocaleString()}
                    />
                    <StatTile
                        icon={TriangleAlert}
                        label="Errors · 24h"
                        value={totalErrors.toLocaleString()}
                        detail={
                            categoriesWithErrors.length > 0
                                ? `${categoriesWithErrors.length} ${categoriesWithErrors.length === 1 ? 'category' : 'categories'} affected`
                                : undefined
                        }
                        tone={totalErrors > 0 ? 'critical' : 'neutral'}
                    />
                    <StatTile
                        icon={Users}
                        label="Online users"
                        value={onlineUsers.toLocaleString()}
                    />
                </div>

                {hasAnyData ? (
                    <CategoryHealthGrid
                        projectId={project.id}
                        categories={categories}
                    />
                ) : (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
                        <p className="text-sm font-medium">
                            No data received yet
                        </p>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Once your app starts sending telemetry with this
                            project's token, you'll see a breakdown of what's
                            healthy and what's erroring here.
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}

ProjectsShowOverview.layout = (page: { project: Project }) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: page.project.name,
            href: show({ project: page.project.id, category: 'overview' }),
        },
    ],
    actions: <PeriodSelector />,
});
