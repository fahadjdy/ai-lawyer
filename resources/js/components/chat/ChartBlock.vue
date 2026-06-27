<script setup lang="ts">
import { computed } from 'vue';

export interface ChartSpec {
    type: 'pie' | 'donut' | 'bar';
    title?: string;
    data: { label: string; value: number }[];
}

const props = defineProps<{ spec: ChartSpec }>();

// A calm, high-contrast categorical palette (Tailwind 500/600 tones).
const PALETTE = [
    '#6366f1', '#10b981', '#f59e0b', '#f43f5e', '#0ea5e9', '#8b5cf6',
    '#14b8a6', '#f97316', '#84cc16', '#d946ef', '#06b6d4', '#64748b',
];

const items = computed(() =>
    (props.spec.data ?? [])
        .filter((d) => d && typeof d.value === 'number' && isFinite(d.value) && d.value >= 0)
        .map((d, i) => ({ label: String(d.label ?? '—'), value: d.value, color: PALETTE[i % PALETTE.length] })),
);

const total = computed(() => items.value.reduce((s, d) => s + d.value, 0));
const max = computed(() => items.value.reduce((m, d) => Math.max(m, d.value), 0));
const isBar = computed(() => props.spec.type === 'bar');

const pct = (v: number) => (total.value > 0 ? (v / total.value) * 100 : 0);
const fmtPct = (v: number) => {
    const p = pct(v);
    return (Number.isInteger(p) ? p : p.toFixed(1)) + '%';
};

// Pie/donut geometry: unit circle, centre (18,18), radius 16, starting at 12 o'clock, clockwise.
const arcs = computed(() => {
    const R = 16;
    const cx = 18;
    const cy = 18;
    let acc = 0;
    return items.value.map((it) => {
        const frac = total.value > 0 ? it.value / total.value : 0;
        const a0 = acc * 2 * Math.PI;
        const a1 = (acc + frac) * 2 * Math.PI;
        acc += frac;
        // A single slice covering (almost) the whole circle can't be drawn as one arc.
        if (frac >= 0.9999) {
            return { d: `M ${cx} ${cy - R} A ${R} ${R} 0 1 1 ${cx - 0.01} ${cy - R} Z`, color: it.color };
        }
        const x0 = cx + R * Math.sin(a0);
        const y0 = cy - R * Math.cos(a0);
        const x1 = cx + R * Math.sin(a1);
        const y1 = cy - R * Math.cos(a1);
        const large = a1 - a0 > Math.PI ? 1 : 0;
        return { d: `M ${cx} ${cy} L ${x0} ${y0} A ${R} ${R} 0 ${large} 1 ${x1} ${y1} Z`, color: it.color };
    });
});
</script>

<template>
    <figure class="my-3 rounded-xl border border-slate-200 bg-white p-3.5">
        <figcaption v-if="spec.title" class="mb-2.5 text-xs font-semibold tracking-wide text-slate-700">
            {{ spec.title }}
        </figcaption>

        <p v-if="!items.length || total === 0" class="py-3 text-center text-xs text-slate-400">No data to chart.</p>

        <!-- Bar chart: horizontal bars -->
        <div v-else-if="isBar" class="flex flex-col gap-2">
            <div v-for="(it, i) in items" :key="i" class="flex items-center gap-2.5 text-xs">
                <span class="w-28 shrink-0 truncate text-right text-slate-600" :title="it.label">{{ it.label }}</span>
                <div class="h-4 flex-1 overflow-hidden rounded bg-slate-100">
                    <div
                        class="h-full rounded transition-[width] duration-500"
                        :style="{ width: (max > 0 ? (it.value / max) * 100 : 0) + '%', backgroundColor: it.color }"
                    />
                </div>
                <span class="w-9 shrink-0 text-right font-semibold tabular-nums text-slate-700">{{ it.value }}</span>
            </div>
        </div>

        <!-- Pie / donut: SVG slices + legend -->
        <div v-else class="flex flex-wrap items-center gap-x-5 gap-y-3">
            <svg viewBox="0 0 36 36" class="size-32 shrink-0" role="img" :aria-label="spec.title || 'Chart'">
                <path v-for="(a, i) in arcs" :key="i" :d="a.d" :fill="a.color" stroke="#fff" stroke-width="0.4" />
                <circle v-if="spec.type === 'donut'" cx="18" cy="18" r="8" fill="#fff" />
            </svg>
            <ul class="flex min-w-0 flex-1 flex-col gap-1.5">
                <li v-for="(it, i) in items" :key="i" class="flex items-center gap-2 text-xs">
                    <span class="size-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: it.color }" />
                    <span class="min-w-0 flex-1 truncate text-slate-600" :title="it.label">{{ it.label }}</span>
                    <span class="shrink-0 font-semibold tabular-nums text-slate-700">{{ it.value }}</span>
                    <span class="w-12 shrink-0 text-right tabular-nums text-slate-400">{{ fmtPct(it.value) }}</span>
                </li>
            </ul>
        </div>
    </figure>
</template>
