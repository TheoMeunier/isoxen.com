export type SlowestEndpoint = {
    name: string;
    avgMs: number;
    total: number;
};

/**
 * One hour's 1XX-3XX/4XX/5XX split -- the same shape RequestStatusBreakdownQuery::executeTimeline()
 * returns on a project's own Requests page, reused here so the dashboard's charts show *when* errors
 * happened instead of a flat, colorless request count.
 */
export type StatusTimelinePoint = {
    at: string;
    success: number;
    client_error: number;
    server_error: number;
};

export type DashboardOverview = {
    projectsCount: number;
    projectsOnline: number;
    requests24h: number;
    clientErrors24h: number;
    serverErrors24h: number;
    onlineUsers: number;
};

/**
 * A project's own most recent exception -- shown on its dashboard card, not folded into a fleet-wide
 * list, so a noisy project elsewhere can't crowd a quieter project's exception out of view.
 */
export type LatestException = {
    time: string;
    name: string | null;
    statusMessage: string | null;
    traceId: string | null;
};

export type DashboardProjectStat = {
    projectId: number;
    requests: number;
    clientErrors: number;
    serverErrors: number;
    onlineUsers: number;
    statusTimeline: StatusTimelinePoint[];
    slowestEndpoint: SlowestEndpoint | null;
    latestException: LatestException | null;
    /**
     * Whether telemetry has arrived from this project in the last few minutes -- not a real uptime
     * probe (isoxen never pings the app itself), just "is data still coming in". See
     * ProjectUptimeQuery's docblock for the tradeoff.
     */
    online: boolean;
    lastSeenAt: string | null;
};

/**
 * @deprecated No longer sent by the dashboard -- each project's own latest exception now lives on its
 * card as `DashboardProjectStat.latestException` instead of a separate fleet-wide list. Kept only
 * because `recent-exceptions-list.tsx` (now unused on the dashboard) still references this shape.
 */
export type RecentException = {
    projectId: number;
    projectName: string;
    time: string;
    name: string | null;
    statusMessage: string | null;
    traceId: string | null;
};
