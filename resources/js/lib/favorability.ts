/**
 * Shared mapping for the case "favourability" metric (0–100): how strongly a
 * matter is assessed to be in the firm's favour. Centralises the colour band +
 * label so the listing, detail view and form preview stay consistent.
 *
 *   < 40   against     → rose
 *   40–59  uncertain   → amber
 *   >= 60  favourable  → emerald
 */
export type FavToken = 'rose' | 'amber' | 'emerald';

export function favorabilityToken(pct: number): FavToken {
    if (pct < 40) return 'rose';
    if (pct < 60) return 'amber';
    return 'emerald';
}

export function favorabilityLabel(pct: number): string {
    if (pct < 25) return 'Strongly against';
    if (pct < 40) return 'Against';
    if (pct < 60) return 'Uncertain';
    if (pct < 75) return 'Favourable';
    return 'Strongly favourable';
}

/** Explicit text-colour classes (kept static so Tailwind's JIT keeps them). */
export const FAV_TEXT: Record<FavToken, string> = {
    rose: 'text-rose-600',
    amber: 'text-amber-600',
    emerald: 'text-emerald-600',
};
