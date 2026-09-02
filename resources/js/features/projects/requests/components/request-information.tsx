import type { ReactNode } from 'react';
import { formatDurationMs } from '@/lib/datetime';
import { formatBytes } from '@/lib/utils';

interface RequestInformationProps {
    occurrence: any;
    noTopBorder?: boolean;
}

export default function RequestInformation({
    occurrence,
    noTopBorder,
}: RequestInformationProps) {
    const user = occurrence.user;
    const userLabel = user ? (user.email ?? user.name ?? user.id ?? '—') : '—';
    const {
        queries,
        outgoing_requests,
        queued_jobs,
        cache,
        mail,
        notifications,
    } = occurrence.activity;
    const { storage, hydrated_models } = occurrence;

    return (
        <div
            className={
                noTopBorder
                    ? 'grid grid-cols-1 gap-x-10 gap-y-6 sm:grid-cols-2'
                    : 'mt-4 grid grid-cols-1 gap-x-10 gap-y-6 border-t border-sidebar-border/70 pt-4 sm:grid-cols-2'
            }
        >
            <div className="space-y-6">
                <ActivityGroup title="Info">
                    {occurrence.route_name && (
                        <MiniRow
                            label="Route name"
                            value={occurrence.route_name}
                        />
                    )}
                    {occurrence.action && (
                        <MiniRow label="Action" value={occurrence.action} />
                    )}
                    <MiniRow
                        label="Status code"
                        value={
                            occurrence.status_code !== null
                                ? String(occurrence.status_code)
                                : '—'
                        }
                    />
                    <MiniRow
                        label="Duration"
                        value={formatDurationMs(occurrence.duration_ms)}
                    />
                    <MiniRow
                        label="Request size"
                        value={
                            occurrence.request_size !== null
                                ? formatBytes(occurrence.request_size)
                                : '—'
                        }
                    />
                    <MiniRow
                        label="Response size"
                        value={
                            occurrence.response_size !== null
                                ? formatBytes(occurrence.response_size)
                                : '—'
                        }
                    />
                    <MiniRow
                        label="Peak memory"
                        value={
                            occurrence.peak_memory !== null
                                ? formatBytes(occurrence.peak_memory)
                                : '—'
                        }
                    />
                </ActivityGroup>

                <ActivityGroup title="User">
                    <MiniRow label="User" value={userLabel} />
                    <MiniRow
                        label="IP address"
                        value={occurrence.client_address ?? '—'}
                    />
                </ActivityGroup>
            </div>

            <div className="space-y-6">
                <ActivityGroup title="Details">
                    <MiniRow
                        label="Queries"
                        value={`${queries.count.toLocaleString()} / ${formatDurationMs(queries.total_ms)}`}
                    />
                    <MiniRow
                        label="Outgoing requests"
                        value={outgoing_requests.count.toLocaleString()}
                    />
                    <MiniRow
                        label="Queued jobs"
                        value={queued_jobs.count.toLocaleString()}
                    />
                    <MiniRow
                        label="Storage"
                        value={`${storage.reads.toLocaleString()} Reads / ${storage.writes.toLocaleString()} Writes`}
                    />
                    <MiniRow
                        label="Cache"
                        value={`${cache.hits} hit / ${cache.misses} miss / ${cache.writes} write`}
                    />
                    <MiniRow
                        label="Hydrated models"
                        value={hydrated_models.toLocaleString()}
                    />
                </ActivityGroup>

                <ActivityGroup title="Notifications and Mail">
                    <MiniRow label="Mail" value={mail.count.toLocaleString()} />
                    <MiniRow
                        label="Notifications"
                        value={notifications.count.toLocaleString()}
                    />
                </ActivityGroup>
            </div>
        </div>
    );
}

function MiniRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex min-w-0 items-baseline gap-2 text-sm">
            <span className="shrink-0 text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </span>
            <span className="h-px min-w-2 flex-1 -translate-y-0.75 border-b border-dotted border-sidebar-border/70" />
            <span className="min-w-0 text-right font-medium wrap-break-word tabular-nums">
                {value}
            </span>
        </div>
    );
}

function ActivityGroup({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2.5">
            <p className="text-sm font-semibold text-foreground">{title}</p>
            <div className="space-y-2">{children}</div>
        </div>
    );
}
