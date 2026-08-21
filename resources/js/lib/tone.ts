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
