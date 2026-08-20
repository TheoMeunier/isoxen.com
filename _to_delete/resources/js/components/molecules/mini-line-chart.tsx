import { useState } from 'react';

type Point = { at: string; value: number | null };

const PLOT_HEIGHT = 120;
const VIEW_WIDTH = 600;

/**
 * A minimal time-series line, shared by the volume and duration panels on a
 * project's category page.
 *
 * Nulls break the line rather than dropping to zero -- an hour with no
 * spans has no duration to plot, which is a different fact than "duration
 * was zero" (see DurationTimelineQuery). Volume never has this problem
 * since every hour gets a real count, even 0.
 *
 * Follows the same mark spec as the existing bar chart (activity-chart.tsx):
 * a 2px line, a hairline top/bottom grid, hover-only labels, and only the
 * two ends of the axis labelled -- 24+ tick labels would collide.
 */
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

export function MiniLineChart({
    points,
    lineColor = 'stroke-[#2a78d6] dark:stroke-[#3987e5]',
    fillColor = 'fill-[#2a78d6]/10 dark:fill-[#3987e5]/10',
    dotColor = 'bg-[#2a78d6] dark:bg-[#3987e5]',
    valueFormat = (value: number) => value.toLocaleString(),
}: {
    points: Point[];
    lineColor?: string;
    fillColor?: string;
    dotColor?: string;
    valueFormat?: (value: number) => string;
}) {
    const [hovered, setHovered] = useState<number | null>(null);

    const numericValues = points
        .map((point) => point.value)
        .filter((value): value is number => value !== null);
    const max = Math.max(...numericValues, 0);
    const ceiling = niceCeiling(max);
    const hasData = numericValues.length > 0 && max > 0;

    const coords = points.map((point, index) => ({
        xPercent: points.length > 1 ? (index / (points.length - 1)) * 100 : 0,
        y:
            point.value === null || ceiling === 0
                ? null
                : PLOT_HEIGHT - (point.value / ceiling) * PLOT_HEIGHT,
        value: point.value,
        at: point.at,
    }));

    // Break the path at every null so a gap in the data reads as a gap on
    // the chart, not a line diving to zero.
    const segments: { x: number; y: number }[][] = [];
    let current: { x: number; y: number }[] = [];

    for (const coord of coords) {
        if (coord.y === null) {
            if (current.length > 0) {
                segments.push(current);
            }

            current = [];
        } else {
            current.push({
                x: (coord.xPercent / 100) * VIEW_WIDTH,
                y: coord.y,
            });
        }
    }

    if (current.length > 0) {
        segments.push(current);
    }

    const linePath = (pts: { x: number; y: number }[]) =>
        pts
            .map(
                (p, i) =>
                    `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(2)} ${p.y.toFixed(2)}`,
            )
            .join(' ');

    const areaPath = (pts: { x: number; y: number }[]) =>
        `${linePath(pts)} L ${pts[pts.length - 1].x.toFixed(2)} ${PLOT_HEIGHT} L ${pts[0].x.toFixed(2)} ${PLOT_HEIGHT} Z`;

    return (
        <div className="mt-4">
            <div className="relative" style={{ height: PLOT_HEIGHT }}>
                <div className="absolute inset-x-0 top-0 border-t border-sidebar-border/50 dark:border-sidebar-border" />
                <div className="absolute inset-x-0 bottom-0 border-t border-sidebar-border/50 dark:border-sidebar-border" />

                {hasData ? (
                    <>
                        <svg
                            viewBox={`0 0 ${VIEW_WIDTH} ${PLOT_HEIGHT}`}
                            preserveAspectRatio="none"
                            className="h-full w-full overflow-visible"
                        >
                            {segments.map((segment, i) => (
                                <g key={i}>
                                    {segment.length > 1 && (
                                        <path
                                            d={areaPath(segment)}
                                            className={fillColor}
                                            stroke="none"
                                        />
                                    )}
                                    <path
                                        d={linePath(segment)}
                                        className={lineColor}
                                        fill="none"
                                        strokeWidth={2}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        vectorEffect="non-scaling-stroke"
                                    />
                                </g>
                            ))}
                        </svg>

                        {hovered !== null && coords[hovered]?.y !== null && (
                            <div
                                aria-hidden
                                className={`pointer-events-none absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-background ${dotColor}`}
                                style={{
                                    left: `${coords[hovered].xPercent}%`,
                                    top: `${(coords[hovered].y! / PLOT_HEIGHT) * 100}%`,
                                }}
                            />
                        )}

                        <div className="absolute inset-0 flex">
                            {coords.map((coord, index) => (
                                <div
                                    key={coord.at}
                                    className="relative flex-1"
                                    onMouseEnter={() => setHovered(index)}
                                    onMouseLeave={() => setHovered(null)}
                                >
                                    {hovered === index &&
                                        coord.value !== null && (
                                            <div
                                                className={`pointer-events-none absolute bottom-full z-10 mb-2 w-max rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-xs shadow-sm dark:border-sidebar-border ${
                                                    index < 3
                                                        ? 'left-0'
                                                        : index >
                                                            points.length - 4
                                                          ? 'right-0'
                                                          : 'left-1/2 -translate-x-1/2'
                                                }`}
                                            >
                                                <span className="font-medium tabular-nums">
                                                    {valueFormat(coord.value)}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    at{' '}
                                                    {new Date(
                                                        coord.at,
                                                    ).toLocaleTimeString(
                                                        undefined,
                                                        {
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        },
                                                    )}
                                                </span>
                                            </div>
                                        )}
                                </div>
                            ))}
                        </div>
                    </>
                ) : (
                    <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
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
