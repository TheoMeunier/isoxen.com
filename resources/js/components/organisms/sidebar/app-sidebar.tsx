import { Link, usePage } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/organisms/sidebar/nav-footer';
import { NavMain } from '@/components/organisms/sidebar/nav-main';
import { NavProject } from '@/components/organisms/sidebar/nav-project';
import { NavUser } from '@/components/organisms/sidebar/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as projectsIndex } from '@/routes/projects';
import type { NavItem } from '@/types';
import type { CurrentProject } from '@/types/observability';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Projects',
        href: projectsIndex(),
        icon: FolderGit2,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

const DEFAULT_CATEGORY = 'requests';

export function AppSidebar() {
    // `currentProject` and `categoryCounts` are shared from
    // HandleInertiaRequests, and are only present while viewing a project.
    const { props, url } = usePage<{
        currentProject?: CurrentProject;
        categoryCounts?: Record<string, number>;
    }>();

    // The category is the last segment of `/projects/{project}/{category}`
    // (see routes/projects.php) -- a bare `/projects/{project}` only ever
    // hits the app while its redirect to the default category is in
    // flight, so the fallback below is mostly cosmetic.
    const segments = new URL(url, 'http://localhost').pathname
        .split('/')
        .filter(Boolean);
    const activeCategory = segments[2] ?? DEFAULT_CATEGORY;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {props.currentProject ? (
                    <NavProject
                        project={props.currentProject}
                        active={activeCategory}
                        counts={props.categoryCounts ?? {}}
                    />
                ) : (
                    <NavMain items={mainNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
