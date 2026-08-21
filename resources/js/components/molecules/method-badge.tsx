const HTTP_METHODS = [
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    'HEAD',
    'OPTIONS',
] as const;
type KnownMethod = (typeof HTTP_METHODS)[number];
type ColouredMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
export function parseHttpMethod(name: string | null): KnownMethod | null {
    if (!name) {
        return null;
    }

    const spaceIndex = name.indexOf(' ');

    if (spaceIndex === -1) {
        return null;
    }

    const head = name.slice(0, spaceIndex);

    return (HTTP_METHODS as readonly string[]).includes(head)
        ? (head as KnownMethod)
        : null;
}
export function stripHttpMethod(
    name: string | null,
    method: string | null,
): string {
    if (!name) {
        return '—';
    }

    return method ? name.slice(method.length + 1) : name;
}
const METHOD_COLORS: Record<ColouredMethod, string> = {
    GET: 'bg-[var(--color-tone-neutral)]/10 text-[var(--color-tone-neutral)] dark:bg-[var(--color-tone-neutral)]/15',
    POST: 'bg-[var(--color-method-post)]/10 text-[var(--color-method-post-contrast)] dark:bg-[var(--color-method-post)]/15',
    PUT: 'bg-[var(--color-method-put)]/10 text-[var(--color-method-put-contrast)] dark:bg-[var(--color-method-put)]/15',
    PATCH: 'bg-[var(--color-tone-warning)]/10 text-[var(--color-tone-warning-contrast)] dark:bg-[var(--color-tone-warning)]/15',
    DELETE: 'bg-[var(--color-method-delete)]/10 text-[var(--color-method-delete-contrast)] dark:bg-[var(--color-method-delete)]/15',
};
function isColouredMethod(method: string): method is ColouredMethod {
    return method in METHOD_COLORS;
}
export function MethodBadge({ method }: { method: string | null }) {
    if (!method) {
        return <span className="text-muted-foreground">—</span>;
    }

    const classes = isColouredMethod(method)
        ? METHOD_COLORS[method]
        : 'bg-muted text-muted-foreground';

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${classes}`}
        >
            {method}
        </span>
    );
}
