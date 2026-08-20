import { Form, Head, Link } from '@inertiajs/react';
import { Check, Copy, Pencil } from 'lucide-react';
import { useState } from 'react';
import DeleteProjectController from '@/actions/App/Watch/Projects/Controllers/DeleteProjectController';
import EditProjectController from '@/actions/App/Watch/Projects/Controllers/EditProjectController';
import Heading from '@/components/heading';
import { ActivityChart } from '@/components/molecules/activity-chart';
import ConfirmDialog from '@/components/molecules/confirm-dialog';
import InputError from '@/components/molecules/forms/input-error';
import { PagerLinks } from '@/components/molecules/pager-links';
import { StatTiles } from '@/components/molecules/stat-tiles';
import { StatusBadge } from '@/components/molecules/status-badge';
import { SlowEndpointsTable } from '@/components/organisms/slow-endpoints-table';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { index } from '@/routes/projects';
import { show as showTrace } from '@/routes/projects/traces';
import type {
    CategorySummary,
    EndpointStat,
    LogEntry,
    MetricEntry,
    ObservabilityCategory,
    Paginated,
    SpanEntry,
    TimelinePoint,
} from '@/types/observability';
import type { Project } from '@/types/project';

const SPAN_KINDS: Record<number, string> = {
    0: 'Unspecified',
    1: 'Internal',
    2: 'Server',
    3: 'Client',
    4: 'Producer',
    5: 'Consumer',
};

// Categories whose entries don't come from `otel_spans` -- everything else
// (Requests, Jobs, Queries, Exceptions, ...) is a span filtered by `type`
// and rendered with the same generic table for now. Mirrors
// App\Watch\Ingestion\Support\ObservabilityCategories.
const LOGS_CATEGORY: ObservabilityCategory = 'logs';
const METRICS_CATEGORY: ObservabilityCategory = 'metrics';

// Labels for the panel heading. The sidebar owns the same labels for its
// own links (components/organisms/sidebar/nav-project.tsx).
const CATEGORY_LABELS: Record<ObservabilityCategory, string> = {
    requests: 'Requests',
    jobs: 'Jobs',
    commands: 'Commands',
    'scheduled-tasks': 'Scheduled Tasks',
    exceptions: 'Exceptions',
    queries: 'Queries',
    notifications: 'Notifications',
    mail: 'Mail',
    cache: 'Cache',
    'outgoing-requests': 'Outgoing Requests',
    metrics: 'Metrics',
    users: 'Users',
    logs: 'Logs',
};

function formatTime(value: string): string {
    // NOTE: `value` comes straight from the database column as a raw
    // string (these rows are plain query-builder results, not cast Eloquent
    // attributes). This hasn't been checked against the actual Postgres
    // driver output yet -- verify this renders correctly once real data
    // flows through, and adjust the parsing here if needed.
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function formatDuration(nanos: number | null): string {
    if (nanos === null) {
        return '—';
    }

    return `${(nanos / 1_000_000).toFixed(1)} ms`;
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
            <p className="text-sm font-medium">No data received yet</p>
            <p className="max-w-sm text-sm text-muted-foreground">{message}</p>
        </div>
    );
}

