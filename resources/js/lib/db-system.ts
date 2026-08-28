/**
 * Display label and colour for a span's `db.system` attribute (OTel's
 * semantic-convention name for the database driver) -- unrecognised or
 * missing values fall back to a neutral pill rather than being hidden, so
 * a driver this app doesn't know about yet still shows up as *something*
 * on the row (see QueriesTable).
 */
const DB_SYSTEMS: Record<string, { label: string; className: string }> = {
    mysql: {
        label: 'MySQL',
        className:
            'bg-[var(--color-tone-warning)]/10 text-[var(--color-tone-warning-contrast)] dark:bg-[var(--color-tone-warning)]/15',
    },
    mariadb: {
        label: 'MariaDB',
        className:
            'bg-[var(--color-method-post)]/10 text-[var(--color-method-post-contrast)] dark:bg-[var(--color-method-post)]/15',
    },
    postgresql: {
        label: 'PostgreSQL',
        className:
            'bg-[var(--color-method-put)]/10 text-[var(--color-method-put-contrast)] dark:bg-[var(--color-method-put)]/15',
    },
    pgsql: {
        label: 'PostgreSQL',
        className:
            'bg-[var(--color-method-put)]/10 text-[var(--color-method-put-contrast)] dark:bg-[var(--color-method-put)]/15',
    },
    sqlite: {
        label: 'SQLite',
        className: 'bg-muted text-muted-foreground',
    },
    mssql: {
        label: 'SQL Server',
        className:
            'bg-[var(--color-method-delete)]/10 text-[var(--color-method-delete-contrast)] dark:bg-[var(--color-method-delete)]/15',
    },
    sqlsrv: {
        label: 'SQL Server',
        className:
            'bg-[var(--color-method-delete)]/10 text-[var(--color-method-delete-contrast)] dark:bg-[var(--color-method-delete)]/15',
    },
    oracle: {
        label: 'Oracle',
        className:
            'bg-[var(--color-tone-critical)]/10 text-[var(--color-tone-critical)] dark:bg-[var(--color-tone-critical)]/15',
    },
    mongodb: {
        label: 'MongoDB',
        className: 'bg-[var(--color-span-mail)]/10 text-[var(--color-span-mail)] dark:bg-[var(--color-span-mail)]/15',
    },
    cockroachdb: {
        label: 'CockroachDB',
        className:
            'bg-[var(--color-method-put)]/10 text-[var(--color-method-put-contrast)] dark:bg-[var(--color-method-put)]/15',
    },
};

export function dbSystemLabel(system: string | null): { label: string; className: string } | null {
    if (!system) {
        return null;
    }

    return (
        DB_SYSTEMS[system.toLowerCase()] ?? {
            label: system,
            className: 'bg-muted text-muted-foreground',
        }
    );
}
