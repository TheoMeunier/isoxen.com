import { useMemo } from 'react';

type StackFrame =
    | {
          type: 'frame';
          classPath: string | null;
          method: string;
          location: string;
      }
    | { type: 'more'; count: number };

type StackSection = {
    header: string;
    frames: StackFrame[];
};

/**
 * Splits a formatted "Class.method" frame name at the last dot -- except a
 * closure's function name is itself a bracketed descriptor that can embed
 * further dots (e.g. "Foo.{closure:Some.Namespace::call():12}"), so the
 * split point is the last dot *before* any "{", not the last dot overall.
 */
function splitQualifiedName(qualified: string): {
    classPath: string | null;
    method: string;
} {
    const braceIndex = qualified.indexOf('{');
    const searchEnd = braceIndex === -1 ? qualified.length - 1 : braceIndex - 1;
    const lastDot = qualified.lastIndexOf('.', searchEnd);

    return {
        classPath:
            lastDot === -1
                ? null
                : qualified.slice(0, lastDot).replace(/\./g, '\\'),
        method: lastDot === -1 ? qualified : qualified.slice(lastDot + 1),
    };
}

/**
 * Parses the java-like format OTel's StackTraceFormatter produces (see
 * vendor/open-telemetry/sdk/Common/Exception/StackTraceFormatter.php): an
 * unindented header line ("ExceptionClass: message", or "Caused by: ..."
 * for a chained exception), followed by tab-indented "at Class.method(file:line)"
 * frame lines and the occasional "... N more" collapsed-frame marker.
 * Namespace separators arrive as "." (its formatName() swaps "\" for "."),
 * so they're converted back for display -- the class/method split relies on
 * the last "." in each frame being the method, which holds for the formatter's
 * own output even though it's not a fully general PHP-name parse.
 */
function parseStacktrace(raw: string): StackSection[] {
    const sections: StackSection[] = [];

    for (const rawLine of raw.split('\n')) {
        if (rawLine.startsWith('\t')) {
            const content = rawLine.slice(1);
            const current = sections[sections.length - 1];

            if (!current) {
                continue;
            }

            const moreMatch = content.match(/^\.\.\. (\d+) more$/);

            if (moreMatch) {
                current.frames.push({
                    type: 'more',
                    count: Number(moreMatch[1]),
                });
                continue;
            }

            const frameMatch = content.match(/^at (.+)\((.*)\)$/);

            if (frameMatch) {
                current.frames.push({
                    type: 'frame',
                    ...splitQualifiedName(frameMatch[1]),
                    location: frameMatch[2],
                });
                continue;
            }

            // A line this parser doesn't recognise -- keep it verbatim
            // rather than silently dropping information from the trace.
            current.frames.push({
                type: 'frame',
                classPath: null,
                method: content,
                location: '',
            });
        } else if (rawLine.trim() !== '') {
            sections.push({ header: rawLine, frames: [] });
        }
    }

    return sections;
}

function FrameRow({ frame, index }: { frame: StackFrame; index: number }) {
    if (frame.type === 'more') {
        return (
            <div className="flex items-baseline gap-3 py-0.5 pl-9 text-muted-foreground/70 italic">
                … {frame.count} more
            </div>
        );
    }

    return (
        <div className="flex items-baseline gap-3 py-0.5">
            <span className="w-5 shrink-0 text-right text-muted-foreground/50 tabular-nums">
                {index}
            </span>
            <span className="min-w-0 flex-1 truncate">
                {frame.classPath && (
                    <>
                        <span className="text-[var(--color-method-put)]">
                            {frame.classPath}
                        </span>
                        <span className="text-muted-foreground/60">{'→'}</span>
                    </>
                )}
                <span className="font-medium text-foreground">
                    {frame.method}()
                </span>
            </span>
            {frame.location && (
                <span className="shrink-0 text-muted-foreground">
                    {frame.location}
                </span>
            )}
        </div>
    );
}

/**
 * The exception detail page's stack trace, rendered IDE-style -- coloured
 * class/method names and right-aligned file:line for every frame, instead
 * of a raw dump of OTel's plain-text `exception.stacktrace` event
 * attribute. Every frame is shown: a partial trace hides exactly the line
 * someone came here to find.
 */
export function StackTrace({ raw }: { raw: string }) {
    const sections = useMemo(() => parseStacktrace(raw), [raw]);
    const totalFrames = sections.reduce(
        (sum, section) => sum + section.frames.length,
        0,
    );

    if (totalFrames === 0) {
        // The formatter produced something this parser couldn't make sense
        // of -- fall back to the raw text rather than showing nothing.
        return (
            <pre className="overflow-x-auto p-4 font-mono text-xs whitespace-pre">
                {raw}
            </pre>
        );
    }

    let rendered = 0;

    return (
        <div className="space-y-3 p-4 font-mono text-xs">
            {sections.map((section, sectionIndex) => (
                <div key={sectionIndex}>
                    <p className="mb-1 truncate font-sans font-semibold text-muted-foreground">
                        {section.header}
                    </p>
                    {section.frames.map((frame, rowIndex) => {
                        rendered++;

                        return (
                            <FrameRow
                                key={rowIndex}
                                frame={frame}
                                index={rendered}
                            />
                        );
                    })}
                </div>
            ))}
        </div>
    );
}
