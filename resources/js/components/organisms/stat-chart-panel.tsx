import { BarChart } from '@/components/molecules/bar-chart';
import type { BarPoint } from '@/components/molecules/bar-chart';
import { TONE_DOT, TONE_TEXT } from '@/lib/tone';
import type { Tone } from '@/lib/tone';

export type PanelPill = {
    label: string;
    value: string;
    tone: Tone;
};

/**
 * One of the two cards at the top of a category page -- a headline number,
 * up to a few pills breaking it down, and a chart of the same figure over
 * time. Used for both the primary metric (Requests/Jobs/Commands/... count,
 * broken down by status) and the Duration metric (avg, broken down against
 * p95), which is what makes it worth sharing rather than writing two
 * bespoke panels.
 */
export function StatChartPanel({
    title,
    headline,
    pills,
    points,
    valueFormat,
}: {
    title: string;
    headline: string;
    pills: PanelPill[];
    points: BarPoint[];
    valueFormat?: (value: number) => string;
}) {
    return (
        <div className="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {title}
                    </p>
                    <p className="mt-1 text-3xl font-semibold tabular-nums">
                        {headline}
                    </p>
                </div>

                {pills.length > 0 && (
                    <div className="flex flex-wrap justify-end gap-x-4 gap-y-1">
                        {pills.map((pill) => (
                            <div
                                key={pill.label}
                                className="flex items-center gap-1.5 text-xs whitespace-nowrap"
                            >
                                <span
                                    aria-hidden
                                    className={`size-1.5 rounded-full ${TONE_DOT[pill.tone]}`}
                                />
                                <span className="text-muted-foreground">
                                    {pill.label}
                                </span>
                                <span
                                    className={`font-medium tabular-nums ${TONE_TEXT[pill.tone]}`}
                                >
                                    {pill.value}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <BarChart points={points} valueFormat={valueFormat} />
        </div>
    );
}
