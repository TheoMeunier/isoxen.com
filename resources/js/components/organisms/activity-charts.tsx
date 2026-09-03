import { StatChartPanel } from '@/components/organisms/stat-chart-panel';
import type {
    CategorySummary,
    DurationPoint,
    StatusSegment,
    StatusTimelinePoint,
    TimelinePoint,
} from '@/types/observability';

/**
 * The headline chart row every category page opens with: volume/status on the left, and (unless
 * the category has no notion of duration, like Logs and Metrics) a duration chart on the right.
 */
export function ActivityCharts({
    label,
    summary,
    statusBreakdown,
    statusTimeline,
    timeline,
    durationTimeline,
    hasDuration = true,
}: {
    label: string;
    summary: CategorySummary;
    statusBreakdown: StatusSegment[];
    statusTimeline: StatusTimelinePoint[];
    timeline: TimelinePoint[];
    durationTimeline: DurationPoint[];
    hasDuration?: boolean;
}) {
    return (
        <div className="flex flex-col gap-4 lg:flex-row">
            <StatChartPanel
                title={label.toUpperCase()}
                headline={summary.total.toLocaleString()}
                pills={statusBreakdown.map((segment) => ({
                    label: segment.label,
                    value: segment.value.toLocaleString(),
                    tone: segment.tone,
                }))}
                points={
                    statusTimeline.length > 0
                        ? statusTimeline
                        : timeline.map((point) => ({
                              at: point.at,
                              segments: [
                                  {
                                      label,
                                      value: point.count,
                                      tone: 'neutral' as const,
                                  },
                              ],
                          }))
                }
            />

            {hasDuration && (
                <StatChartPanel
                    chart="line"
                    title="Duration"
                    headline={
                        summary.avg_ms !== null ? `${summary.avg_ms} ms` : '—'
                    }
                    pills={[
                        {
                            label: 'Avg',
                            value:
                                summary.avg_ms !== null
                                    ? `${summary.avg_ms} ms`
                                    : '—',
                            tone: 'neutral',
                        },
                        {
                            label: 'P95',
                            value:
                                summary.slowest_ms !== null
                                    ? `${summary.slowest_ms} ms`
                                    : '—',
                            tone: 'warning',
                        },
                    ]}
                    points={durationTimeline.map((point) => ({
                        at: point.at,
                        segments: [
                            {
                                label: 'Avg',
                                value: point.avg_ms ?? 0,
                                tone: 'neutral' as const,
                            },
                            {
                                label: 'P95',
                                value: point.p95_ms ?? 0,
                                tone: 'warning' as const,
                            },
                        ],
                    }))}
                    valueFormat={(value) => `${value.toFixed(1)} ms`}
                />
            )}
        </div>
    );
}
