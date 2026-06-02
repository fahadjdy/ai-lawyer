import { onMounted, ref, watch, type Ref } from 'vue';

/**
 * Animates a number from 0 up to `target` on mount (and whenever the target
 * changes) using an ease-out cubic curve. Returns a reactive display value to
 * bind in the template. Honours `prefers-reduced-motion` by snapping instantly.
 *
 * @param target   Getter for the value to animate towards.
 * @param duration Animation length in ms.
 * @param decimals Decimal places to keep (0 for integers/counts).
 */
export function useCountUp(target: () => number, duration = 1100, decimals = 0): Ref<number> {
    const display = ref(0);
    const factor = 10 ** decimals;
    const prefersReduced =
        typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    function round(v: number): number {
        return Math.round(v * factor) / factor;
    }

    function run(to: number): void {
        if (prefersReduced || to === 0 || typeof requestAnimationFrame === 'undefined') {
            display.value = round(to);
            return;
        }
        const start = performance.now();
        const tick = (now: number): void => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - (1 - progress) ** 3;
            display.value = round(to * eased);
            if (progress < 1) requestAnimationFrame(tick);
            else display.value = round(to);
        };
        requestAnimationFrame(tick);
    }

    onMounted(() => run(target()));
    watch(target, (v) => run(v));

    return display;
}
