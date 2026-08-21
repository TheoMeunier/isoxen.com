const DATE_TIME_FORMAT = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    timeZoneName: 'short',
});
export function formatTime(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : DATE_TIME_FORMAT.format(date);
}
export function formatDurationMs(ms: number): string {
    return ms < 1 ? `${ms.toFixed(2)} ms` : `${ms.toFixed(1)} ms`;
}
export function formatDuration(nanos: number | null): string {
    if (nanos === null) {
        return '—';
    }

    return formatDurationMs(nanos / 1000000);
}
