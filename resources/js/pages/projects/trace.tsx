import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, Copy } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { LogSeverityBadge } from '@/components/molecules/log-severity-badge';
import { StatusBadge } from '@/components/molecules/status-badge';
import { TraceWaterfall } from '@/components/organisms/trace-waterfall';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatDuration, formatTime } from '@/lib/datetime';
import { spanDetail } from '@/lib/span-detail';
import { index, show } from '@/routes/projects';
import type { TraceLog, TraceSpan } from '@/types/observability';
import type { Project } from '@/types/project';
const SPAN_KINDS: Record<number, string> = {
    0: 'Unspecified',
    1: 'Internal',
    2: 'Server',
    3: 'Client',
    4: 'Producer',
    5: 'Consumer',
};
const TYPE_TO_CATEGORY: Record<string, string> = {
    request: 'requests',
    job: 'jobs',
    command: 'commands',
    scheduled_task: 'scheduled-tasks',
    exception: 'exceptions',
    query: 'queries',
    notification: 'notifications',
    mail: 'mail',
    cache: 'cache',
    outgoing_request: 'outgoing-requests',
    user: 'users',
};
const COLLAPSED_LINE_COUNT = 6;
function AttributeValue({ value }: { value: unknown }) {
    const [expanded, setExpanded] = useState(false);

    if (value === null || value === undefined) {
        return <span className="text-muted-foreground">—</span>;
    }

    if (typeof value === 'string') {
        return <span className="break-words">{value}</span>;
    }

    if (typeof value !== 'object') {
        return <span className="font-mono">{String(value)}</span>;
    }

    const pretty = JSON.stringify(value, null, 2);
    const lines = pretty.split('\n');
    const isLong = lines.length > COLLAPSED_LINE_COUNT;
    const shown =
        expanded || !isLong
            ? pretty
            : lines.slice(0, COLLAPSED_LINE_COUNT).join('\n');

    return (
        <div className="min-w-0">
            <pre className="overflow-x-auto rounded-md bg-muted px-2 py-1.5 font-mono text-xs whitespace-pre-wrap">
                {shown}
                {!expanded && isLong && '\n…'}
            </pre>

            {isLong && (
                <button
                    type="button"
                    onClick={() => setExpanded((current) => !current)}
                    className="mt-1 text-xs text-muted-foreground hover:text-foreground"
                >
                    {expanded
                        ? 'Show less'
                        : `Show more (${lines.length - COLLAPSED_LINE_COUNT} more lines)`}
                </button>
            )}
        </div>
    );
}
function AttributesList({
    attributes,
}: {
    attributes: Record<string, unknown> | null;
}) {
    if (!attributes || Object.keys(attributes).length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No attributes recorded for this span.
            </p>
        );
    }

    return (
        <dl className="grid grid-cols-[minmax(0,240px)_1fr] gap-x-4 gap-y-2 text-sm">
            {Object.entries(attributes).map(([key, value]) => (
                <div key={key} className="contents">
                    <dt className="truncate font-mono text-xs text-muted-foreground">
                        {key}
                    </dt>
                    <dd className="min-w-0 break-words">
                        <AttributeValue value={value} />
                    </dd>
                </div>
            ))}
        </dl>
    );
}
export default function ProjectsTrace({
    project,
    traceId,
    spans,
    logs,
}: {
    project: Project;
    traceId: string;
    spans: TraceSpan[];
    logs: TraceLog[];
}) {
    const [copiedText, copy] = useClipboard();
    const root = spans.find((span) => span.parent_span_id === null) ?? spans[0];
    const [selectedSpanId, setSelectedSpanId] = useState<string | null>(
        root?.span_id ?? null,
    );
    const selected =
        spans.find((span) => span.span_id === selectedSpanId) ?? root;
    const errorCount = spans.filter((span) => span.status_code === 2).length;
    const backCategory = root?.type
        ? (TYPE_TO_CATEGORY[root.type] ?? 'requests')
        : 'requests';

    return (
        <>
            <Head title={root?.name ?? 'Trace'} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Link
                    href={show.url(project.id, {
                        query: { category: backCategory },
                    })}
                    className="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to {project.name}
                </Link>

                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={root?.name ?? 'Trace'}
                        description={
                            spanDetail(
                                root ?? { type: null, attributes: null },
                            ) ?? undefined
                        }
                    />

                    <div className="flex shrink-0 items-center gap-2 rounded-lg border border-sidebar-border/70 px-2 py-1 dark:border-sidebar-border">
                        <code className="text-xs text-muted-foreground">
                            {traceId}
                        </code>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-6"
                            onClick={() => copy(traceId)}
                        >
                            {copiedText === traceId ? (
                                <Check className="size-3.5" />
                            ) : (
                                <Copy className="size-3.5" />
                            )}
                        </Button>
                    </div>
                </div>

                <div className="flex flex-col gap-4 sm:flex-row">
                    <div className="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Duration
                        </p>
                        <p className="mt-1 text-3xl font-semibold tabular-nums">
                            {formatDuration(root?.duration_nanos ?? null)}
                        </p>
                    </div>

                    <div className="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Spans
                        </p>
                        <p className="mt-1 text-3xl font-semibold tabular-nums">
                            {spans.length}
                        </p>
                    </div>

                    <div className="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Errors
                        </p>
                        <p
                            className={`mt-1 text-3xl font-semibold tabular-nums ${
                                errorCount > 0
                                    ? 'text-[var(--color-tone-critical)]'
                                    : 'text-foreground'
                            }`}
                        >
                            {errorCount}
                        </p>
                    </div>

                    <div className="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Started at
                        </p>
                        <p className="mt-1 text-sm font-medium">
                            {root ? formatTime(root.time) : '—'}
                        </p>
                    </div>
                </div>

                <div>
                    <p className="mb-2 text-sm font-medium">Timeline</p>
                    <TraceWaterfall
                        spans={spans}
                        selectedSpanId={selectedSpanId}
                        onSelectSpan={setSelectedSpanId}
                    />
                </div>

                {selected && (
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                            <p className="font-medium">
                                {selected.name ?? '—'}
                            </p>
                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                <span>
                                    {selected.kind !== null
                                        ? (SPAN_KINDS[selected.kind] ??
                                          selected.kind)
                                        : '—'}
                                </span>
                                <span>
                                    {formatDuration(selected.duration_nanos)}
                                </span>
                                <StatusBadge code={selected.status_code} />
                            </div>
                        </div>

                        <div className="p-4">
                            {selected.status_message && (
                                <p className="mb-4 rounded-md bg-[var(--color-tone-critical)]/10 px-3 py-2 text-sm text-[var(--color-tone-critical)] dark:bg-[var(--color-tone-critical)]/15">
                                    {selected.status_message}
                                </p>
                            )}

                            <AttributesList attributes={selected.attributes} />
                        </div>
                    </div>
                )}

                {logs.length > 0 && (
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                            <p className="font-medium">Logs</p>
                        </div>

                        <div className="p-4">
                            <table className="w-full text-left text-sm">
                                <thead className="text-muted-foreground">
                                    <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                        <th className="py-2 pr-4 font-medium">
                                            Time
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Severity
                                        </th>
                                        <th className="py-2 font-medium">
                                            Message
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.map((log, i) => (
                                        <tr
                                            key={`${log.span_id}-${i}`}
                                            className={`cursor-pointer border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/60 ${
                                                log.span_id === selectedSpanId
                                                    ? 'bg-muted/60'
                                                    : ''
                                            }`}
                                            onClick={() =>
                                                log.span_id &&
                                                setSelectedSpanId(log.span_id)
                                            }
                                        >
                                            <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                                                {formatTime(log.time)}
                                            </td>
                                            <td className="py-2 pr-4">
                                                <LogSeverityBadge entry={log} />
                                            </td>
                                            <td className="py-2">
                                                {log.body ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
ProjectsTrace.layout = (page: { project: Project }) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: page.project.name,
            href: show(page.project.id),
        },
    ],
});
