import { Link, usePage } from '@inertiajs/react';
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
    ObservabilityCategories,
    ObservabilityCategory,
} from '@/types/observability';

// Icons are presentational and have no PHP-side equivalent, so they stay a
// local lookup keyed by the same slug ObservabilityCategories::all() uses.
// Everything else about a category (label, type, enabled, group) comes from
// the `observabilityCategories` shared prop -- see HandleInertiaRequests and
// ObservabilityCategories.php, the single source of truth for that data.
const CATEGORY_ICONS: Record<ObservabilityCategory, LucideIcon> = {
    requests: Globe,
    jobs: BriefcaseBusiness,
    commands: Braces,
    'scheduled-tasks': CalendarClock,
    exceptions: TriangleAlert,
    queries: Database,
    notifications: Bell,
    mail: Mail,
    cache: Zap,
    'outgoing-requests': ArrowUpRight,
    metrics: BarChart3,
    users: Users,
    logs: ScrollText,
};

// Likewise presentational: which categories get the "critical" badge tint
// when they have a nonzero count. Exceptions is the only one today.
const CATEGORY_TONES: Partial<Record<ObservabilityCategory, 'critical'>> = {
    exceptions: 'critical',
};

function CategoryItem({
    slug,
    label,
    type,
    enabled,
    projectId,
    active,
    count,
}: {
    slug: ObservabilityCategory;
    label: string;
    type: string | null;
    enabled: boolean;
    projectId: number;
    active: boolean;
    count?: number;
}) {
    const Icon = CATEGORY_ICONS[slug];

    if (!enabled) {
        return (
            <SidebarMenuItem>
                <SidebarMenuButton
                    disabled
                    tooltip={{ children: `${label} — coming soon` }}
                    className="cursor-not-allowed opacity-50"
                >
                    <Icon />
                    <span>{label}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={active}
                tooltip={{ children: label }}
            >
                <Link href={show({ project: projectId, category: slug })} prefetch>
                    <Icon />
                    <span>{label}</span>
                </Link>
            </SidebarMenuButton>

            {type && count !== undefined && count > 0 && (
                <SidebarMenuBadge
                    className={
                        CATEGORY_TONES[slug] === 'critical'
                            ? 'bg-[var(--color-tone-critical)]/10 font-semibold text-[var(--color-tone-critical)] peer-hover/menu-button:text-[var(--color-tone-critical)] peer-data-[active=true]/menu-button:text-[var(--color-tone-critical)]'
                            : undefined
                    }
                >
                    {count}
                </SidebarMenuBadge>
            )}
        </SidebarMenuItem>
    );
}

function CategoryGroup({
    label,
    group,
    categories,
    project,
    active,
    counts,
}: {
    label: string;
    group: 'activity' | 'monitoring';
    categories: ObservabilityCategories;
    project: CurrentProject;
    active: string;
    counts: Record<string, number>;
}) {
    const slugs = (
        Object.entries(categories) as [
            ObservabilityCategory,
            ObservabilityCategories[ObservabilityCategory],
        ][]
    ).filter(([, category]) => category.group === group);

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {slugs.map(([slug, category]) => (
                    <CategoryItem
                        key={slug}
                        slug={slug}
                        label={category.label}
                        type={category.type}
                        enabled={category.enabled}
                        projectId={project.id}
                        active={active === slug}
                        count={
                            category.type
                                ? counts[category.type]
                                : undefined
                        }
                    />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

export function NavProject({
    project,
    active,
    counts,
}: {
    project: CurrentProject;
    active: string;
    counts: Record<string, number>;
}) {
    // Only present while viewing a project (see HandleInertiaRequests) --
    // NavProject itself is only rendered in that situation (see
    // AppSidebar), so this is always populated in practice.
    const { props } = usePage<{
        observabilityCategories?: ObservabilityCategories;
    }>();
    const categories = props.observabilityCategories ?? ({} as ObservabilityCategories);

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
                                href={show({
                                    project: project.id,
                                    category: 'information',
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
                group="activity"
                categories={categories}
                project={project}
                active={active}
                counts={counts}
            />

            <CategoryGroup
                label="Monitoring"
                group="monitoring"
                categories={categories}
                project={project}
                active={active}
                counts={counts}
            />
        </>
    );
}
