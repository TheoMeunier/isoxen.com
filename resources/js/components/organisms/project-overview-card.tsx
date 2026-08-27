import { Link } from '@inertiajs/react';
import { BarChart } from '@/components/molecules/bar-chart';
import { formatTime } from '@/lib/datetime';
import { TONE_DOT } from '@/lib/tone';
import { show } from '@/routes/projects';
import type { DashboardProjectStat } from '@/types/dashboard';
import type { Project } from '@/types/project';

/**
 * One project's card on the dashboard: name, request count, and a 24h
 * sparkline. Links to that project's Overview tab, where the fuller
 * breakdown (health per category, uptime, slowest endpoint, exceptions)
 * lives.
 */
export function ProjectOverviewCard({
    project,
    stat,
}: {
    project: Project;
    stat: DashboardProjectStat;
}) {
    const hasData =
        stat.requests > 0 ||
        stat.statusTimeline.some(
            (point) =>
                point.success > 0 ||
                point.client_error > 0 ||
                point.server_error > 0,
        );

    return (
        <Link
            href={show({ project: project.id, category: 'overview' })}
            className="flex flex-col rounded-xl border border-sidebar-border/70 p-4 transition-colors hover:bg-sidebar-accent dark:border-sidebar-border"
        >
            <div className="min-w-0">
                <p className="truncate text-base font-semibold">
                    {project.name}
                </p>
                <p className="truncate text-sm text-muted-foreground">
                    {project.slug}
                </p>
                {!stat.online && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {stat.lastSeenAt
                            ? `No data since ${formatTime(stat.lastSeenAt)}`
                            : 'No data in the last 24h'}
                    </p>
                )}
            </div>

            <div className="mt-3 flex items-end justify-between gap-4">
                <div>
                    <p className="text-2xl font-semibold tabular-nums">
                        {stat.requests.toLocaleString()}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        requests · 24h
                    </p>
                </div>

                <div className="flex flex-wrap justify-end gap-x-3 gap-y-1 text-xs">
                    {stat.clientErrors > 0 && (
                        <span className="font-medium text-muted-foreground tabular-nums">
                            {stat.clientErrors.toLocaleString()} 4XX
                        </span>
                    )}
                    {stat.onlineUsers > 0 && (
                        <span className="flex items-center gap-1 font-medium tabular-nums">
                            <span
                                aria-hidden
                                className={`size-1.5 rounded-full ${TONE_DOT.neutral}`}
                            />
                            {stat.onlineUsers.toLocaleString()} online
                        </span>
                    )}
                </div>
            </div>

            {hasData ? (
                <BarChart
                    points={stat.statusTimeline.map((point) => ({
                        at: point.at,
                        segments: [
                            {
                                label: '1XX-3XX',
                                value: point.success,
                                tone: 'neutral',
                            },
                            {
                                label: '4XX',
                                value: point.client_error,
                                tone: 'warning',
                            },
                            {
                                label: '5XX',
                                value: point.server_error,
                                tone: 'critical',
                            },
                        ],
                    }))}
                />
            ) : (
                <p className="mt-6 mb-2 text-sm text-muted-foreground">
                    No data received yet -- send a request with this project's
                    token to see it here.
                </p>
            )}
        </Link>
    );
}
