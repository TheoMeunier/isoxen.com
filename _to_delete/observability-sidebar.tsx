import { Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    BarChart3,
    Bell,
    Braces,
    BriefcaseBusiness,
    CalendarClock,
    Database,
    Globe,
    type LucideIcon,
    Mail,
    ScrollText,
    TriangleAlert,
    Users,
    Zap,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { show } from '@/routes/projects';
import type { ObservabilityCategory } from '@/types/observability';

type CategoryDef = {
    slug: ObservabilityCategory;
    label: string;
    icon: LucideIcon;
    /**
     * Whether this category is wired to real data yet. Categories shown
     * here but not yet enabled require instrumentation isoxen's client
     * doesn't implement yet (Artisan commands, the scheduler, mail,
     * notifications, cache, users). Mirrors
     * `App\Watch\Ingestion\Support\ObservabilityCategories::all()`.
     */
    enabled: boolean;
};

const ACTIVITY: CategoryDef[] = [
    { slug: 'requests', label: 'Requests', icon: Globe, enabled: true },
    { slug: 'jobs', label: 'Jobs', icon: BriefcaseBusiness, enabled: true },
    { slug: 'commands', label: 'Commands', icon: Braces, enabled: false },
    {
        slug: 'scheduled-tasks',
        label: 'Scheduled Tasks',
        icon: CalendarClock,
        enabled: false,
    },
    {
        slug: 'exceptions',
        label: 'Exceptions',
        icon: TriangleAlert,
        enabled: true,
    },
    { slug: 'queries', label: 'Queries', icon: Database, enabled: true },
    {
        slug: 'notifications',
        label: 'Notifications',
        icon: Bell,
        enabled: false,
    },
    { slug: 'mail', label: 'Mail', icon: Mail, enabled: false },
    { slug: 'cache', label: 'Cache', icon: Zap, enabled: false },
    {
        slug: 'outgoing-requests',
        label: 'Outgoing Requests',
        icon: ArrowUpRight,
        enabled: true,
    },
    { slug: 'metrics', label: 'Metrics', icon: BarChart3, enabled: true },
];

const MONITORING: CategoryDef[] = [
    { slug: 'users', label: 'Users', icon: Users, enabled: false },
    { slug: 'logs', label: 'Logs', icon: ScrollText, enabled: true },
];

// Maps a sidebar category slug to the span `type` it filters on, mirroring
// App\Watch\Ingestion\Support\ObservabilityCategories on the backend.
// `metrics` and `logs` aren't spans (different tables), so they have no
// count here.
const CATEGORY_TYPES: Partial<Record<ObservabilityCategory, string>> = {
    requests: 'request',
    jobs: 'job',
    commands: 'command',
    'scheduled-tasks': 'scheduled_task',
    exceptions: 'exception',
    queries: 'query',
    notifications: 'notification',
    mail: 'mail',
    cache: 'cache',
    'outgoing-requests': 'outgoing_request',
    users: 'user',
};

function CategoryLink({
    category,
    projectId,
    active,
    count,
}: {
    category: CategoryDef;
    projectId: number;
    active: boolean;
    count?: number;
}) {
    const Icon = category.icon;

    if (!category.enabled) {
        return (
            <div
                className="flex cursor-not-allowed items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground/50"
                title="Coming soon"
            >
                <Icon className="size-4" />
                <span>{category.label}</span>
            </div>
        );
    }

    return (
        <Link
            href={show.url(projectId, { query: { category: category.slug } })}
            preserveScroll
            className={cn(
                'flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm transition-colors',
                active
                    ? 'bg-sidebar-accent font-medium text-foreground'
                    : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground',
            )}
        >
            <span className="flex items-center gap-2">
                <Icon className="size-4" />
                <span>{category.label}</span>
            </span>
            {count !== undefined && count > 0 && (
                <span className="text-xs text-muted-foreground">
                    {count}
                </span>
            )}
        </Link>
    );
}

export function ObservabilitySidebar({
    projectId,
    active,
    counts,
}: {
    projectId: number;
    active: string;
    counts: Record<string, number>;
}) {
    return (
        <nav className="flex w-52 shrink-0 flex-col gap-4 p-4">
            <div>
                <p className="px-2 pb-1 text-xs font-medium text-muted-foreground uppercase">
                    Activity
                </p>
                <div className="flex flex-col gap-0.5">
                    {ACTIVITY.map((category) => {
                        const type = CATEGORY_TYPES[category.slug];

                        return (
                            <CategoryLink
                                key={category.slug}
                                category={category}
                                projectId={projectId}
                                active={active === category.slug}
                                count={type ? counts[type] : undefined}
                            />
                        );
                    })}
                </div>
            </div>

            <div>
                <p className="px-2 pb-1 text-xs font-medium text-muted-foreground uppercase">
                    Monitoring
                </p>
                <div className="flex flex-col gap-0.5">
                    {MONITORING.map((category) => (
                        <CategoryLink
                            key={category.slug}
                            category={category}
                            projectId={projectId}
                            active={active === category.slug}
                        />
                    ))}
                </div>
            </div>
        </nav>
    );
}
