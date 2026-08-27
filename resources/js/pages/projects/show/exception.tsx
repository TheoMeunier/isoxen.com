import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, ChevronRight, Folder } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { HttpStatusBadge } from '@/components/molecules/http-status-badge';
import { StackTrace } from '@/components/molecules/stack-trace';
import { formatTime } from '@/lib/datetime';
import { index, show } from '@/routes/projects';
import { show as showTrace } from '@/routes/projects/traces';
import type { ExceptionDetail } from '@/types/observability';
import type { Project } from '@/types/project';

/**
 * One row of the Info panel: a timestamp with an optional "View trace" link
 * to the occurrence it came from -- first/last seen each point at a
 * different span, so each gets its own trace link rather than sharing one.
 */
function InfoRow({
    label,
    project,
    time,
    traceId,
}: {
    label: string;
    project: Project;
    time: string;
    traceId: string | null;
}) {
    return (
        <div className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <div className="flex items-center gap-2">
                <span className="font-medium tabular-nums">
                    {formatTime(time)}
                </span>
                {traceId && (
                    <Link
                        href={showTrace({
                            project: project.id,
                            trace: traceId,
                        })}
                        className="text-xs text-muted-foreground underline hover:text-foreground"
                    >
                        View trace
                    </Link>
                )}
            </div>
        </div>
    );
}

export default function ProjectsShowException({
    project,
    exception,
}: {
    project: Project;
    fingerprint: string;
    exception: ExceptionDetail;
}) {
    const [stackExpanded, setStackExpanded] = useState(true);

    return (
        <>
            <Head title={exception.type} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Link
                    href={show({ project: project.id, category: 'exceptions' })}
                    className="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to {project.name}
                </Link>

                <Heading
                    title={exception.type}
                    description={exception.message ?? undefined}
                />

                <div className="flex flex-col gap-4 sm:flex-row">
                    <div className="flex-1 space-y-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Info
                        </p>
                        <InfoRow
                            label="First seen"
                            project={project}
                            time={exception.first_seen}
                            traceId={exception.first_trace_id}
                        />
                        <InfoRow
                            label="Last seen"
                            project={project}
                            time={exception.last_seen}
                            traceId={exception.last_trace_id}
                        />
                    </div>

                    <div className="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Impact
                        </p>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    24h
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {exception.occurrences_24h.toLocaleString()}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    7d
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {exception.occurrences_7d.toLocaleString()}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    30d
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {exception.occurrences_30d.toLocaleString()}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <p className="font-medium">Exception</p>
                    </div>
                    <div className="space-y-2 p-4 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            <HttpStatusBadge status={exception.http_status} />
                            <span className="font-mono text-sm font-semibold text-[var(--color-tone-critical)]">
                                {exception.type}
                            </span>
                        </div>
                        {exception.message && (
                            <p className="text-base leading-snug font-medium text-foreground">
                                {exception.message}
                            </p>
                        )}
                        {exception.file && (
                            <p className="flex items-center gap-1.5 font-mono text-xs text-muted-foreground">
                                <Folder className="size-3.5 shrink-0" />
                                <span className="truncate">
                                    {exception.file}
                                    {exception.line !== null
                                        ? `:${exception.line}`
                                        : ''}
                                </span>
                            </p>
                        )}
                    </div>
                </div>

                {exception.stacktrace && (
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <button
                            type="button"
                            onClick={() =>
                                setStackExpanded((current) => !current)
                            }
                            className="flex w-full items-center gap-2 border-b border-sidebar-border/70 px-4 py-3 text-left font-medium dark:border-sidebar-border"
                        >
                            {stackExpanded ? (
                                <ChevronDown className="size-4" />
                            ) : (
                                <ChevronRight className="size-4" />
                            )}
                            Stack trace
                        </button>
                        {stackExpanded && (
                            <StackTrace raw={exception.stacktrace} />
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

ProjectsShowException.layout = (page: { project: Project }) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: page.project.name,
            href: show({ project: page.project.id, category: 'exceptions' }),
        },
    ],
});
