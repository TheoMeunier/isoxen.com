const STATUS_CODES: Record<number, string> = {
    0: 'Unset',
    1: 'Ok',
    2: 'Error',
};
export function StatusBadge({ code }: { code: number | null }) {
    if (code === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    const label = STATUS_CODES[code] ?? String(code);

    if (code !== 2) {
        return <span className="text-muted-foreground">{label}</span>;
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-tone-critical)]/10 px-2 py-0.5 font-medium text-[var(--color-tone-critical)] dark:bg-[var(--color-tone-critical)]/15">
            <span aria-hidden className="size-1.5 rounded-full bg-current" />
            {label}
        </span>
    );
}
