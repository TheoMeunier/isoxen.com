import { useMemo } from 'react';
import { spanTypeColor } from '@/lib/span-colors';
import { spanDetail } from '@/lib/span-detail';
import type { TraceSpan } from '@/types/observability';

const SPAN_KINDS: Record<number, string> = {
    0: 'Unspecified',
    1: 'Internal',
    2: 'Server',
    3: 'Client',
    4: 'Producer',
    5: 'Consumer',
};

type Node = TraceSpan & { children: Node[]; depth: number };

/**
 * Nests spans under their parent and flattens back into a list, depth-first
 * and in the order they started -- the shape a waterfall reads top to
 * bottom.
 *
 * A span whose `parent_span_id` isn't any other span in this trace (either
 * it's genuinely the root, or its parent fell outside a sampled/truncated
 * trace) is treated as top-level rather than dropped: every span the query
 * fetched must show up somewhere.
 */
function buildRows(spans: TraceSpan[]): Node[] {
    const byId = new Map<string, Node>();

    for (const span of spans) {
        if (span.span_id) {
            byId.set(span.span_id, { ...span, children: [], depth: 0 });
        }
    }

    const roots: Node[] = [];

    for (const span of spans) {
        if (!span.span_id) {
            continue;
        }

        const node = byId.get(span.span_id)!;
        const parent = span.parent_span_id
            ? byId.get(span.parent_span_id)
            : undefined;

        if (parent) {
            parent.children.push(node);
        } else {
            roots.push(node);
        }
    }

    const rows: Node[] = [];

    function visit(node: Node, depth: number): void {
        node.depth = depth;
        rows.push(node);
        node.children.forEach((child) => visit(child, depth + 1));
    }

    roots.forEach((root) => visit(root, 0));

    return rows;
}

function formatDuration(nanos: number | null): string {
    if (nanos === null) {
        return '—';
    }

    const ms = nanos / 1_000_000;

    return ms < 1 ? `${ms.toFixed(2)} ms` : `${ms.toFixed(1)} ms`;
}

export function TraceWaterfall({
    spans,
    selectedSpanId,
    onSelectSpan,
}: {
    spans: TraceSpan[];
    selectedSpanId: string | null;
    onSelectSpan: (spanId: string) => void;
}) {
    const rows = useMemo(() => buildRows(spans), [spans]);

    const traceStart = Math.min(
        ...spans.map((span) => new Date(span.time).getTime()),
    );
    const traceEnd = Math.max(
        ...spans.map((span) => new Date(span.end_time ?? span.time).getTime()),
    );
    // A trace that's all zero-duration spans (or one span) would otherwise
    // divide by zero; every bar just renders at the far left instead.
    const totalMs = Math.max(traceEnd - traceStart, 1);

    return (
        <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <div className="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 text-xs text-muted-foreground dark:border-sidebar-border">
                <span>0 ms</span>
                <span>{formatDuration(totalMs * 1_000_000)} total</span>
            </div>

            <div className="divide-y divide-sidebar-border/40 dark:divide-sidebar-border/60">
                {rows.map((row) => {
                    const startMs = row.time
                        ? new Date(row.time).getTime() - traceStart
                        : 0;
                    const durationMs = row.duration_nanos
                        ? row.duration_nanos / 1_000_000
                        : 0;

                    const leftPercent = Math.min(
                        (startMs / totalMs) * 100,
                        100,
                    );
                    const widthPercent = Math.max(
                        (durationMs / totalMs) * 100,
                        0.5,
                    );

                    const isError = row.status_code === 2;
                    const isSelected = row.span_id === selectedSpanId;
                    const detail = spanDetail(row);

                    return (
                        <button
                            key={row.span_id ?? `${row.name}-${row.time}`}
                            type="button"
                            onClick={() =>
                                row.span_id && onSelectSpan(row.span_id)
                            }
                            className={`grid w-full grid-cols-[minmax(0,1fr)_minmax(0,1fr)] items-center gap-4 px-4 py-2 text-left text-sm transition-colors hover:bg-muted/40 ${
                                isSelected ? 'bg-muted/60' : ''
                            }`}
                        >
                            <div
                                className="flex min-w-0 items-center gap-2"
                                style={{
                                    paddingLeft: `${row.depth * 16}px`,
                                }}
                            >
                                <span
                                    aria-hidden
                                    className={`size-2 shrink-0 rounded-full ${spanTypeColor(row.type)}`}
                                />
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {row.name ?? '—'}
                                        {row.kind !== null && (
                                            <span className="ml-2 font-normal text-muted-foreground">
                                                {SPAN_KINDS[row.kind] ??
                                                    row.kind}
                                            </span>
                                        )}
                                    </p>
                                    {detail && (
                                        <p className="truncate text-xs text-muted-foreground">
                                            {detail}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex min-w-0 items-center gap-2">
                                <div className="relative h-4 flex-1">
                                    <div
                                        className={`absolute top-1/2 h-2 -translate-y-1/2 rounded-sm ${spanTypeColor(row.type)} ${
                                            isError
                                                ? 'ring-2 ring-[#c33c3c] dark:ring-[#e66767]'
                                                : ''
                                        }`}
                                        style={{
                                            left: `${leftPercent}%`,
                                            width: `${widthPercent}%`,
                                        }}
                                    />
                                </div>
                                <span className="w-16 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                                    {formatDuration(row.duration_nanos)}
                                </span>
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
