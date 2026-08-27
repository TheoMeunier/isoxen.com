/**
 * The HTTP status code an exception maps to, when the client could tell
 * (an HttpExceptionInterface, or a failed outgoing HTTP call -- see
 * IsoxenServiceProvider::resolveHttpStatusCode()). Coloured by status
 * class the same way RoutesTable colours 4XX/5XX counts, so a glance at
 * the badge tells you whether this was a client- or server-side failure.
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
              : 'bg-muted text-muted-foreground';

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-xs font-semibold ${classes}`}
        >
            {status}
        </span>
    );
}
