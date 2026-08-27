import { useId, useMemo, useState } from 'react';
import { formatDateTimeLabel } from '@/lib/datetime';
import { TONE_COLOR } from '@/lib/tone';
import type { Tone } from '@/lib/tone';
import { niceCeiling } from './bar-chart';
import type { BarPoint } from './bar-chart';

const PLOT_HEIGHT = 120;
const PLOT_WIDTH = 100;

type Coord = { x: number; y: number };

/**
 * Catmull-Rom-to-Bezier smoothing, so the line reads as a trend rather than
 * a jagged connect-the-dots -- the same curve style the Duration panel is
 * meant to evoke. Falls back to a single point/straight segment when there
 * isn't enough data to curve.
 */
function smoothPath(points: Coord[]): string {
    if (points.length === 0) {
        return '';
    }

    if (points.length === 1) {
        return `M ${points[0].x} ${points[0].y}`;
    }

    let path = `M ${points[0].x} ${points[0].y}`;

    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i - 1] ?? points[i];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[i + 2] ?? p2;

        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;

        path += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2.x} ${p2.y}`;
    }

    return path;
}

/**
 * A smoothed line per series (coloured by tone, same convention as
 * BarChart's segments) with a fading fill underneath -- the Duration
 * panel's chart, where a continuous trend reads better than discrete bars.
 * Takes the same BarPoint[] shape as BarChart so a category page can choose
 * either chart for the same data (see StatChartPanel's `chart` prop).
 */
export function LineChart({
    points,
    valueFormat = (value: number) => value.toLocaleString(),
}: {
    points: BarPoint[];
    valueFormat?: (value: number) => string;
}) {
    const gradientId = useId();
    const [hovered, setHovered] = useState<number | null>(null);

    const series = useMemo(() => {
        const byLabel = new Map<string, Tone>();

        points.forEach((point) =>
            point.segments.forEach((segment) => {
                if (!byLabel.has(segment.label)) {
                    byLabel.set(segment.label, segment.tone);
                }
            }),
        );

        return Array.from(byLabel.entries()).map(([label, tone]) => ({
            label,
            tone,
        }));
    }, [points]);

    const max = Math.max(
        0,
        ...points.flatMap((point) => point.segments.map((s) => s.value)),
    );
    const ceiling = niceCeiling(max);
    const hasData = max > 0;

    function valueFor(point: BarPoint, label: string): number {
        return (
            point.segments.find((segment) => segment.label === label)?.value ??
            0
        );
    }

    function coordsFor(label: string): Coord[] {
        return points.map((point, index) => ({
            x:
                points.length > 1
                    ? (index / (points.length - 1)) * PLOT_WIDTH
                    : PLOT_WIDTH / 2,
            y:
                ceiling === 0
                    ? PLOT_HEIGHT
                    : PLOT_HEIGHT -
                      (valueFor(point, label) / ceiling) * PLOT_HEIGHT,
        }));
    }

    return (
        <div className="mt-4">
            <div className="relative" style={{ height: PLOT_HEIGHT }}>
                {[0, 0.5, 1].map((fraction) => (
                    <div
                        key={fraction}
                        className="border-sidebar-border/50 dark:border-sidebar-border pointer-events-none absolute left-0 right-0 border-t"
                        style={{ top: fraction * PLOT_HEIGHT }}
                    />
                ))}

                {hasData && (
                    <svg
                        viewBox={`0 0 ${PLOT_WIDTH} ${PLOT_HEIGHT}`}
                        preserveAspectRatio="none"
                        className="pointer-events-none absolute inset-0 h-full w-full"
                    >
                        {series.map(({ label, tone }) => {
                            const coords = coordsFor(label);
                            const linePath = smoothPath(coords);
                            const areaPath = `${linePath} L ${coords[coords.length - 1].x} ${PLOT_HEIGHT} L ${coords[0].x} ${PLOT_HEIGHT} Z`;

                            return (
                                <g key={label}>
                                    <defs>
                                        <linearGradient
                                            id={`${gradientId}-${label}`}
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="0%"
                                                stopColor={TONE_COLOR[tone]}
                                                stopOpacity={0.25}
                                            />
                                            <stop
                                                offset="100%"
                                                stopColor={TONE_COLOR[tone]}
                                                stopOpacity={0}
                                            />
                                        </linearGradient>
                                    </defs>
                                    <path
                                        d={areaPath}
                                        fill={`url(#${gradientId}-${label})`}
                                        stroke="none"
                                    />
                                    <path
                                        d={linePath}
                                        fill="none"
                                        stroke={TONE_COLOR[tone]}
                                        strokeWidth={1.5}
                                        strokeLinejoin="round"
                                        strokeLinecap="round"
                                        vectorEffect="non-scaling-stroke"
                                    />
                                </g>
                            );
                        })}

                        {hovered !== null && (
                            <line
                                x1={
                                    points.length > 1
                                        ? (hovered / (points.length - 1)) *
                                          PLOT_WIDTH
                                        : PLOT_WIDTH / 2
                                }
                                x2={
                                    points.length > 1
                                        ? (hovered / (points.length - 1)) *
                                          PLOT_WIDTH
                                        : PLOT_WIDTH / 2
                                }
                                y1={0}
                                y2={PLOT_HEIGHT}
                                className="stroke-sidebar-border/70 dark:stroke-sidebar-border"
                                strokeWidth={1}
                                vectorEffect="non-scaling-stroke"
                            />
                        )}
                    </svg>
                )}

                {/* Plain HTML dots, not SVG circles -- the chart's viewBox is
                    stretched non-uniformly (preserveAspectRatio="none") so a
                    circle drawn in that coordinate space renders as a
                    lopsided, oversized ellipse. Positioning a fixed-size dot
                    by percentage keeps it round regardless of the chart's
                    aspect ratio. */}
                {hovered !== null &&
                    series.map(({ label, tone }) => {
                        const point = coordsFor(label)[hovered];

                        return (
                            <span
                                key={label}
                                aria-hidden
                                className="ring-background pointer-events-none absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2"
                                style={{
                                    left: `${(point.x / PLOT_WIDTH) * 100}%`,
                                    top: `${(point.y / PLOT_HEIGHT) * 100}%`,
                                    backgroundColor: TONE_COLOR[tone],
                                }}
                            />
                        );
                    })}

                <div className="absolute inset-0 flex">
                    {points.map((point, index) => (
                        <div
                            key={point.at}
                            className="h-full flex-1"
                            onMouseEnter={() => setHovered(index)}
                            onMouseLeave={() => setHovered(null)}
                        />
                    ))}
                </div>

                {hovered !== null && points[hovered] && (
                    <div
                        className={`border-sidebar-border/70 bg-background dark:border-sidebar-border pointer-events-none absolute bottom-full z-10 mb-2 w-max space-y-1 rounded-md border px-2 py-1.5 text-xs shadow-sm ${
                            hovered < 3
                                ? 'left-0'
                                : hovered > points.length - 4
                                  ? 'right-0'
                                  : 'left-1/2 -translate-x-1/2'
                        }`}
                    >
                        <p className="font-medium">
                            {formatDateTimeLabel(points[hovered].at)}
                        </p>
                        {points[hovered].segments.map((segment) => (
                            <p
                                key={segment.label}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    aria-hidden
                                    className="size-1.5 rounded-full"
                                    style={{
                                        backgroundColor:
                                            TONE_COLOR[segment.tone],
                                    }}
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

                {!hasData && (
                    <div className="text-muted-foreground absolute inset-0 flex items-center justify-center text-sm">
                        —
                    </div>
                )}
            </div>

            <div className="text-muted-foreground mt-2 flex justify-between text-xs tabular-nums">
                <span>{points[0] && formatDateTimeLabel(points[0].at)}</span>
                <span>
                    {points.length > 0 &&
                        formatDateTimeLabel(points[points.length - 1].at)}
                </span>
            </div>
        </div>
    );
}
