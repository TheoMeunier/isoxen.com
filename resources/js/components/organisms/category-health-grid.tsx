import { Link } from '@inertiajs/react';
import { CATEGORY_ICONS } from '@/components/organisms/sidebar/nav-project';
import { show } from '@/routes/projects';
import type { CategoryHealth } from '@/types/observability';

/**
 * The error pill on a health tile -- deliberately styled after StatusBadge's
 * critical state (same critical-tone pill with a dot) rather than inventing
 * a "success" color the rest of the app doesn't have: a healthy category
 * just gets plain muted text, exactly like a non-error StatusBadge.
 */
export function HealthBadge({ errors }: { errors: number }) {
    if (errors === 0) {
        return <span className="text-sm text-muted-foreground">OK</span>;
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-tone-critical)]/10 px-2 py-0.5 text-sm font-medium text-[var(--color-tone-critical)] dark:bg-[var(--color-tone-critical)]/15">
            <span aria-hidden className="size-1.5 rounded-full bg-current" />
            {errors.toLocaleString()} {errors === 1 ? 'error' : 'errors'}
        </span>
    );
}

function CategoryHealthTile({
    projectId,
    category,
}: {
    projectId: number;
    category: CategoryHealth;
}) {
    const Icon = CATEGORY_ICONS[category.slug];

    return (
        <Link
            href={show({ project: projectId, category: category.slug })}
            className="flex items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 p-4 transition-colors hover:bg-sidebar-accent dark:border-sidebar-border"
        >
            <div className="flex min-w-0 items-center gap-3">
                <Icon className="size-4 shrink-0 text-muted-foreground" />
                <div className="min-w-0">
                    <p className="truncate font-medium">{category.label}</p>
                    <p className="text-xs text-muted-foreground">
                        {category.total.toLocaleString()} · 24h
                    </p>
                </div>
            </div>

            {/* Metrics has no notion of failure -- see ProjectHealthOverviewQuery -- so it gets no badge at all. */}
            {category.slug !== 'metrics' && (
                <HealthBadge errors={category.errors} />
            )}
        </Link>
    );
}

const GROUPS: { label: string; group: 'activity' | 'monitoring' }[] = [
    { label: 'Activity', group: 'activity' },
    { label: 'Monitoring', group: 'monitoring' },
];

/**
 * The Overview tab's main content: every category the project has enabled,
 * grouped the same way the sidebar groups them, each tile showing its 24h
 * volume and whether it's clean or erroring.
 */
export function CategoryHealthGrid({
    projectId,
    categories,
}: {
    projectId: number;
    categories: CategoryHealth[];
}) {
    return (
        <div className="space-y-6">
            {GROUPS.map(({ label, group }) => {
                const items = categories.filter(
                    (category) => category.group === group,
                );

                if (items.length === 0) {
                    return null;
                }

                return (
                    <div key={group}>
                        <p className="mb-3 text-sm font-medium text-muted-foreground">
                            {label}
                        </p>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {items.map((category) => (
                                <CategoryHealthTile
                                    key={category.slug}
                                    projectId={projectId}
                                    category={category}
                                />
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
