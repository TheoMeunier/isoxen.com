import { Link } from '@inertiajs/react';
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    MoreHorizontalIcon,
} from 'lucide-react';
import * as React from 'react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * shadcn/ui's Pagination primitives (new-york style), adapted for this app:
 * `PaginationLink` navigates through Inertia's `<Link>` for a client-side
 * page change instead of a full document navigation, and renders as a
 * disabled `<span>` rather than an anchor when there's nowhere to go
 * (`href` is `null` -- no page-behind-the-first-page, no page-past-the-
 * last-page) rather than a `<a>` with a fake href.
 */

function Pagination({ className, ...props }: React.ComponentProps<'nav'>) {
    return (
        <nav
            role="navigation"
            aria-label="pagination"
            data-slot="pagination"
            className={cn('flex w-full justify-center', className)}
            {...props}
        />
    );
}

function PaginationContent({
    className,
    ...props
}: React.ComponentProps<'ul'>) {
    return (
        <ul
            data-slot="pagination-content"
            className={cn('flex flex-row items-center gap-1', className)}
            {...props}
        />
    );
}

function PaginationItem({ ...props }: React.ComponentProps<'li'>) {
    return <li data-slot="pagination-item" {...props} />;
}

type PaginationLinkProps = {
    isActive?: boolean;
    href: string | null;
    size?: 'default' | 'icon';
    // Inertia's `Link` spreads `React.AllHTMLAttributes`, which includes a
    // `size?: number` (the `<input>`/`<select>` HTML attribute) -- omitted
    // here too, alongside `href`, so it doesn't collide with the `size`
    // variant prop above and collapse the merged type to `never`.
} & Omit<React.ComponentProps<typeof Link>, 'href' | 'size'>;

function PaginationLink({
    className,
    isActive,
    size = 'icon',
    href,
    children,
    ...props
}: PaginationLinkProps) {
    const classes = cn(
        buttonVariants({
            variant: isActive ? 'outline' : 'ghost',
            size,
        }),
        !href && 'pointer-events-none opacity-50',
        className,
    );

    // No `href` means this direction genuinely goes nowhere (already on the
    // first/last page) -- render an inert span rather than a link with a
    // fake destination.
    if (!href) {
        return (
            <span
                aria-disabled="true"
                data-slot="pagination-link"
                className={classes}
            >
                {children}
            </span>
        );
    }

    return (
        <Link
            href={href}
            preserveScroll
            aria-current={isActive ? 'page' : undefined}
            data-slot="pagination-link"
            data-active={isActive}
            className={classes}
            {...props}
        >
            {children}
        </Link>
    );
}

function PaginationPrevious({
    className,
    ...props
}: React.ComponentProps<typeof PaginationLink>) {
    return (
        <PaginationLink
            aria-label="Go to previous page"
            size="default"
            className={cn('gap-1 px-2.5 sm:pl-2.5', className)}
            {...props}
        >
            <ChevronLeftIcon />
            <span className="hidden sm:block">Previous</span>
        </PaginationLink>
    );
}

function PaginationNext({
    className,
    ...props
}: React.ComponentProps<typeof PaginationLink>) {
    return (
        <PaginationLink
            aria-label="Go to next page"
            size="default"
            className={cn('gap-1 px-2.5 sm:pr-2.5', className)}
            {...props}
        >
            <span className="hidden sm:block">Next</span>
            <ChevronRightIcon />
        </PaginationLink>
    );
}

function PaginationEllipsis({
    className,
    ...props
}: React.ComponentProps<'span'>) {
    return (
        <span
            aria-hidden
            data-slot="pagination-ellipsis"
            className={cn('flex size-9 items-center justify-center', className)}
            {...props}
        >
            <MoreHorizontalIcon className="size-4" />
            <span className="sr-only">More pages</span>
        </span>
    );
}

export {
    Pagination,
    PaginationContent,
    PaginationLink,
    PaginationItem,
    PaginationPrevious,
    PaginationNext,
    PaginationEllipsis,
};
