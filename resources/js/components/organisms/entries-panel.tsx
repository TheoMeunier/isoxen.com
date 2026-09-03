import { Search } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { PagerLinks } from '@/components/molecules/pager-links';
import { EmptyState } from '@/components/organisms/empty-state';
import { Input } from '@/components/ui/input';
import type { Paginated } from '@/types/observability';

/**
 * The generic "list of entries" body: a header with the total and an on-page search box, an
 * empty state for no data at all vs. no search matches, and the paginator -- everything a
 * category page needs around whichever table it renders. `total` is the category's headline
 * count (its {@see CategorySummary}), not necessarily `entries.data.length` on this page.
 */
export function EntriesPanel<Entry>({
    label,
    total,
    entries,
    searchableText,
    renderTable,
}: {
    label: string;
    total: number;
    entries: Paginated<Entry>;
    searchableText: (entry: Entry) => string;
    renderTable: (entries: Entry[]) => ReactNode;
}) {
    const [search, setSearch] = useState('');

    const searchedEntries = entries.data.filter((entry) => {
        if (!search.trim()) {
            return true;
        }

        return searchableText(entry)
            .toLowerCase()
            .includes(search.trim().toLowerCase());
    });

    const lowerLabel = label.toLowerCase();

    return (
        <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <div className="flex items-center justify-between gap-4 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <p className="font-medium">
                    {total.toLocaleString()} {label}
                </p>

                {entries.data.length > 0 && (
                    <div className="relative w-full max-w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder={`Search ${lowerLabel} on this page`}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="h-8 pl-8 text-sm"
                        />
                    </div>
                )}
            </div>

            <div className="p-4">
                {entries.data.length === 0 ? (
                    <EmptyState
                        message={`Once your app starts sending ${lowerLabel} with this token, they'll show up here.`}
                    />
                ) : searchedEntries.length === 0 ? (
                    <EmptyState
                        message={`No ${lowerLabel} on this page match "${search}".`}
                    />
                ) : (
                    <div className="space-y-4">
                        {renderTable(searchedEntries)}

                        <PagerLinks meta={entries} itemLabel={lowerLabel} />
                    </div>
                )}
            </div>
        </div>
    );
}
