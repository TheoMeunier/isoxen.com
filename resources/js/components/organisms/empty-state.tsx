/**
 * The placeholder shown in an entries table when a category has no data yet
 * (nothing sent), or when the on-page search filters everything out.
 */
export function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
            <p className="text-sm font-medium">No data received yet</p>
            <p className="max-w-sm text-sm text-muted-foreground">{message}</p>
        </div>
    );
}
