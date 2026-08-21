export type Tone = 'neutral' | 'warning' | 'critical';

/**
 * The single colour vocabulary for anything status-coloured on a category
 * page -- the pills above a chart and the bar segments inside it. Shared
 * from one place so a pill and its matching bar segment can never drift
 * apart: same tone always means same colour.
 *
 * `neutral` reuses the app's single-series blue (see the old activity
 * chart) rather than a plain grey -- the healthy majority of a stacked bar
 * is still the most prominent thing on the chart, so it gets a real colour,
 * not a dimmed one. `warning`/`critical` are the validated categorical
 * yellow/red slots already used elsewhere in this app (SlowEndpointsTable,
 * StatusBadge).
 */
export const TONE_DOT: Record<Tone, string> = {
    neutral: 'bg-[var(--color-tone-neutral)]',
    warning: 'bg-[var(--color-tone-warning)]',
    critical: 'bg-[var(--color-tone-critical)]',
};

/**
 * Text stays on text tokens per the dataviz rule ("colour follows the
 * entity, never sits directly on a number") -- only `critical` gets a
 * colour hint, matching how errors are already called out elsewhere
 * (SlowEndpointsTable's error count, StatusBadge).
 */
export const TONE_TEXT: Record<Tone, string> = {
    neutral: 'text-foreground',
    warning: 'text-[var(--color-tone-warning-contrast)]',
    critical: 'text-[var(--color-tone-critical)]',
};
