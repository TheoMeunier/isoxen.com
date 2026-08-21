import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import type { PaginatorLinkItem } from '@/types/observability';

/**
 * The subset of a Laravel paginator response this component needs --
 * structurally compatible with `Paginated<T>` (see types/observability.ts),
 * so any of that type's paginated props can be passed straight through
 * without picking fields out by hand.
 */
export type PaginationMeta = {
    prev_page_url: string | null;
    next_page_url: string | null;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginatorLinkItem[];
};

/**
 * A category page's pager: "Showing 26-50 of 1,204 requests" next to a real
 * shadcn `Pagination` control -- numbered pages, an ellipsis where the
 * window skips ahead, and Previous/Next -- rather than two bare buttons.
 *
 * The page-number window (which numbers show, where the "..." goes) isn't
 * computed here: `meta.links` is Laravel's own windowed link list
 * (`LengthAwarePaginator::toArray()`), already correct for however many
 * pages this category has. The first and last entries of that array are
 * Laravel's own "Previous"/"Next" links (with `&laquo;`/`&raquo;` in their
 * label) -- skipped here in favour of `PaginationPrevious`/`PaginationNext`,
 * which supply their own icon and text instead of rendering that label.
 *
 * `itemLabel` names what's being counted (e.g. "requests", "logs") so the
 * count reads naturally across every category page that reuses this
 * component -- pass the same label already shown in that page's heading.
 *
 * Renders nothing when there's nothing to page through (`total === 0`);
 * the caller's own empty state already covers that case.
 */
export function PagerLinks({
    meta,
    itemLabel = 'items',
}: {
    meta: PaginationMeta;
    itemLabel?: string;
}) {
    const { prev_page_url, next_page_url, last_page, from, to, total, links } =
        meta;

    if (total === 0) {
        return null;
    }

    // Drop Laravel's leading "« Previous" / trailing "Next »" entries --
    // PaginationPrevious/PaginationNext below render those two directions
    // themselves, from prev_page_url/next_page_url.
    const pageLinks = links.slice(1, -1);

    return (
        <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
            <p className="text-sm text-muted-foreground">
                Showing{' '}
                <span className="font-medium text-foreground">
                    {from}-{to}
                </span>{' '}
                of{' '}
                <span className="font-medium text-foreground">
                    {total.toLocaleString()}
                </span>{' '}
                {itemLabel}
            </p>

            {last_page > 1 && (
                <Pagination className="w-auto">
                    <PaginationContent>
                        <PaginationItem>
                            <PaginationPrevious href={prev_page_url} />
                        </PaginationItem>

                        {pageLinks.map((link, i) =>
                            link.url === null && link.label === '...' ? (
                                <PaginationItem key={`ellipsis-${i}`}>
                                    <PaginationEllipsis />
                                </PaginationItem>
                            ) : (
                                <PaginationItem key={link.label}>
                                    <PaginationLink
                                        href={link.url}
                                        isActive={link.active}
                                    >
                                        {link.label}
                                    </PaginationLink>
                                </PaginationItem>
                            ),
                        )}

                        <PaginationItem>
                            <PaginationNext href={next_page_url} />
                        </PaginationItem>
                    </PaginationContent>
                </Pagination>
            )}
        </div>
    );
}
