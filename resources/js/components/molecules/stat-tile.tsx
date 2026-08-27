import type { LucideIcon } from 'lucide-react';
import { TONE_TEXT } from '@/lib/tone';
import type { Tone } from '@/lib/tone';

/**
 * One of the KPI tiles across the top of the dashboard -- a label and a
 * headline number, with an optional coloured detail line underneath (e.g.
 * how many of the errors were server errors). Deliberately chart-less: the
 * activity panel right below already carries the trend, this row is just
 * the "how much" at a glance.
 *
 * The icon is optional and purely presentational -- pass one of the same
 * lucide icons the sidebar and category pages already use for that concept
 * (see CATEGORY_ICONS in nav-project.tsx) so a tile's meaning is reinforced
 * rather than a new icon vocabulary being invented for the dashboard alone.
 */
export function StatTile({
    label,
    value,
    detail,
    tone = 'neutral',
    icon: Icon,
}: {
    label: string;
    value: string;
    detail?: string;
    tone?: Tone;
    icon?: LucideIcon;
}) {
    return (
        <div className="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="flex items-center gap-1.5">
                {Icon && (
                    <Icon
                        aria-hidden
                        className="size-3.5 text-muted-foreground"
                    />
                )}
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
            </div>
            <p className="mt-1.5 text-3xl font-semibold tabular-nums">
                {value}
            </p>
            {detail && (
                <p className={`mt-1 text-xs font-medium ${TONE_TEXT[tone]}`}>
                    {detail}
                </p>
            )}
        </div>
    );
}
