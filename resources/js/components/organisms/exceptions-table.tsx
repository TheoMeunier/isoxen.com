import { Link } from '@inertiajs/react';
import { Bug, Folder, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { HttpStatusBadge } from '@/components/molecules/http-status-badge';
import { EmptyState } from '@/components/organisms/empty-state';
import { Input } from '@/components/ui/input';
import { formatTime } from '@/lib/datetime';
import { show as showException } from '@/routes/projects/exceptions';
import type { ExceptionGroup } from '@/types/observability';

/**
 * The Exceptions page's main list: every distinct exception seen in the
 * window (grouped by type+file:line, see ExceptionGroupsQuery), ranked by
 * last seen, linking through to its full detail page -- this replaces the
 * raw entries list for this one category the same way RoutesTable replaces
 * it for Requests, since "which exceptions are recurring" is far more
 * useful here than a stream of individual occurrences.
 */
export function ExceptionsTable({
    projectId,
    groups,
}: {
    projectId: number;
    groups: ExceptionGroup[];
}) {
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term) {
            return groups;
        }

        return groups.filter((group) =>
            `${group.type} ${group.message ?? ''} ${group.file ?? ''}`
                .toLowerCase()
                .includes(term),
        );
    }, [groups, search]);

    const sorted = useMemo(
        () =>
            [...filtered].sort((a, b) =>
                a.last_seen < b.last_seen
                    ? 1
                    : a.last_seen > b.last_seen
                      ? -1
                      : 0,
            ),
        [filtered],
    );

    return (
        <div>
            <div className="flex items-center justify-between gap-4 px-4 py-3">
                <p className="flex items-center gap-2 text-lg font-medium">
                    <Bug className="size-5 text-muted-foreground" />
                    {groups.length.toLocaleString()}{' '}
                    {groups.length === 1 ? 'Exception' : 'Exceptions'}
                </p>

                {groups.length > 0 && (
                    <div className="relative w-full max-w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder="Search exceptions"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="h-8 pl-8 text-sm"
                        />
                    </div>
                )}
            </div>

            <div className="p-4">
                {groups.length === 0 ? (
                    <EmptyState message="Once your app throws an exception with this project's token, you'll see it grouped here." />
                ) : sorted.length === 0 ? (
                    <EmptyState message={`No exceptions match "${search}".`} />
                ) : (
                    <div className="divide-y divide-sidebar-border/40 dark:divide-sidebar-border/60">
                        {sorted.map((group) => (
                            <Link
                                key={group.fingerprint}
                                href={showException({
                                    project: projectId,
                                    fingerprint: group.fingerprint,
                                })}
                                className="-mx-4 flex items-center justify-between gap-4 px-4 py-3 hover:bg-accent/50"
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <HttpStatusBadge
                                            status={group.http_status}
                                        />
                                        <p className="truncate font-mono text-sm font-semibold text-[var(--color-tone-critical)]">
                                            {group.type}
                                        </p>
                                    </div>
                                    <p className="truncate text-sm font-medium text-foreground">
                                        {group.message ?? '—'}
                                    </p>
                                    {group.file && (
                                        <p className="flex items-center gap-1.5 truncate font-mono text-xs text-muted-foreground">
                                            <Folder className="size-3.5 shrink-0" />
                                            <span className="truncate">
                                                {group.file}
                                                {group.line !== null
                                                    ? `:${group.line}`
                                                    : ''}
                                            </span>
                                        </p>
                                    )}
                                </div>

                                <div className="flex shrink-0 items-center gap-6 text-right text-sm">
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Occurrences
                                        </p>
                                        <p className="font-medium tabular-nums">
                                            {group.occurrences.toLocaleString()}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Last seen
                                        </p>
                                        <p className="font-medium tabular-nums">
                                            {formatTime(group.last_seen)}
                                        </p>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
