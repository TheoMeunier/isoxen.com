import type { Tone } from '@/lib/tone';
import type { LogEntry } from '@/types/observability';

// Mirrors LogSeverityBreakdownQuery's thresholds -- the OTEL severity_number
// convention (<13 info/debug/trace, 13-16 warning, >=17 error) already used
// for the pills and bar chart above this table. Keeping the same thresholds
// here means a row's badge always agrees with that summary.
function severityTone(severityNumber: number | null): Tone {
    if (severityNumber !== null && severityNumber >= 17) {
        return 'critical';
    }

    if (severityNumber !== null && severityNumber >= 13) {
        return 'warning';
    }

    return 'neutral';
}

// Same three hues as tone.ts's TONE_DOT/TONE_TEXT, reassembled into a pill
// (StatusBadge's shape) rather than imported directly -- StatusBadge
// hardcodes its own colours the same way, so this keeps the precedent
// rather than wiring a shared component for two callers. Colours themselves
// live as tokens in app.css, not hardcoded here.
const TONE_PILL: Record<Tone, string> = {
    neutral:
        'bg-[var(--color-tone-neutral)]/10 text-[var(--color-tone-neutral)] dark:bg-[var(--color-tone-neutral)]/15',
    warning:
        'bg-[var(--color-tone-warning)]/10 text-[var(--color-tone-warning-contrast)] dark:bg-[var(--color-tone-warning)]/15',
    critical:
        'bg-[var(--color-tone-critical)]/10 text-[var(--color-tone-critical)] dark:bg-[var(--color-tone-critical)]/15',
};

export function LogSeverityBadge({
    entry,
}: {
    entry: Pick<LogEntry, 'severity_text' | 'severity_number'>;
}) {
    if (!entry.severity_text) {
        return <span className="text-muted-foreground">—</span>;
    }

    const tone = severityTone(entry.severity_number);

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 font-medium ${TONE_PILL[tone]}`}
        >
            {entry.severity_text}
        </span>
    );
}
