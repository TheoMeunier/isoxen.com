import type { CategorySummary } from '@/types/observability';

/**
 * The headline numbers for a category.
 *
 * These are single current values, which is what a stat tile is for — a
 * one-bar chart of "total" would carry the same number with more ink and
 * less legibility.
 */
function Tile({
    label,
    value,
    hint,
    tone = 'neutral',
}: {
    label: string;
    value: string;
    hint?: string;
    tone?: 'neutral' | 'critical';
}) {
    return (
        <div className="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p
                className={`mt-1 text-3xl font-semibold tabular-nums ${
                    // Status colour is reserved for state and always ships
                    // with its label, never as colour alone.
                    tone === 'critical' && value !== '0'
                        ? 'text-[#c33c3c] dark:text-[#e66767]'
                        : 'text-foreground'
                }`}
            >
                {value}
            </p>
            {hint && (
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}

export function StatTiles({
    summary,
    unit,
}: {
    summary: CategorySummary;
    unit: string;
}) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row">
            <Tile
                label="Total"
                value={summary.total.toLocaleString()}
                hint={`${unit} in the last ${summary.hours}h`}
            />

            {summary.errors !== null && (
                <Tile
                    label="Errors"
                    value={summary.errors.toLocaleString()}
                    hint={
                        summary.total > 0
                            ? `${((summary.errors / summary.total) * 100).toFixed(1)}% of total`
                            : undefined
                    }
                    tone="critical"
                />
            )}

            {summary.slowest_ms !== null && (
                <Tile
                    label="p95 duration"
                    value={`${summary.slowest_ms.toLocaleString()} ms`}
                    hint="95% are faster than this"
                />
            )}
        </div>
    );
}
