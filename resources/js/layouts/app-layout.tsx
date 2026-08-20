import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    actions,
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    actions?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs} actions={actions}>
            {children}
        </AppLayoutTemplate>
    );
}
