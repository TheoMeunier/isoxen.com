const STATUS_CODES: Record<number, string> = {
    0: 'Unset',
    1: 'Ok',
    2: 'Error',
};

/**
 * Status is a reserved colour role, and it always ships with its label —
 * never colour alone, which would be invisible to a colourblind reader.
 *
 * Shared between the category table (projects/show) and a trace's detail
 * page, which both render the same OTEL status codes.
 */
export function StatusBadge({ code }: { code: number | null }) {
    if (code === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    const label = STATUS_CODES[code] ?? String(code);

    if (code !== 2) {
        return <span className="text-muted-foreground">{label}</span>;
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-[#c33c3c]/10 px-2 py-0.5 font-medium text-[#c33c3c] dark:bg-[#e66767]/15 dark:text-[#e66767]">
            <span aria-hidden className="size-1.5 rounded-full bg-current" />
            {label}
        </span>
    );
}