function SpansTable({
    entries,
    projectId,
}: {
    entries: SpanEntry[];
    projectId: number;
}) {
    return (
        <table className="w-full text-left text-sm">
            <thead className="text-muted-foreground">
                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                    <th className="py-2 pr-4 font-medium">Time</th>
                    <th className="py-2 pr-4 font-medium">Name</th>
                    <th className="py-2 pr-4 font-medium">Kind</th>
                    <th className="py-2 pr-4 font-medium">Duration</th>
                    <th className="py-2 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                {entries.map((entry, i) => (
                    <tr
                        key={`${entry.trace_id}-${entry.span_id}-${i}`}
                        className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/60"
                    >
                        <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                            {formatTime(entry.time)}
                        </td>
                        <td className="py-2 pr-4">
                            {entry.trace_id ? (
                                <Link
                                    href={showTrace.url({
                                        project: projectId,
                                        trace: entry.trace_id,
                                    })}
                                    className="hover:underline"
                                >
                                    {entry.name ?? '—'}
                                </Link>
                            ) : (
                                (entry.name ?? '—')
                            )}
                        </td>
                        <td className="py-2 pr-4">
                            {entry.kind !== null
                                ? (SPAN_KINDS[entry.kind] ?? entry.kind)
                                : '—'}
                        </td>
                        <td className="py-2 pr-4">
                            {formatDuration(entry.duration_nanos)}
                        </td>
                        <td className="py-2">
                            <StatusBadge code={entry.status_code} />
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function MetricsTable({ entries }: { entries: MetricEntry[] }) {
    return (
        <table className="w-full text-left text-sm">
            <thead className="text-muted-foreground">
                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                    <th className="py-2 pr-4 font-medium">Time</th>
                    <th className="py-2 pr-4 font-medium">Name</th>
                    <th className="py-2 pr-4 font-medium">Type</th>
                    <th className="py-2 font-medium">Value</th>
                </tr>
            </thead>
            <tbody>
                {entries.map((entry, i) => (
                    <tr
                        key={`${entry.name}-${i}`}
                        className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/60"
                    >
                        <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                            {formatTime(entry.time)}
                        </td>
                        <td className="py-2 pr-4">{entry.name ?? '—'}</td>
                        <td className="py-2 pr-4">{entry.type ?? '—'}</td>
                        <td className="py-2">
                            {entry.value !== null ? entry.value : '—'}
                            {entry.unit ? ` ${entry.unit}` : ''}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function LogsTable({ entries }: { entries: LogEntry[] }) {
    return (
        <table className="w-full text-left text-sm">
            <thead className="text-muted-foreground">
                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                    <th className="py-2 pr-4 font-medium">Time</th>
                    <th className="py-2 pr-4 font-medium">Severity</th>
                    <th className="py-2 font-medium">Message</th>
                </tr>
            </thead>
            <tbody>
                {entries.map((entry, i) => (
                    <tr
                        key={`${entry.trace_id}-${i}`}
                        className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/60"
                    >
                        <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                            {formatTime(entry.time)}
                        </td>
                        <td className="py-2 pr-4">
                            {entry.severity_text ?? '—'}
                        </td>
                        <td className="py-2">{entry.body ?? '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

type Entries =
    Paginated<SpanEntry> | Paginated<MetricEntry> | Paginated<LogEntry>;

export default function ProjectsShow({
    project,
    activeCategory,
    entries,
    summary,
    timeline,
    slowEndpoints,
}: {
    project: Project;
    activeCategory: ObservabilityCategory;
    entries: Entries;
    summary: CategorySummary;
    timeline: TimelinePoint[];
    slowEndpoints: EndpointStat[];
}) {
    const [copiedText, copy] = useClipboard();
    const [isEditOpen, setIsEditOpen] = useState(false);

    return (
        <>
            <Head title={project.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title={project.name} description={project.slug} />

                    <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                        <DialogTrigger asChild>
                            <Button variant="outline">
                                <Pencil />
                                Edit
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Edit project</DialogTitle>

                            <Form
                                {...EditProjectController.execute.form(
                                    project.id,
                                )}
                                onSuccess={() => setIsEditOpen(false)}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Name</Label>

                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                autoFocus
                                                defaultValue={project.name}
                                            />

                                            <InputError message={errors.name} />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">
                                                    Cancel
                                                </Button>
                                            </DialogClose>

                                            <Button
                                                disabled={processing}
                                                asChild
                                            >
                                                <button type="submit">
                                                    Save
                                                </button>
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <p className="font-medium">Ingestion token</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Configure your app's OpenTelemetry exporter to send data
                        to this project using this token.
                    </p>

                    {project.token && (
                        <div className="mt-3 flex items-center gap-2">
                            <code className="rounded-md bg-muted px-3 py-2 text-sm">
                                {project.token}
                            </code>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => copy(project.token as string)}
                            >
                                {copiedText === project.token ? (
                                    <Check />
                                ) : (
                                    <Copy />
                                )}
                            </Button>
                        </div>
                    )}
                </div>

                {summary.total > 0 && (
                    <>
                        <StatTiles
                            summary={summary}
                            unit={CATEGORY_LABELS[activeCategory].toLowerCase()}
                        />

                        <ActivityChart points={timeline} />
                    </>
                )}

                {activeCategory === 'requests' && slowEndpoints.length > 0 && (
                    <SlowEndpointsTable endpoints={slowEndpoints} />
                )}

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <p className="font-medium capitalize">
                            {CATEGORY_LABELS[activeCategory]}
                        </p>
                    </div>

                    <div className="p-4">
                        {entries.data.length === 0 ? (
                            <EmptyState
                                message={`Once your app starts sending ${CATEGORY_LABELS[activeCategory].toLowerCase()} with this token, they'll show up here.`}
                            />
                        ) : (
                            <div className="space-y-4">
                                {activeCategory === LOGS_CATEGORY && (
                                    <LogsTable
                                        entries={entries.data as LogEntry[]}
                                    />
                                )}
                                {activeCategory === METRICS_CATEGORY && (
                                    <MetricsTable
                                        entries={entries.data as MetricEntry[]}
                                    />
                                )}
                                {activeCategory !== LOGS_CATEGORY &&
                                    activeCategory !== METRICS_CATEGORY && (
                                        <SpansTable
                                            entries={
                                                entries.data as SpanEntry[]
                                            }
                                            projectId={project.id}
                                        />
                                    )}

                                <PagerLinks
                                    prevPageUrl={entries.prev_page_url}
                                    nextPageUrl={entries.next_page_url}
                                />
                            </div>
                        )}
                    </div>
                </div>

                <ConfirmDialog
                    trigger={
                        <Button variant="destructive" className="self-start">
                            Delete project
                        </Button>
                    }
                    title="Delete this project?"
                    description="This permanently deletes the project and revokes its ingestion token. This cannot be undone."
                    confirmLabel="Delete project"
                    variant="destructive"
                    form={DeleteProjectController.execute.form(project.id)}
                />
            </div>
        </>
    );
}

ProjectsShow.layout = {
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
    ],
};
