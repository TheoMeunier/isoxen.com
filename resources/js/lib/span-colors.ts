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
 * Returns Tailwind classes (not raw hex) to match how the rest of this
 * project's charts theme for light/dark -- see activity-chart.tsx.
 */
const SPAN_TYPE_COLORS: Record<string, string> = {
    request: 'bg-[#2a78d6] dark:bg-[#3987e5]',
    query: 'bg-[#eb6834] dark:bg-[#d95926]',
    cache: 'bg-[#1baf7a] dark:bg-[#199e70]',
    job: 'bg-[#eda100] dark:bg-[#c98500]',
    outgoing_request: 'bg-[#e87ba4] dark:bg-[#d55181]',
    mail: 'bg-[#008300]',
    notification: 'bg-[#4a3aa7] dark:bg-[#9085e9]',
    exception: 'bg-[#e34948] dark:bg-[#e66767]',
};

const OTHER_COLOR = 'bg-[#8a8a86] dark:bg-[#9c9b93]';

/**
 * Tailwind background classes for a span's category. Used both for the
 * waterfall bar itself and, at the same color, the small dot next to a
 * span's name in the label column.
 */
export function spanTypeColor(type: string | null): string {
    return (type && SPAN_TYPE_COLORS[type]) ?? OTHER_COLOR;
}
