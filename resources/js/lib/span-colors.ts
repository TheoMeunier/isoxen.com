/**
 * Categorical color for a span's `type`, used by the trace waterfall so
 * it's obvious at a glance which category a bar belongs to (a request, its
 * queries, a cache lookup, ...).
 *
 * Slots and order come from the workspace's validated eight-hue categorical
 * palette (fixed order, never cycled -- see the dataviz skill). Slot 1
 * (blue) is already the app's single-series color in the activity chart, so
 * it's kept for `request` here too. A span type with no assigned slot folds
 * into the neutral "other" color rather than generating a new hue -- there
 * are more span types (13+) than safe categorical slots (8), and most
 * traces only ever show a handful of them at once.
 *
 * `exception` deliberately takes the red slot: it's the one category that
 * *is* about something going wrong, so the categorical color and the
 * reader's instinct for "red = bad" point the same way. Every other span
 * can still be individually marked errored via its own status code -- a
 * status ring rather than a recolor, so it never collides with a span's
 * category color.
 *
 * Returns Tailwind classes referencing the shared colour tokens defined in
 * app.css (not raw hex) so light/dark theming stays in one place -- see the
 * comment above those tokens for how each light/dark pair was chosen.
 */
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

/**
 * Tailwind background classes for a span's category. Used both for the
 * waterfall bar itself and, at the same color, the small dot next to a
 * span's name in the label column.
 */
export function spanTypeColor(type: string | null): string {
    return (type && SPAN_TYPE_COLORS[type]) ?? OTHER_COLOR;
}
