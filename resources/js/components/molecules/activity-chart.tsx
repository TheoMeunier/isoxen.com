import { useState } from 'react';
import type { TimelinePoint } from '@/types/observability';

/**
 * Hourly activity over the last 24 hours, as a column chart.
 *
 * One series, so there's no legend: the title above already names what is
 * plotted, and a single-swatch legend would just restate it. Colour comes
 * from the validated categorical slot 1, stepped for each surface.
 */
const SERIES = 'bg-[#2a78d6] dark:bg-[#3987e5]';

const PLOT_HEIGHT = 144;

/**
 * The hour as the reader's own clock shows it.
 *
 * The backend sends an absolute instant rather than a formatted hour,
 * precisely so this conversion happens where the timezone is known.
 */
function hourLabel(at: string): string {
    return new Date(at).toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function niceCeiling(max: number): number {
    if (max <= 5) {
        return 5;
    }

    // Round the axis up to a clean number so its ticks read as 20/40, not
    // 17/34.
    const magnitude = 10 ** Math.floor(Math.log10(max));
    const step = magnitude / 2;

    return Math.ceil(max / step) * step;
}

export function ActivityChart({ points }: { points: TimelinePoint[] }) {
    const [hovered, setHovered] = useState<number | null>(null);

    const max = Math.max(...points.map((point) => point.count), 0);
    const ceiling = niceCeiling(max);
    const peak = points.findIndex((point) => point.count === max && max > 0);

    return (
        <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p className="text-sm font-medium">Last 24 hours</p>
            <p className="mt-0.5 text-xs text-muted-foreground">
                Hourly volume. Hover a column for its exact count.
            </p>

            <div className="mt-6 flex gap-3">
                {/* Axis ticks carry the values that aren't directly
                    labelled, so only the peak needs a label on the mark. */}
                <div
                    className="relative w-10 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                    style={{ height: PLOT_HEIGHT }}
                >
                    <span className="absolute top-0 right-0 -translate-y-1/2">
                        {ceiling.toLocaleString()}
                    </span>
                    <span className="absolute top-1/2 right-0 -translate-y-1/2">
                        {(ceiling / 2).toLocaleString()}
                    </span>
                    <span className="absolute right-0 bottom-0 translate-y-1/2">
                        0
                    </span>
                </div>

                <div className="relative flex-1">
                    {/* Hairline gridlines, one step off the surface so they
                        stay behind the data. */}
                    {[0, 0.5, 1].map((fraction) => (
                        <div
                            key={fraction}
                            className="absolute right-0 left-0 border-t border-sidebar-border/50 dark:border-sidebar-border"
                            style={{ top: fraction * PLOT_HEIGHT }}
                        />
                    ))}

                    <div
                        className="relative flex items-end gap-[2px]"
                        style={{ height: PLOT_HEIGHT }}
                    >
                        {points.map((point, index) => {
                            const height =
                                ceiling === 0
                                    ? 0
                                    : (point.count / ceiling) * PLOT_HEIGHT;

                            return (
                                <div
                                    key={point.at}
                                    className="group relative flex h-full flex-1 items-end"
                                    onMouseEnter={() => setHovered(index)}
                                    onMouseLeave={() => setHovered(null)}
                                >
                                    {/* The extreme is the one value worth
                                        labelling, and it rides its own
                                        column: put it on the axis instead
                                        and it points at the wrong hour. */}
                                    {index === peak && hovered !== index && (
                                        <span
                                            className="pointer-events-none absolute bottom-full left-1/2 mb-1 w-max -translate-x-1/2 text-xs font-medium text-muted-foreground tabular-nums"
                                            style={{ marginBottom: 4 }}
                                        >
                                            {max.toLocaleString()}
                                        </span>
                                    )}

                                    {/* Full-height hit area: the columns are
                                        thin, and hovering a 3px bar is not a
                                        usable target. */}
                                    <div
                                        className={`w-full max-w-6 rounded-t ${SERIES} ${
                                            point.count === 0
                                                ? 'opacity-0'
                                                : 'opacity-100'
                                        } ${
                                            hovered !== null &&
                                            hovered !== index
                                                ? 'opacity-60'
                                                : ''
                                        } transition-opacity`}
                                        style={{
                                            height: Math.max(height, 2),
                                        }}
                                    />

                                    {hovered === index && (
                                        <div
                                            className={`pointer-events-none absolute bottom-full z-10 mb-2 w-max rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-xs shadow-sm dark:border-sidebar-border ${
                                                // Clamp at the edges so the
                                                // tooltip never overflows the
                                                // plot.
                                                index < 3
                                                    ? 'left-0'
                                                    : index >
                                                        points.length - 4
                                                      ? 'right-0'
                                                      : 'left-1/2 -translate-x-1/2'
                                            }`}
                                        >
                                            <span className="font-medium tabular-nums">
                                                {point.count.toLocaleString()}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {' '}
                                                at {hourLabel(point.at)}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {/* Only the two ends are labelled; 24 tick labels would
                        collide and go unread. The peak is labelled on its
                        own column, and every other hour is one hover away. */}
                    <div className="mt-2 flex justify-between text-xs text-muted-foreground tabular-nums">
                        <span>{points[0] && hourLabel(points[0].at)}</span>
                        <span>
                            {points.length > 0 &&
                                hourLabel(points[points.length - 1].at)}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}
