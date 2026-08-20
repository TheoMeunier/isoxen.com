import { PeriodSelector } from '@/components/molecules/period-selector';

/**
 * The per-category page header -- title and period picker -- shared by
 * every tab in the sidebar (Requests, Jobs, Commands, ...) rather than
 * rebuilt per tab.
 */
export function CategoryHeader({ label }: { label: string }) {
    return (
        <div className="flex items-center justify-between">
            <h2 className="text-xl font-semibold">{label}</h2>
            <PeriodSelector />
        </div>
    );
}
