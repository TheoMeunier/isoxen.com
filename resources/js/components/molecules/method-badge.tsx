/**
 * A colour-coded pill for an HTTP verb -- GET (and other read-only or
 * unrecognised verbs) stays neutral gray since it's the overwhelming
 * majority of traffic almost everywhere; the mutating verbs each get their
 * own colour so a route's method is readable at a glance in a dense table
 * (see RoutesTable).
 */
const METHOD_COLORS: Record<string, string> = {
    POST: 'bg-[var(--color-method-post)]/10 text-[var(--color-method-post-contrast)] dark:bg-[var(--color-method-post)]/15',
    PUT: 'bg-[var(--color-method-put)]/10 text-[var(--color-method-put-contrast)] dark:bg-[var(--color-method-put)]/15',
    PATCH: 'bg-[var(--color-tone-warning)]/10 text-[var(--color-tone-warning-contrast)] dark:bg-[var(--color-tone-warning)]/15',
    DELETE: 'bg-[var(--color-method-delete)]/10 text-[var(--color-method-delete-contrast)] dark:bg-[var(--color-method-delete)]/15',
};

export function MethodBadge({ method }: { method: string | null }) {
    if (!method) {
        return <span className="text-muted-foreground">—</span>;
    }

    const classes = METHOD_COLORS[method] ?? 'bg-muted text-muted-foreground';

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${classes}`}
        >
            {method}
        </span>
    );
}
