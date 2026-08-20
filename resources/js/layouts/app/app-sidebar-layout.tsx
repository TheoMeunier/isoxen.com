import { AppContent } from '@/components/molecules/app-content';
import { AppShell } from '@/components/organisms/app/app-shell';
import { AppSidebar } from '@/components/organisms/sidebar/app-sidebar';
import { AppSidebarHeader } from '@/components/organisms/sidebar/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    actions,
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} actions={actions} />
                {children}
            </AppContent>
        </AppShell>
    );
}
