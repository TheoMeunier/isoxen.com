import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    BarChart3,
    Bell,
    Braces,
    BriefcaseBusiness,
    CalendarClock,
    Database,
    Globe,
    Info,
    Mail,
    ScrollText,
    TriangleAlert,
    Users,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as projectsIndex, show } from '@/routes/projects';
import type {
    CurrentProject,
    ObservabilityCategory,
} from '@/types/observability';

type CategoryDef = {
    slug: ObservabilityCategory;
    label: string;
    icon: LucideIcon;
    /**
     * The span `type` this category filters on, used to read its count.
     * Absent for categories that don't read from `otel_spans` at all
     * (Metrics and Logs live in their own tables).
     */
    type?: string;
    /**
     * Whether this category is wired to real data yet.
     *
     * All of them now are: the client grew instrumentation for commands,
     * the scheduler, mail, notifications, cache and users. The flag stays
     * because new categories land here disabled before their sensor ships.
     * Mirrors `App\Watch\Ingestion\Support\ObservabilityCategories::all()`.
     *
     * Note that an enabled category can still be legitimately empty — Cache
     * is off by default in the client (ISOXEN_SENSOR_CACHE), because a
     * cache-heavy request emits hundreds of spans.
     */
    enabled: boolean;
};

const ACTIVITY: CategoryDef[] = [
    {
        slug: 'requests',
        label: 'Requests',
        icon: Globe,
        type: 'request',
        enabled: true,
    },
    {
        slug: 'jobs',
        label: 'Jobs',
        icon: BriefcaseBusiness,
        type: 'job',
        enabled: true,
    },
    {
        slug: 'commands',
        label: 'Commands',
        icon: Braces,
        type: 'command',
        enabled: true,
    },
    {
        slug: 'scheduled-tasks',
        label: 'Scheduled Tasks',
        icon: CalendarClock,
        type: 'scheduled_task',
        enabled: true,
    },
    {
        slug: 'exceptions',
        label: 'Exceptions',
        icon: TriangleAlert,
        type: 'exception',
        enabled: true,
    },
    {
        slug: 'queries',
        label: 'Queries',
        icon: Database,
        type: 'query',
        enabled: true,
    },
    {
        slug: 'notifications',
        label: 'Notifications',
        icon: Bell,
        type: 'notification',
        enabled: true,
    },
    { slug: 'mail', label: 'Mail', icon: Mail, type: 'mail', enabled: true },
    { slug: 'cache', label: 'Cache', icon: Zap, type: 'cache', enabled: true },
    {
        slug: 'outgoing-requests',
        label: 'Outgoing Requests',
        icon: ArrowUpRight,
        type: 'outgoing_request',
        enabled: true,
    },
    { slug: 'metrics', label: 'Metrics', icon: BarChart3, enabled: true },
];

const MONITORING: CategoryDef[] = [
    { slug: 'users', label: 'Users', icon: Users, type: 'user', enabled: true },
    { slug: 'logs', label: 'Logs', icon: ScrollText, enabled: true },
];

function CategoryItem({
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
            <SidebarMenuItem>
                <SidebarMenuButton
                    disabled
                    tooltip={{ children: `${category.label} — coming soon` }}
                    className="cursor-not-allowed opacity-50"
                >
                    <Icon />
                    <span>{category.label}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={active}
                tooltip={{ children: category.label }}
            >
                <Link
                    href={show.url(projectId, {
                        query: { category: category.slug },
                    })}
                    prefetch
                >
                    <Icon />
                    <span>{category.label}</span>
                </Link>
            </SidebarMenuButton>

            {count !== undefined && count > 0 && (
                <SidebarMenuBadge>{count}</SidebarMenuBadge>
            )}
        </SidebarMenuItem>
    );
}

function CategoryGroup({
    label,
    categories,
    project,
    active,
    counts,
}: {
    label: string;
    categories: CategoryDef[];
    project: CurrentProject;
    active: string;
    counts: Record<string, number>;
}) {
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {categories.map((category) => (
                    <CategoryItem
                        key={category.slug}
                        category={category}
                        projectId={project.id}
                        active={active === category.slug}
                        count={
                            category.type ? counts[category.type] : undefined
                        }
                    />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

/**
 * The app sidebar's contents while a project is open: a way back to the
 * project list, then that project's observability categories.
 */
export function NavProject({
    project,
    active,
    counts,
}: {
    project: CurrentProject;
    active: string;
    counts: Record<string, number>;
}) {
    return (
        <>
            <SidebarGroup className="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'Back to projects' }}
                        >
                            <Link href={projectsIndex()} prefetch>
                                <ArrowLeft />
                                <span className="truncate">{project.name}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>Project</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            isActive={active === 'information'}
                            tooltip={{ children: 'Information' }}
                        >
                            <Link
                                href={show.url(project.id, {
                                    query: { category: 'information' },
                                })}
                                prefetch
                            >
                                <Info />
                                <span>Information</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <CategoryGroup
                label="Activity"
                categories={ACTIVITY}
                project={project}
                active={active}
                counts={counts}
            />

            <CategoryGroup
                label="Monitoring"
                categories={MONITORING}
                project={project}
                active={active}
                counts={counts}
            />
        </>
    );
}
