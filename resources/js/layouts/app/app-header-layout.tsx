import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { AppContent } from '@/components/molecules/app-content';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    return (
        <AppShell variant="header">
            <AppHeader breadcrumbs={breadcrumbs} />
            <AppContent variant="header">{children}</AppContent>
        </AppShell>
    );
}
