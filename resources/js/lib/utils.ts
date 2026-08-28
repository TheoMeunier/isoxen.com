import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * A count at "k" scale rather than the full digit string -- QueriesTable's
 * Calls column deals in tens of thousands per statement, and "60.9k" scans
 * far faster in a dense table than "60,904".
 */
export function formatCompactNumber(value: number): string {
    if (value < 1000) {
        return value.toLocaleString();
    }

    return `${(value / 1000).toFixed(1)}k`;
}
