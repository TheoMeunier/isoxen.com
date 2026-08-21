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
export type PaginationMeta = {
    prev_page_url: string | null;
    next_page_url: string | null;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginatorLinkItem[];
};
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
