import type { OnlineUser } from '@/types/observability';

function formatSince(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const minutes = Math.max(
        0,
        Math.round((Date.now() - date.getTime()) / 60_000),
    );

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.round(minutes / 60);

    return hours < 24 ? `${hours}h ago` : date.toLocaleString();
}

// Name/email only show up here once the monitored application opts in via
// `OpenTelemetry::user(...)` (see UserInstrumentation) -- until then every
// row falls back to the raw enduser.id, which is always present.
function displayName(user: OnlineUser): string {
    return user.name ?? user.email ?? user.id;
}

/**
 * Who's currently connected -- inferred from login/logout events, not a
 * real session check (see OnlineUsersQuery). The page polls this prop on an
 * interval (see show.tsx) rather than pushing updates: the app has no
 * WebSocket server configured yet, so this is the lightest way to keep the
 * list current without standing up one.
 */
export function OnlineUsersPanel({ users }: { users: OnlineUser[] }) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <div className="flex items-center gap-2 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <span
                    aria-hidden
                    className="size-2 rounded-full bg-[#1a9d5c] dark:bg-[#22a878]"
                />
                <p className="font-medium">
                    {users.length} online{' '}
                    <span className="font-normal text-muted-foreground">
                        · updates automatically
                    </span>
                </p>
            </div>

            {users.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                    <p className="text-sm font-medium">No one online</p>
                    <p className="max-w-sm text-sm text-muted-foreground">
                        Once someone logs into your app, they'll show up here
                        for as long as they're connected.
                    </p>
                </div>
            ) : (
                <ul>
                    {users.map((user) => (
                        <li
                            key={user.id}
                            className="flex items-center justify-between gap-4 border-b border-sidebar-border/40 px-4 py-3 last:border-0 dark:border-sidebar-border/60"
                        >
                            <div className="flex items-center gap-2">
                                <span
                                    aria-hidden
                                    className="size-1.5 rounded-full bg-[#1a9d5c] dark:bg-[#22a878]"
                                />
                                <span className="font-medium">
                                    {displayName(user)}
                                </span>
                                {user.name && user.email && (
                                    <span className="text-sm text-muted-foreground">
                                        {user.email}
                                    </span>
                                )}
                            </div>

                            <span className="text-sm whitespace-nowrap text-muted-foreground">
                                Online since {formatSince(user.since)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
