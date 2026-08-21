/**
 * A short, human-readable line describing what a span actually did, pulled
 * from its OTEL attributes.
 *
 * Span `name` alone is already descriptive for some categories (a request's
 * name is "GET /orders/{id}", set by the client's own instrumentation), so
 * this only covers the categories where the name is generic (a query's name
 * is just "SELECT") and the interesting part lives in an attribute instead.
 *
 * Attribute keys are either OTEL semantic conventions (`db.query.text`,
 * `messaging.message.job_name`, ...) or isoxen's own client attributes
 * (`console.command`, `cache.key`, ...) -- see the instrumentation classes
 * under packages/isoxen-client/src/Instrumentation for what each category
 * actually sets.
 */
const DETAIL_ATTRIBUTES: Partial<Record<string, string[]>> = {
    query: ['db.query.text', 'db.operation.name'],
    command: ['console.command'],
    job: ['messaging.message.job_name'],
    scheduled_task: [
        'isoxen.scheduled_task.description',
        'isoxen.scheduled_task.expression',
    ],
    cache: ['cache.key'],
    mail: ['mail.subject'],
    notification: ['notification.class'],
    redis: ['db.query.text'],
    exception: ['exception.message', 'exception.type'],
    // Name/email only appear if the monitored application opted in (see
    // UserInstrumentation) -- most spans will only have the id, which is
    // what's checked last.
    user: ['user.email', 'user.name', 'enduser.id', 'user.id'],
};

export function spanDetail(entry: {
    type: string | null;
    attributes: Record<string, unknown> | null;
}): string | null {
    if (!entry.attributes || !entry.type) {
        return null;
    }

    const keys = DETAIL_ATTRIBUTES[entry.type] ?? [];

    for (const key of keys) {
        const value = entry.attributes[key];

        if (typeof value === 'string' && value.length > 0) {
            return value;
        }
    }

    return null;
}
