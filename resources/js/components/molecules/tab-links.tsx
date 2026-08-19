import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type TabLink = {
    value: string;
    label: string;
    href: string;
};

/**
 * A small, dependency-free tab bar backed by real Inertia links (each tab is
 * a distinct URL with a `?tab=` query param, resolved server-side). This
 * intentionally avoids pulling in a Radix tabs component for a single use
 * case; revisit if more tab-driven UI shows up elsewhere in the app.
 */
export function TabLinks({
    tabs,
    active,
}: {
    tabs: TabLink[];
    active: string;
}) {
    return (
        <div className="flex gap-1 border-b border-sidebar-border/70 dark:border-sidebar-border">
            {tabs.map((tab) => (
                <Link
                    key={tab.value}
                    href={tab.href}
                    preserveScroll
                    className={cn(
                        'border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                        tab.value === active
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </div>
    );
}
