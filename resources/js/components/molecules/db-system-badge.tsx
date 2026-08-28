import { dbSystemLabel } from '@/lib/db-system';

/**
 * A colour-coded pill for a query's `db.system` (see QueriesTable) --
 * mirrors {@see MethodBadge}'s pattern for HTTP verbs, so the two grouped
 * tables read the same way even though they colour a different attribute.
 */
export function DbSystemBadge({ system }: { system: string | null }) {
    const badge = dbSystemLabel(system);

    if (!badge) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ${badge.className}`}
        >
            {badge.label}
        </span>
    );
}
