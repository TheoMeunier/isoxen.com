/**
 * The per-category page title, shared by every tab in the sidebar
 * (Requests, Jobs, Commands, ...) rather than rebuilt per tab.
 *
 * Used to also render the period picker beside the title; that now lives in
 * the app header instead (see AppSidebarHeader / PeriodSelector), since a
 * time window applies to the whole page, not just this one heading.
 */
export function CategoryHeader({ label }: { label: string }) {
    return <h2 className="text-xl font-semibold">{label}</h2>;
}
