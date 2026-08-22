import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { CategoryHeader } from '@/components/molecules/category-header';
import { PeriodSelector } from '@/components/molecules/period-selector';
import { OnlineUsersPanel } from '@/components/organisms/online-users-panel';
import { index, show } from '@/routes/projects';
import type { OnlineUser } from '@/types/observability';
import type { Project } from '@/types/project';

const ONLINE_USERS_POLL_MS = 12000;

/**
 * The Users tab: who's currently online, nothing else (see
 * ShowProjectController::renderUsers()). Polls this same route for
 * `onlineUsers` alone every ONLINE_USERS_POLL_MS -- cheap now that this
 * route has nothing else to recompute.
 */
export default function ProjectsShowUsers({
    project,
    onlineUsers,
}: {
    project: Project;
    onlineUsers: OnlineUser[];
}) {
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['onlineUsers'] });
        }, ONLINE_USERS_POLL_MS);

        return () => clearInterval(interval);
    }, []);

    return (
        <>
            <Head title={project.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <CategoryHeader label="Users" />

                <OnlineUsersPanel users={onlineUsers} />
            </div>
        </>
    );
}

ProjectsShowUsers.layout = (page: { project: Project }) => ({
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
    actions: <PeriodSelector />,
});
