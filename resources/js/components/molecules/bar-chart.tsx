import { useState } from 'react';
import { TONE_DOT } from '@/lib/tone';
import type { Tone } from '@/lib/tone';

export type BarSegment = { label: string; value: number; tone: Tone };
export type BarPoint = { at: string; segments: BarSegment[] };

const PLOT_HEIGHT = 120;

function niceCeiling(max: number): number {
    if (max <= 5) {
        return 5;
    }

    const magnitude = 10 ** Math.floor(Math.log10(max));
    const step = magnitude / 2;

    return Math.ceil(max / step) * step;
}

function fullDateLabel(at: string): string {
    return new Date(at).toLocaleString(undefined, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * A stacked bar per hour, coloured by status -- the volume chart on a
 * category page. Each bar's segments carry the same tone as the pills
 * above it (see lib/tone.ts), so the chart is readable without a separate
 * legend: the pill row already is one.
 *
 * A single-segment point (the Duration panel, which has no notion of
 * status) renders as a plain one-colour bar -- same component, no branch
 * needed for that case.
 */
export function BarChart({
    points,
    valueFormat = (value: number) => value.toLocaleString(),
}: {
    points: BarPoint[];
    valueFormat?: (value: number) => string;
}) {
    const [hovered, setHovered] = useState<number | null>(null);

    const totals = points.map((point) =>
        point.segments.reduce((sum, segment) => sum + segment.value, 0),
    );
    const max = Math.max(...totals, 0);
    const ceiling = niceCeiling(max);
    const hasData = max > 0;

    return (
        <div className="mt-4">
            <div
                className="relative flex items-end gap-[2px]"
                style={{ height: PLOT_HEIGHT }}
            >
                {[0, 0.5, 1].map((fraction) => (
                    <div
                        key={fraction}
                        className="pointer-events-none absolute right-0 left-0 border-t border-sidebar-border/50 dark:border-sidebar-border"
                        style={{ top: fraction * PLOT_HEIGHT }}
                    />
                ))}

                {points.map((point, index) => {
                    const total = totals[index];
                    const barHeight =
                        ceiling === 0
                            ? 0
                            : Math.max(
                                  (total / ceiling) * PLOT_HEIGHT,
                                  total > 0 ? 2 : 0,
                              );
                    const visibleSegments = point.segments.filter(
                        (segment) => segment.value > 0,
                    );

                    return (
                        <div
                            key={point.at}
                            className="group relative flex h-full flex-1 items-end"
                            onMouseEnter={() => setHovered(index)}
                            onMouseLeave={() => setHovered(null)}
                        >
                            <div
                                className={`flex w-full max-w-6 flex-col-reverse transition-opacity ${
                                    hovered !== null && hovered !== index
                                        ? 'opacity-60'
                                        : ''
                                }`}
                                style={{ height: barHeight }}
                            >
                                {visibleSegments.map((segment, i) => (
                                    <div
                                        key={segment.label}
                                        className={`w-full ${TONE_DOT[segment.tone]} ${
                                            i === visibleSegments.length - 1
                                                ? 'rounded-t-sm'
                                                : ''
                                        }`}
                                        style={{
                                            height: `${(segment.value / total) * 100}%`,
                                        }}
                                    />
                                ))}
                            </div>

                            {hovered === index && total > 0 && (
                                <div
                                    className={`pointer-events-none absolute bottom-full z-10 mb-2 w-max space-y-1 rounded-md border border-sidebar-border/70 bg-background px-2 py-1.5 text-xs shadow-sm dark:border-sidebar-border ${
                                        index < 3
                                            ? 'left-0'
                                            : index > points.length - 4
                                              ? 'right-0'
                                              : 'left-1/2 -translate-x-1/2'
                                    }`}
                                >
                                    <p className="font-medium">
                                        {fullDateLabel(point.at)}
                                    </p>
                                    {visibleSegments.map((segment) => (
                                        <p
                                            key={segment.label}
                                            className="flex items-center gap-1.5"
                                        >
                                            <span
                                                aria-hidden
                                                className={`size-1.5 rounded-full ${TONE_DOT[segment.tone]}`}
                                            />
                                            <span className="text-muted-foreground">
                                                {segment.label}
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {valueFormat(segment.value)}
                                            </span>
                                        </p>
                                    ))}
                                </div>
                            )}
                        </div>
                    );
                })}

                {!hasData && (
                    <div className="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">
                        —
                    </div>
                )}
            </div>

            <div className="mt-2 flex justify-between text-xs text-muted-foreground tabular-nums">
                <span>{points[0] && fullDateLabel(points[0].at)}</span>
                <span>
                    {points.length > 0 &&
                        fullDateLabel(points[points.length - 1].at)}
                </span>
            </div>
        </div>
    );
}
