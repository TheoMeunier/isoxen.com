const SPAN_TYPE_COLORS: Record<string, string> = {
    request: 'bg-[var(--color-tone-neutral)]',
    query: 'bg-[var(--color-method-post)]',
    cache: 'bg-[var(--color-method-put)]',
    job: 'bg-[var(--color-tone-warning)]',
    outgoing_request: 'bg-[var(--color-method-delete)]',
    mail: 'bg-[var(--color-span-mail)]',
    notification: 'bg-[var(--color-span-notification)]',
    exception: 'bg-[var(--color-span-exception)]',
};
const OTHER_COLOR = 'bg-[var(--color-span-other)]';
export function spanTypeColor(type: string | null): string {
    return (type && SPAN_TYPE_COLORS[type]) ?? OTHER_COLOR;
}
