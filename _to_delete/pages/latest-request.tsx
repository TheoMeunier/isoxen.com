import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight } from 'lucide-react';
import Heading from '@/components/heading';
import { HttpStatusBadge } from '@/components/molecules/http-status-badge';
import { MethodBadge } from '@/components/molecules/method-badge';
import { RequestActivityPanel } from '@/components/organisms/route-detail';
import { formatDurationMs, formatTime } from '@/lib/datetime';
import { index, show } from '@/routes/projects';
import { show as showRoute } from '@/routes/projects/requests';
import { show as showTrace } from '@/routes/projects/traces';
import type { LatestRequest } from '@/types/observability';
import type { Project } from '@/types/project';

/**
 * The sidebar's "Latest request" shortcut -- the newest request the project
 * has received, shown as its own page rather than nested inside a route's
 * "Recent requests" list (see RequestActivityPanel, which this reuses
 * directly). `request` is null the first time a fresh project is opened,
 * before it has received any traffic at all.
 */
export default function ProjectsShowLatestRequest({
    project,
    request,
}: {
    project: Project;
    request: LatestRequest | null;
}) {
    return (
        <>
            <Head title="Latest request" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Link
                    href={show({ project: project.id, category: 'requests' })}
                    className="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to {project.name}
                </Link>

                <Heading
                    title="Latest request"
                    description={
                        request
                            ? `Received ${formatTime(request.time)}`
                            : 'No requests recorded yet'
                    }
                />

                {request === null ? (
                    <section className="rounded-xl border border-sidebar-border/70 p-6 text-sm text-muted-foreground dark:border-sidebar-border">
                        This project hasn&apos;t received a request yet.
                    </section>
                ) : (
                    <section className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0 space-y-1.5">
                                <div className="flex items-center gap-2">
                                    <MethodBadge method={request.method} />
                                    <HttpStatusBadge
                                        status={request.status_code}
                                    />
                                    <span className="text-xs font-semibold text-muted-foreground tabular-nums">
                                        {formatDurationMs(request.duration_ms)}
                                    </span>
                                </div>

                                <div
                                    className="truncate font-mono text-xs text-muted-foreground"
                                    title={request.url ?? undefined}
                                >
                                    {request.url ?? 'Unknown URL'}
                                </div>

                                {request.fingerprint && (
                                    <Link
                                        href={showRoute({
                                            project: project.id,
                                            fingerprint: request.fingerprint,
                                        })}
                                        className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                                    >
                                        {request.route_name ??
                                            request.path ??
                                            'View route'}
                                        <ArrowUpRight className="size-3" />
                                    </Link>
                                )}
                            </div>

                            {request.trace_id && (
                                <Link
                                    href={showTrace({
                                        project: project.id,
                                        trace: request.trace_id,
                                    })}
                                    className="inline-flex shrink-0 items-center gap-1 rounded-full border border-sidebar-border/70 bg-background py-1 pr-2.5 pl-2 text-xs font-medium text-muted-foreground hover:border-sidebar-border hover:bg-accent hover:text-foreground dark:border-sidebar-border"
                                    title="View trace"
                                >
                                    <ArrowUpRight className="size-3" />
                                    Trace
                                </Link>
                            )}
                        </div>

                        <RequestActivityPanel occurrence={request} />
                    </section>
                )}
            </div>
        </>
    );
}

ProjectsShowLatestRequest.layout = (page: { project: Project }) => ({
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
