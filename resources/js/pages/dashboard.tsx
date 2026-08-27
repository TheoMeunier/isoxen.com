import { Head, Link } from '@inertiajs/react';
import { FolderGit2, Globe, TriangleAlert, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { StatTile } from '@/components/molecules/stat-tile';
import { ProjectOverviewCard } from '@/components/organisms/project-overview-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as projectsIndex } from '@/routes/projects';
import type {
    DashboardOverview,
    DashboardProjectStat,
} from '@/types/dashboard';
import type { Project } from '@/types/project';

export default function Dashboard({
    projects,
    overview,
    projectStats,
}: {
    projects: Project[];
    overview: DashboardOverview | null;
    projectStats: DashboardProjectStat[];
}) {
    const statsByProject = new Map(
        projectStats.map((stat) => [stat.projectId, stat]),
    );

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Dashboard"
                    description="What's happening across every project you're watching."
                />

                {overview === null ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
                        <p className="text-sm font-medium">No projects yet</p>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Create your first project to get an ingestion token.
                            Once your app starts sending telemetry, it'll be
                            summarized here.
                        </p>
                        <Button asChild className="mt-2">
                            <Link href={projectsIndex()}>Create a project</Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatTile
                                icon={FolderGit2}
                                label="Projects"
                                value={overview.projectsCount.toLocaleString()}
                                detail={
                                    overview.projectsOnline <
                                    overview.projectsCount
                                        ? `${(overview.projectsCount - overview.projectsOnline).toLocaleString()} offline`
                                        : undefined
                                }
                                tone={
                                    overview.projectsOnline <
                                    overview.projectsCount
                                        ? 'critical'
                                        : 'neutral'
                                }
                            />
                            <StatTile
                                icon={Globe}
                                label="Requests · 24h"
                                value={overview.requests24h.toLocaleString()}
                            />
                            <StatTile
                                icon={TriangleAlert}
                                label="Errors · 24h"
                                value={(
                                    overview.clientErrors24h +
                                    overview.serverErrors24h
                                ).toLocaleString()}
                                detail={
                                    overview.serverErrors24h > 0
                                        ? `${overview.serverErrors24h.toLocaleString()} server errors`
                                        : undefined
                                }
                                tone={
                                    overview.serverErrors24h > 0
                                        ? 'critical'
                                        : 'neutral'
                                }
                            />
                            <StatTile
                                icon={Users}
                                label="Online users"
                                value={overview.onlineUsers.toLocaleString()}
                            />
                        </div>

                        <div>
                            <p className="mb-3 text-sm font-medium text-muted-foreground">
                                Projects
                            </p>
                            {/*
                                auto-fit, not auto-fill: auto-fit collapses tracks that have no card
                                in them and hands their space to the tracks that do, so cards stretch
                                to fill the full row -- one project fills the whole width, several
                                share it evenly and wrap at the 280px minimum. auto-fill would instead
                                keep those empty tracks reserved and split 1fr across all of them,
                                which is what made a single card render narrow with empty space next
                                to it.
                            */}
                            <div className="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-4">
                                {projects.map((project) => {
                                    const stat = statsByProject.get(project.id);

                                    return stat ? (
                                        <ProjectOverviewCard
                                            key={project.id}
                                            project={project}
                                            stat={stat}
                                        />
                                    ) : null;
                                })}
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = (page: { overview: DashboardOverview | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
    actions: page.overview ? <PeriodSelector /> : undefined,
});
