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
export type EndpointStat = {
    name: string;
    total: number;
    errors: number;
    avg_ms: number;
    p50_ms: number;
    p95_ms: number;
    p99_ms: number;
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
