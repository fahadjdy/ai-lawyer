/** Shared date/number formatting helpers (en-IN locale). */

export function formatDate(value: string | null | undefined, withTime = false): string {
    if (!value) return '—';
    const date = new Date(value);
    return date.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    });
}

export function relativeDate(value: string | null | undefined): string {
    if (!value) return '—';
    const diff = new Date(value).getTime() - Date.now();
    const days = Math.round(diff / 86_400_000);
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
    if (Math.abs(days) >= 1) return rtf.format(days, 'day');
    const hours = Math.round(diff / 3_600_000);
    return rtf.format(hours, 'hour');
}

export function initialsOf(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('');
}
