import { Calendar } from 'lucide-react';

const PRESETS = ['1H', '24H', '7D', '14D', '30D'] as const;

// Every window this page's queries actually run against right now (see
// ShowProjectController) is fixed at 24 hours. The other presets render so
// the layout matches the reference design, but stay disabled rather than
// silently doing nothing when clicked -- a button that looks live but
// changes nothing is worse than one that's visibly not wired up yet.
const ACTIVE_PRESET = '24H';

/**
 * The period picker on a category page's header. Visual-only for now (see
 * the note above); real range filtering is a follow-up once this layout is
 * in place across every category.
 */
export function PeriodSelector() {
    return (
        <div className="flex items-center gap-1 rounded-lg border border-sidebar-border/70 p-1 dark:border-sidebar-border">
            {PRESETS.map((preset) => (
                <button
                    key={preset}
                    type="button"
                    disabled={preset !== ACTIVE_PRESET}
                    title={
                        preset === ACTIVE_PRESET
                            ? undefined
                            : 'Filtrage par période à venir'
                    }
                    className={`rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${
                        preset === ACTIVE_PRESET
                            ? 'bg-foreground text-background'
                            : 'cursor-not-allowed text-muted-foreground/50'
                    }`}
                >
                    {preset}
                </button>
            ))}

            <button
                type="button"
                disabled
                title="Plage personnalisée à venir"
                className="cursor-not-allowed rounded-md p-1.5 text-muted-foreground/50"
            >
                <Calendar className="size-3.5" />
            </button>
        </div>
    );
}
