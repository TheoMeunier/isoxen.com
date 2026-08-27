import { Link } from '@inertiajs/react';
import { formatTime } from '@/lib/datetime';
import { show as showTrace } from '@/routes/projects/traces';
import type { RecentException } from '@/types/dashboard';

/**
 * The dashboard's "what's on fire" list -- the newest exception spans across
 * every project the user owns, newest first. Each row links to its trace
 * when one was captured; exceptions raised outside a traced request have no
 * trace_id and render as plain text instead.
 */
export function RecentExceptionsList({
    exceptions,
}: {
    exceptions: RecentException[];
}) {
    if (exceptions.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center gap-1 py-6 text-center">
                <p className="text-sm font-medium">
                    No exceptions in the last 24h
                </p>
                <p className="text-sm text-muted-foreground">
                    Nothing reported an exception across your projects recently.
                </p>
            </div>
        );
    }

    return (
        <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
            {exceptions.map((exception, index) => {
                const key = `${exception.projectId}-${exception.time}-${index}`;

                const content = (
                    <>
                        <div className="min-w-0">
                            <p className="truncate font-medium">
                                {exception.name ?? 'Exception'}
                            </p>
                            {exception.statusMessage && (
                                <p className="truncate text-sm text-muted-foreground">
                                    {exception.statusMessage}
                                </p>
                            )}
                        </div>
                        <div className="flex shrink-0 flex-col items-end gap-0.5 text-xs text-muted-foreground">
                            <span>{exception.projectName}</span>
                            <span>{formatTime(exception.time)}</span>
                        </div>
                    </>
                );

                return (
                    <li key={key} className="py-2.5 first:pt-0 last:pb-0">
                        {exception.traceId ? (
                            <Link
                                href={showTrace.url({
                                    project: exception.projectId,
                                    trace: exception.traceId,
                                })}
                                className="-mx-2 flex items-center justify-between gap-4 rounded-md px-2 py-1 transition-colors hover:bg-sidebar-accent"
                            >
                                {content}
                            </Link>
                        ) : (
                            <div className="flex items-center justify-between gap-4 px-2">
                                {content}
                            </div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
