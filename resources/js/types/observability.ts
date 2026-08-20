export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

export type SpanEntry = {
    time: string;
    name: string | null;
    type: string | null;
    kind: number | null;
    duration_nanos: number | null;
    status_code: number | null;
    trace_id: string | null;
    span_id: string | null;
};

export type MetricEntry = {
    time: string;
    name: string | null;
    type: string | null;
    unit: string | null;
    value: number | null;
};

export type LogEntry = {
    time: string;
    severity_text: string | null;
    severity_number: number | null;
    body: string | null;
    trace_id: string | null;
    span_id: string | null;
};

/**
 * A single span within one trace, as returned by `TraceSpansQuery` for the
 * waterfall on a trace's detail page.
 *
 * Unlike `SpanEntry` (one category's table, most recent first), this
 * carries `parent_span_id` and `attributes` -- what the waterfall needs to
 * nest spans under their parent and to show what a span actually did.
 */
export type TraceSpan = {
    span_id: string | null;
    parent_span_id: string | null;
    name: string | null;
    type: string | null;
    kind: number | null;
    time: string;
    end_time: string | null;
    duration_nanos: number | null;
    status_code: number | null;
    status_message: string | null;
    attributes: Record<string, unknown> | null;
};

/** A log line correlated to a trace, for the trace detail page. */
export type TraceLog = {
    time: string;
    span_id: string | null;
    severity_text: string | null;
    severity_number: number | null;
    body: string | null;
};

/**
 * The headline numbers above a category's table. `errors` is null for
 * categories where the notion doesn't apply (metrics), and `slowest_ms`
 * only exists for spans, which are the only entries with a duration.
 */
export type CategorySummary = {
    total: number;
    errors: number | null;
    slowest_ms: number | null;
    avg_ms: number | null;
    /** The window these figures cover, matching the chart beside them. */
    hours: number;
};

/**
 * One hour of the activity chart.
 *
 * `at` is an ISO-8601 instant, deliberately not a preformatted label: the
 * hour a reader should see is the hour in *their* timezone, which only the
 * browser knows.
 */
export type TimelinePoint = {
    at: string;
    count: number;
};

/**
 * One hour of the duration chart. `avg_ms` is `null` (not `0`) for an hour
 * with no spans -- a quiet hour has no duration to report, which is a
 * different fact than "requests happened and were instant".
 */
export type DurationPoint = {
    at: string;
    avg_ms: number | null;
};

/**
 * One segment of a category's headline breakdown -- an HTTP status class
 * for Requests, a severity for Logs, or a generic success/failure split for
 * everything else. `tone` picks the pill's colour; it's always paired with
 * `label` so state is never colour alone.
 */
export type StatusSegment = {
    label: string;
    value: number;
    tone: 'neutral' | 'warning' | 'critical';
};

/**
 * The same breakdown as `StatusSegment`, per hour -- what the volume
 * chart's stacked bars are made of. Every segment here carries the same
 * `label`/`tone` as its counterpart in `statusBreakdown`, by construction
 * (see ShowProjectController::statusTimeline()).
 */
export type StatusTimelinePoint = {
    at: string;
    segments: StatusSegment[];
};

/**
 * One endpoint's aggregate latency over the summary window, as returned by
 * `SlowEndpointsQuery` for the Requests category's "slowest endpoints"
 * panel. `p50`/`p95`/`p99` are separate fields rather than a nested object
 * so the table can sort by any one of them without extra unpacking.
 */
export type EndpointStat = {
    name: string;
    total: number;
    errors: number;
    avg_ms: number;
    p50_ms: number;
    p95_ms: number;
    p99_ms: number;
};

/**
 * The project currently being viewed, shared from
 * `HandleInertiaRequests::projectContext()` so the app sidebar can swap its
 * navigation. Absent on every page that isn't scoped to a project.
 */
export type CurrentProject = {
    id: number;
    name: string;
    slug: string;
};

/**
 * Category slugs, mirrored from
 * `App\Watch\Ingestion\Support\ObservabilityCategories` on the backend.
 * Keep the two lists in sync by hand until this is worth codegen'ing.
 */
export type ObservabilityCategory =
    | 'requests'
    | 'jobs'
    | 'commands'
    | 'scheduled-tasks'
    | 'exceptions'
    | 'queries'
    | 'notifications'
    | 'mail'
    | 'cache'
    | 'outgoing-requests'
    | 'metrics'
    | 'users'
    | 'logs';
