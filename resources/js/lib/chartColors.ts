/**
 * Maps the backend's semantic color tokens (the same ones used by StatusBadge
 * and the enum `color()` methods) to concrete values for SVG charts, where we
 * need real hex strings and gradient class pairs rather than Tailwind bg-* utils.
 */

export const CHART_HEX: Record<string, string> = {
    slate: '#64748b',
    zinc: '#71717a',
    blue: '#3b82f6',
    indigo: '#6366f1',
    violet: '#8b5cf6',
    emerald: '#10b981',
    amber: '#f59e0b',
    rose: '#f43f5e',
    sky: '#0ea5e9',
    teal: '#14b8a6',
};

/** Resolve a color token to a hex string, falling back to slate. */
export function hex(token: string | undefined | null): string {
    return CHART_HEX[token ?? 'slate'] ?? CHART_HEX.slate;
}

/** Ordered palette for series/segments that don't carry an explicit token. */
export const SERIES_TOKENS = ['indigo', 'emerald', 'amber', 'violet', 'sky', 'rose', 'blue', 'teal'] as const;

/** `from-x-400 to-x-600` gradient pairs for animated bars/accents. */
export const GRADIENTS: Record<string, string> = {
    slate: 'from-slate-300 to-slate-500',
    zinc: 'from-zinc-300 to-zinc-500',
    blue: 'from-blue-400 to-blue-600',
    indigo: 'from-indigo-400 to-indigo-600',
    violet: 'from-violet-400 to-violet-600',
    emerald: 'from-emerald-400 to-emerald-600',
    amber: 'from-amber-400 to-amber-600',
    rose: 'from-rose-400 to-rose-600',
    sky: 'from-sky-400 to-sky-600',
    teal: 'from-teal-400 to-teal-600',
};

/** Solid bg-* dot class for legends. */
export const DOTS: Record<string, string> = {
    slate: 'bg-slate-400',
    zinc: 'bg-zinc-400',
    blue: 'bg-blue-500',
    indigo: 'bg-indigo-500',
    violet: 'bg-violet-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    rose: 'bg-rose-500',
    sky: 'bg-sky-500',
    teal: 'bg-teal-500',
};

export function gradient(token: string | undefined | null): string {
    return GRADIENTS[token ?? 'indigo'] ?? GRADIENTS.indigo;
}

export function dot(token: string | undefined | null): string {
    return DOTS[token ?? 'slate'] ?? DOTS.slate;
}
