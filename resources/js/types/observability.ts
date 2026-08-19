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
