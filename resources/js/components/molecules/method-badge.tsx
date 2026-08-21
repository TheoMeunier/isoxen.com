const HTTP_METHODS = [
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    'HEAD',
    'OPTIONS',
] as const;

type KnownMethod = (typeof HTTP_METHODS)[number];

// Only these five get their own colour -- HEAD/OPTIONS are rare enough on a
// typical app that giving them a dedicated categorical slot isn't worth it
// (see the dataviz skill: a straggler folds into a neutral "Other" rather
// than stretching the palette). They still render as a badge, just muted.
type ColouredMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

/**
 * Request span names follow `"{METHOD} {route}"` (see
 * TraceRequestMiddleware::record()), e.g. `"GET /orders"`. This pulls the
 * verb back out so the table can badge it instead of showing it as part of
 * the name text.
 */
export function parseHttpMethod(name: string | null): KnownMethod | null {
    if (!name) {
        return null;
    }

    const spaceIndex = name.indexOf(' ');

    if (spaceIndex === -1) {
        return null;
    }

    const head = name.slice(0, spaceIndex);

    return (HTTP_METHODS as readonly string[]).includes(head)
        ? (head as KnownMethod)
        : null;
}

/** The route portion of a request span's name, once its method is stripped. */
export function stripHttpMethod(
    name: string | null,
    method: string | null,
): string {
    if (!name) {
        return '—';
    }

    return method ? name.slice(method.length + 1) : name;
}

/**
 * Categorical identity, not status -- each verb gets a fixed hue from the
 * dataviz skill's validated 8-slot palette (blue, orange, aqua, yellow,
 * magenta, ...), taken in the documented order and checked with
 * `validate_palette.js` for adjacent-pair CVD safety. Deliberately skips
 * the palette's red and green slots: red already means "error" everywhere
 * else in this app (StatusBadge, tone.ts), so a red DELETE badge would read
 * as a failed request rather than a verb.
 *
 * Each pair swaps which step carries the text colour vs. the background
 * wash (verified against both chart surfaces): the background always uses
 * the light-mode hue for light and the dark-mode hue for dark (matching
 * StatusBadge/tone.ts), but for aqua/orange/yellow/magenta the *text*
 * uses the other member of the pair, because the "natural" hue for text
 * measures a hair under WCAG 3:1 against the light surface (2.1-2.7:1) --
 * its darker sibling clears it. Blue is the one hue where the natural
 * pairing already clears 3:1 both ways, so it's left alone.
 *
 * The `-contrast` tokens (app.css) already hold the swapped hue per theme,
 * so no `dark:text-*` override is needed here -- only the background wash's
 * opacity still differs (10% light, 15% dark), which the dark-mode opacity
 * modifier on the background class handles.
 */
const METHOD_COLORS: Record<ColouredMethod, string> = {
    GET: 'bg-[var(--color-tone-neutral)]/10 text-[var(--color-tone-neutral)] dark:bg-[var(--color-tone-neutral)]/15',
    POST: 'bg-[var(--color-method-post)]/10 text-[var(--color-method-post-contrast)] dark:bg-[var(--color-method-post)]/15',
    PUT: 'bg-[var(--color-method-put)]/10 text-[var(--color-method-put-contrast)] dark:bg-[var(--color-method-put)]/15',
    PATCH: 'bg-[var(--color-tone-warning)]/10 text-[var(--color-tone-warning-contrast)] dark:bg-[var(--color-tone-warning)]/15',
    DELETE: 'bg-[var(--color-method-delete)]/10 text-[var(--color-method-delete-contrast)] dark:bg-[var(--color-method-delete)]/15',
};

function isColouredMethod(method: string): method is ColouredMethod {
    return method in METHOD_COLORS;
}

export function MethodBadge({ method }: { method: string | null }) {
    if (!method) {
        return <span className="text-muted-foreground">—</span>;
    }

    const classes = isColouredMethod(method)
        ? METHOD_COLORS[method]
        : 'bg-muted text-muted-foreground';

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${classes}`}
        >
            {method}
        </span>
    );
}
