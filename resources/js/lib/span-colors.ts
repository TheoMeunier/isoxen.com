const SPAN_TYPE_COLORS: Record<string, string> = {
    request: 'bg-[var(--color-tone-neutral)]',
    query: 'bg-[var(--color-method-post)]',
    cache: 'bg-[var(--color-method-put)]',
    job: 'bg-[var(--color-tone-warning)]',
    outgoing_request: 'bg-[var(--color-method-delete)]',
    mail: 'bg-[var(--color-span-mail)]',
    notification: 'bg-[var(--color-span-notification)]',
    exception: 'bg-[var(--color-span-exception)]',
};
const OTHER_COLOR = 'bg-[var(--color-span-other)]';
export function spanTypeColor(type: string | null): string {
    return (type && SPAN_TYPE_COLORS[type]) ?? OTHER_COLOR;
}

/**
 * The same per-type colour as {@see spanTypeColor}, as a light background +
 * matching text pair for a standalone badge (see SourceBadge in
 * query-detail.tsx) -- written out as complete class strings rather than
 * built from spanTypeColor()'s value at runtime (e.g. `${color}/10`),
 * because Tailwind only generates CSS for class names it can find written
 * literally in the source; a name assembled from a template literal at
 * runtime never appears as a match and silently produces no styles.
 */
const SPAN_TYPE_BADGE_COLORS: Record<string, string> = {
    request:
        'bg-[var(--color-tone-neutral)]/10 text-[var(--color-tone-neutral)]',
    query: 'bg-[var(--color-method-post)]/10 text-[var(--color-method-post-contrast)]',
    cache: 'bg-[var(--color-method-put)]/10 text-[var(--color-method-put-contrast)]',
    job: 'bg-[var(--color-tone-warning)]/10 text-[var(--color-tone-warning-contrast)]',
    outgoing_request:
        'bg-[var(--color-method-delete)]/10 text-[var(--color-method-delete-contrast)]',
    mail: 'bg-[var(--color-span-mail)]/10 text-[var(--color-span-mail)]',
    notification:
        'bg-[var(--color-span-notification)]/10 text-[var(--color-span-notification)]',
    exception:
        'bg-[var(--color-span-exception)]/10 text-[var(--color-span-exception)]',
};
const OTHER_BADGE_COLOR =
    'bg-[var(--color-span-other)]/10 text-[var(--color-span-other)]';
export function spanTypeBadgeColor(type: string | null): string {
    return (type && SPAN_TYPE_BADGE_COLORS[type]) ?? OTHER_BADGE_COLOR;
}
