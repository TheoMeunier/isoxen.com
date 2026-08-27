import { BarChart } from '@/components/molecules/bar-chart';
import type { BarPoint } from '@/components/molecules/bar-chart';
import { LineChart } from '@/components/molecules/line-chart';
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
 * broken down by status), which reads better as bars grouping several
 * statuses per hour, and the Duration metric (avg over time, against a
 * static p95 pill), which reads better as a continuous line -- `chart`
 * picks between the two without either caller reshaping its data.
 */
export function StatChartPanel({
    title,
    headline,
    pills,
    points,
    valueFormat,
    chart = 'bar',
}: {
    title: string;
    headline: string;
    pills: PanelPill[];
    points: BarPoint[];
    valueFormat?: (value: number) => string;
    chart?: 'bar' | 'line';
}) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border flex-1 rounded-xl border p-4">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
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
                                className="flex items-center gap-1.5 whitespace-nowrap text-xs"
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

            {chart === 'line' ? (
                <LineChart points={points} valueFormat={valueFormat} />
            ) : (
                <BarChart points={points} valueFormat={valueFormat} />
            )}
        </div>
    );
}
