export type Tone = 'neutral' | 'warning' | 'critical';
export const TONE_DOT: Record<Tone, string> = {
    neutral: 'bg-[var(--color-tone-neutral)]',
    warning: 'bg-[var(--color-tone-warning)]',
    critical: 'bg-[var(--color-tone-critical)]',
};
export const TONE_TEXT: Record<Tone, string> = {
    neutral: 'text-foreground',
    warning: 'text-[var(--color-tone-warning-contrast)]',
    critical: 'text-[var(--color-tone-critical)]',
};
/**
 * The same palette as {@see TONE_DOT}, as raw CSS colour values instead of
 * Tailwind classes -- for SVG `stroke`/`fill` attributes, which don't take
 * utility classes the way a `div`'s background does (LineChart).
 */
export const TONE_COLOR: Record<Tone, string> = {
    neutral: 'var(--color-tone-neutral)',
    warning: 'var(--color-tone-warning)',
    critical: 'var(--color-tone-critical)',
};
