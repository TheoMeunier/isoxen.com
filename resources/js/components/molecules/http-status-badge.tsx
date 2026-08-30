/**
 * The HTTP status code a request or exception maps to (an
 * HttpExceptionInterface, or a failed outgoing HTTP call -- see
 * IsoxenServiceProvider::resolveHttpStatusCode()). Coloured by status
 * class -- green for 1XX-3XX, orange for 4XX, red for 5XX -- the same
 * way RoutesTable colours its 4XX/5XX counts, so a glance at the badge
 * tells you whether a code was informational/successful or a client- or
 * server-side failure without reading the number itself.
 */
export function HttpStatusBadge({ status }: { status: number | null }) {
    if (status === null) {
        return null;
    }

    const classes =
        status >= 500
            ? 'bg-[var(--color-tone-critical)]/10 text-[var(--color-tone-critical)] dark:bg-[var(--color-tone-critical)]/15'
            : status >= 400
              ? 'bg-[var(--color-tone-warning)]/10 text-[var(--color-tone-warning-contrast)] dark:bg-[var(--color-tone-warning)]/15'
              : 'bg-[var(--color-tone-success)]/10 text-[var(--color-tone-success)] dark:bg-[var(--color-tone-success)]/15';

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-xs font-semibold ${classes}`}
        >
            {status}
        </span>
    );
}
