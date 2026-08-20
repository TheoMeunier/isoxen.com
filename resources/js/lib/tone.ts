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
    neutral: 'bg-[#2a78d6] dark:bg-[#3987e5]',
    warning: 'bg-[#eda100] dark:bg-[#c98500]',
    critical: 'bg-[#c33c3c] dark:bg-[#e66767]',
};

/**
 * Text stays on text tokens per the dataviz rule ("colour follows the
 * entity, never sits directly on a number") -- only `critical` gets a
 * colour hint, matching how errors are already called out elsewhere
 * (SlowEndpointsTable's error count, StatusBadge).
 */
export const TONE_TEXT: Record<Tone, string> = {
    neutral: 'text-foreground',
    warning: 'text-[#c98500] dark:text-[#eda100]',
    critical: 'text-[#c33c3c] dark:text-[#e66767]',
};
