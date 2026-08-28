import { Fragment } from 'react';

/**
 * Keywords colored as SQL syntax rather than left as plain text -- clauses,
 * operators, and joins, but not the generic identifiers (table/column
 * names) sitting between them, since those carry the query's actual
 * meaning and shouldn't compete for attention with the boilerplate around
 * them.
 */
const KEYWORDS = new Set([
    'select', 'insert', 'update', 'delete', 'from', 'where', 'and', 'or',
    'not', 'null', 'is', 'in', 'exists', 'join', 'inner', 'left', 'right',
    'outer', 'cross', 'on', 'group', 'by', 'order', 'having', 'limit',
    'offset', 'as', 'distinct', 'union', 'all', 'case', 'when', 'then',
    'else', 'end', 'like', 'between', 'asc', 'desc', 'into', 'values',
    'set', 'default', 'primary', 'key', 'foreign', 'references',
    'constraint', 'index', 'table', 'create', 'alter', 'drop', 'add',
    'column', 'if', 'begin', 'commit', 'rollback', 'transaction',
    'returning', 'with', 'lateral', 'over', 'partition', 'window', 'using',
]);

/**
 * Matches, in order of precedence: a line or block comment, a quoted
 * string/identifier (single, double, or backtick), a number, or a bare
 * word. Anything else (punctuation, whitespace, `?` placeholders) falls
 * through unmatched and is rendered as-is between matches.
 */
const TOKEN_PATTERN =
    /(?<comment>--[^\n]*|\/\*[\s\S]*?\*\/)|(?<string>'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*"|`(?:[^`\\]|\\.)*`)|(?<number>\b\d+\.?\d*\b)|(?<function>[A-Za-z_][A-Za-z0-9_]*)(?=\s*\()|(?<word>\b[A-Za-z_][A-Za-z0-9_]*\b)/g;

const TOKEN_CLASSES: Record<string, string> = {
    comment: 'text-muted-foreground italic',
    string: 'text-[var(--color-method-post)]',
    number: 'text-[var(--color-tone-warning-contrast)]',
    function: 'text-[var(--color-span-notification)]',
    keyword: 'font-semibold text-[var(--color-method-put)]',
};

/**
 * Renders a SQL statement with its keywords, strings, numbers, comments,
 * and function calls colored -- a raw query string is dense and hard to
 * scan at the density this table needs, and a real editor is overkill for
 * one line of text, so this is a small hand-rolled tokenizer instead of an
 * added syntax-highlighting dependency.
 */
export function SqlHighlight({ sql }: { sql: string }) {
    const nodes: React.ReactNode[] = [];
    let cursor = 0;
    let key = 0;

    for (const match of sql.matchAll(TOKEN_PATTERN)) {
        const index = match.index ?? 0;

        if (index > cursor) {
            nodes.push(
                <Fragment key={key++}>{sql.slice(cursor, index)}</Fragment>,
            );
        }

        const groups = match.groups ?? {};
        const [type, text] =
            Object.entries(groups).find(([, value]) => value !== undefined) ??
            [];

        if (type === 'word') {
            const lower = (text ?? '').toLowerCase();
            const className = KEYWORDS.has(lower)
                ? TOKEN_CLASSES.keyword
                : undefined;

            nodes.push(
                className ? (
                    <span key={key++} className={className}>
                        {text}
                    </span>
                ) : (
                    <Fragment key={key++}>{text}</Fragment>
                ),
            );
        } else if (type) {
            nodes.push(
                <span key={key++} className={TOKEN_CLASSES[type]}>
                    {text}
                </span>,
            );
        }

        cursor = index + match[0].length;
    }

    if (cursor < sql.length) {
        nodes.push(<Fragment key={key++}>{sql.slice(cursor)}</Fragment>);
    }

    return <>{nodes}</>;
}
