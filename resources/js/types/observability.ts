export type PaginatorLinkItem = {
    url: string | null;
    label: string;
    active: boolean;
};
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginatorLinkItem[];
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
    detail?: string | null;
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
export type TraceLog = {
    time: string;
    span_id: string | null;
    severity_text: string | null;
    severity_number: number | null;
    body: string | null;
};
export type CategorySummary = {
    total: number;
    errors: number | null;
    slowest_ms: number | null;
    avg_ms: number | null;
    hours: number;
};
export type TimelinePoint = {
    at: string;
    count: number;
};
export type DurationPoint = {
    at: string;
    avg_ms: number | null;
    p95_ms: number | null;
};
export type StatusSegment = {
    label: string;
    value: number;
    tone: 'neutral' | 'warning' | 'critical';
};
export type StatusTimelinePoint = {
    at: string;
    segments: StatusSegment[];
};
/**
 * One row of the Requests page's Routes table -- a path grouped across every
 * method it was seen with (see RouteStatsQuery), with its status-class
 * breakdown and duration percentiles over the window.
 */
export type RouteStat = {
    methods: string;
    path: string;
    total: number;
    success: number;
    client_error: number;
    server_error: number;
    avg_ms: number;
    p95_ms: number;
};
/**
 * One row of the Queries page's grouped table -- every SQL statement seen
 * in the window collapsed into one row by its exact text (see
 * QueryStatsQuery), so a busy or slow query surfaces on its own instead of
 * being buried in a stream of thousands of individual query spans.
 */
export type QueryStat = {
    query: string;
    fingerprint: string;
    system: string | null;
    calls: number;
    total_ms: number;
    avg_ms: number;
    p95_ms: number;
};
export type QueryTimelinePoint = {
    at: string;
    calls: number;
    avg_ms: number | null;
    p95_ms: number | null;
};
export type QueryOccurrence = {
    time: string;
    duration_ms: number;
    trace_id: string | null;
    span_id: string | null;
    caller: string | null;
    caller_file: string | null;
    caller_line: number | null;
    called_from: string | null;
    called_from_type: string | null;
};
/**
 * One SQL statement's full detail, addressed by fingerprint (see
 * QueryOccurrencesQuery) -- shared by the Queries table's drawer and its
 * dedicated detail page, since both show the same picture at two different
 * sizes.
 */
export type QueryDetail = {
    query: string;
    fingerprint: string;
    system: string | null;
    namespace: string | null;
    host: string | null;
    port: number | null;
    calls: number;
    total_ms: number;
    avg_ms: number;
    p95_ms: number;
    first_seen: string | null;
    last_seen: string | null;
    timeline: QueryTimelinePoint[];
    occurrences: QueryOccurrence[];
};
/**
 * One row of the Exceptions page's grouped list -- every occurrence of "the
 * same exception" (see ExceptionFingerprint) collapsed into one row, ranked
 * by last seen so a recurring failure doesn't get buried under whichever
 * exception fired most recently (see ExceptionGroupsQuery).
 */
export type ExceptionGroup = {
    fingerprint: string;
    type: string;
    message: string | null;
    file: string | null;
    line: number | null;
    http_status: number | null;
    occurrences: number;
    first_seen: string;
    last_seen: string;
    trace_id: string | null;
};
/**
 * The exception detail page's payload -- one fingerprint's full picture
 * across the lookback window (see ExceptionOccurrencesQuery).
 */
export type ExceptionDetail = {
    type: string;
    message: string | null;
    file: string | null;
    line: number | null;
    http_status: number | null;
    occurrences_24h: number;
    occurrences_7d: number;
    occurrences_30d: number;
    first_seen: string;
    last_seen: string;
    first_trace_id: string | null;
    last_trace_id: string | null;
    stacktrace: string | null;
};
export type OnlineUser = {
    id: string;
    name: string | null;
    email: string | null;
    since: string;
};
export type CurrentProject = {
    id: number;
    name: string;
    slug: string;
};
/**
 * One tile on the project's Overview tab: a category's 24h volume and error
 * count (see ProjectHealthOverviewQuery). `slug` excludes 'users', which
 * isn't a volume/error category.
 */
export type CategoryHealth = {
    slug: Exclude<ObservabilityCategory, 'users'>;
    label: string;
    group: 'activity' | 'monitoring';
    total: number;
    errors: number;
};
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
/**
 * One entry of ObservabilityCategories::all() (app/Watch/Ingestion/Support/
 * ObservabilityCategories.php), shared to every project-bound page via
 * HandleInertiaRequests so the sidebar can build its nav from the backend's
 * list instead of keeping its own hardcoded copy.
 */
export type ObservabilityCategoryDefinition = {
    group: 'activity' | 'monitoring';
    label: string;
    source: 'span' | 'logs' | 'metrics';
    type: string | null;
    enabled: boolean;
};
export type ObservabilityCategories = Record<
    ObservabilityCategory,
    ObservabilityCategoryDefinition
>;
