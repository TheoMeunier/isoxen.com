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

/**
 * A byte count in whichever unit reads best -- "852 B", "4.33 kB",
 * "2.86 MB" -- for request/response body sizes (see RouteDetailContent's
 * per-request Info panel), the same scale Nightwatch's own request page
 * uses.
 */
export function formatBytes(bytes: number): string {
    if (bytes < 1000) {
        return `${bytes} B`;
    }

    const units = ['kB', 'MB', 'GB'];
    let value = bytes / 1000;
    let unitIndex = 0;

    while (value >= 1000 && unitIndex < units.length - 1) {
        value /= 1000;
        unitIndex++;
    }

    return `${value.toFixed(2)} ${units[unitIndex]}`;
}
