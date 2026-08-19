import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

/**
 * Minimal previous/next pager for a Laravel paginator response. No page
 * number list yet -- fine for an MVP, revisit once volumes make jumping to
 * an arbitrary page worthwhile.
 */
export function PagerLinks({
    prevPageUrl,
    nextPageUrl,
}: {
    prevPageUrl: string | null;
    nextPageUrl: string | null;
}) {
    if (!prevPageUrl && !nextPageUrl) {
        return null;
    }

    return (
        <div className="flex items-center justify-end gap-2">
            <Button variant="outline" size="sm" disabled={!prevPageUrl} asChild>
                {prevPageUrl ? (
                    <Link href={prevPageUrl} preserveScroll>
                        Previous
                    </Link>
                ) : (
                    <span>Previous</span>
                )}
            </Button>

            <Button variant="outline" size="sm" disabled={!nextPageUrl} asChild>
                {nextPageUrl ? (
                    <Link href={nextPageUrl} preserveScroll>
                        Next
                    </Link>
                ) : (
                    <span>Next</span>
                )}
            </Button>
        </div>
    );
}
