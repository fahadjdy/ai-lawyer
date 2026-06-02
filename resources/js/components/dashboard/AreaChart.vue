<script setup lang="ts">
/**
 * Dependency-free animated area/line chart supporting multiple series.
 *
 * - Renders at the container's exact pixel width (ResizeObserver) so strokes,
 *   dots and text stay crisp instead of being stretched by viewBox scaling.
 * - Each line "draws in" on mount using a normalised `pathLength="1"` dash trick
 *   and its gradient area fades up underneath.
 * - Hovering reveals a guide line, enlarged points and a value tooltip.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { hex } from '@/lib/chartColors';

interface SeriesDef {
    key: string;
    name: string;
    color: string;
}

const props = withDefaults(
    defineProps<{
        data: Array<Record<string, number | string>>;
        series: SeriesDef[];
        height?: number;
    }>(),
    { height: 240 },
);

// Unique gradient ids per instance to avoid <defs> collisions on the page.
let counter = 0;
const uid = `area-${(counter = (counter + 1) % 1e6)}-${props.series.map((s) => s.key).join('')}`;

const pad = { t: 18, r: 16, b: 28, l: 34 };

const wrap = ref<HTMLElement | null>(null);
const W = ref(640);
const mounted = ref(false);
const active = ref<number | null>(null);
let ro: ResizeObserver | undefined;

onMounted(() => {
    if (wrap.value) {
        W.value = wrap.value.clientWidth || 640;
        ro = new ResizeObserver(() => {
            if (wrap.value) W.value = wrap.value.clientWidth || W.value;
        });
        ro.observe(wrap.value);
    }
    requestAnimationFrame(() => (mounted.value = true));
});
onUnmounted(() => ro?.disconnect());

const H = computed(() => props.height);
const innerH = computed(() => H.value - pad.t - pad.b);
const innerW = computed(() => Math.max(10, W.value - pad.l - pad.r));
const n = computed(() => props.data.length);

function niceMax(raw: number): number {
    if (raw <= 5) return 5;
    const pow = 10 ** Math.floor(Math.log10(raw));
    const f = raw / pow;
    const nf = f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10;
    return nf * pow;
}

const maxY = computed(() => {
    let m = 0;
    for (const row of props.data) for (const s of props.series) m = Math.max(m, Number(row[s.key]) || 0);
    return niceMax(Math.max(m, 1));
});

const xAt = (i: number): number => pad.l + (n.value > 1 ? i / (n.value - 1) : 0.5) * innerW.value;
const yAt = (v: number): number => pad.t + innerH.value * (1 - v / maxY.value);
const baseline = computed(() => pad.t + innerH.value);

const lines = computed(() =>
    props.series.map((s) => {
        const pts = props.data.map((row, i) => ({ x: xAt(i), y: yAt(Number(row[s.key]) || 0) }));
        const line = pts.length ? 'M' + pts.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' L') : '';
        const area = pts.length
            ? `M${pts[0].x.toFixed(1)},${baseline.value} L` +
              pts.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' L') +
              ` L${pts[pts.length - 1].x.toFixed(1)},${baseline.value} Z`
            : '';
        return { ...s, pts, line, area, hexColor: hex(s.color) };
    }),
);

const gridLines = computed(() => [0, 0.5, 1].map((f) => ({ y: pad.t + innerH.value * (1 - f), value: Math.round(maxY.value * f) })));

// ---- Hover tooltip geometry --------------------------------------------------
const TW = 132;
const tipH = computed(() => 26 + props.series.length * 17);
const tip = computed(() => {
    if (active.value === null) return null;
    const i = active.value;
    const x = xAt(i);
    const left = Math.min(Math.max(x - TW / 2, pad.l), W.value - pad.r - TW);
    return {
        x,
        left,
        top: pad.t,
        label: String(props.data[i]?.label ?? ''),
        rows: props.series.map((s) => ({ name: s.name, value: Number(props.data[i]?.[s.key]) || 0, hexColor: hex(s.color) })),
    };
});

// Highlighted marker points for the hovered month (empty when not hovering),
// kept as a computed so the template avoids TS-only non-null assertions.
const activePoints = computed(() => {
    if (active.value === null) return [];
    const i = active.value;
    return props.series.map((s) => ({ key: s.key, y: yAt(Number(props.data[i]?.[s.key]) || 0), hexColor: hex(s.color) }));
});
</script>

<template>
    <div ref="wrap" class="w-full select-none">
        <svg :width="W" :height="H" :viewBox="`0 0 ${W} ${H}`" class="overflow-visible">
            <defs>
                <linearGradient v-for="s in lines" :id="`${uid}-${s.key}`" :key="s.key" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="s.hexColor" stop-opacity="0.28" />
                    <stop offset="100%" :stop-color="s.hexColor" stop-opacity="0" />
                </linearGradient>
            </defs>

            <!-- grid -->
            <g>
                <line
                    v-for="g in gridLines"
                    :key="`g-${g.value}`"
                    :x1="pad.l"
                    :x2="W - pad.r"
                    :y1="g.y"
                    :y2="g.y"
                    stroke="currentColor"
                    class="text-slate-100"
                    stroke-width="1"
                />
                <text
                    v-for="g in gridLines"
                    :key="`gt-${g.value}`"
                    :x="pad.l - 8"
                    :y="g.y + 3"
                    text-anchor="end"
                    class="fill-slate-400 text-[10px]"
                >
                    {{ g.value }}
                </text>
            </g>

            <!-- areas + lines -->
            <g v-for="(s, si) in lines" :key="s.key">
                <path
                    :d="s.area"
                    :fill="`url(#${uid}-${s.key})`"
                    class="transition-opacity duration-700 ease-out"
                    :style="{ opacity: mounted ? 1 : 0, transitionDelay: `${600 + si * 120}ms` }"
                />
                <path
                    :d="s.line"
                    fill="none"
                    :stroke="s.hexColor"
                    stroke-width="2.5"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    pathLength="1"
                    stroke-dasharray="1"
                    :stroke-dashoffset="mounted ? 0 : 1"
                    class="transition-[stroke-dashoffset] ease-out"
                    :style="{ transitionDuration: '1500ms', transitionDelay: `${si * 150}ms` }"
                />
            </g>

            <!-- x-axis labels -->
            <text
                v-for="(row, i) in data"
                :key="`x-${i}`"
                :x="xAt(i)"
                :y="H - 8"
                text-anchor="middle"
                class="fill-slate-400 text-[10px]"
            >
                {{ row.label }}
            </text>

            <!-- hover layer -->
            <template v-if="tip">
                <line :x1="tip.x" :x2="tip.x" :y1="pad.t" :y2="baseline" stroke="currentColor" class="text-slate-300" stroke-width="1" stroke-dasharray="3 3" />
                <g v-for="p in activePoints" :key="`hp-${p.key}`">
                    <circle :cx="tip.x" :cy="p.y" r="5" :fill="p.hexColor" class="drop-shadow" />
                    <circle :cx="tip.x" :cy="p.y" r="2" fill="white" />
                </g>
            </template>

            <!-- static dots -->
            <g v-for="s in lines" :key="`dots-${s.key}`">
                <circle
                    v-for="(p, i) in s.pts"
                    :key="i"
                    :cx="p.x"
                    :cy="p.y"
                    r="3"
                    :fill="s.hexColor"
                    class="transition-opacity duration-500"
                    :style="{ opacity: mounted ? 1 : 0, transitionDelay: `${1100 + i * 40}ms` }"
                />
            </g>

            <!-- invisible hover bands -->
            <rect
                v-for="(row, i) in data"
                :key="`band-${i}`"
                :x="n > 1 ? xAt(i) - innerW / (2 * (n - 1)) : pad.l"
                :y="pad.t"
                :width="n > 1 ? innerW / (n - 1) : innerW"
                :height="innerH"
                fill="transparent"
                @mouseenter="active = i"
                @mouseleave="active = null"
            />

            <!-- tooltip -->
            <g v-if="tip" :transform="`translate(${tip.left}, ${tip.top})`" class="pointer-events-none">
                <rect :width="TW" :height="tipH" rx="10" class="fill-white" stroke="currentColor" stroke-opacity="0.08" />
                <text x="12" y="17" class="fill-slate-500 text-[10px] font-semibold uppercase tracking-wide">{{ tip.label }}</text>
                <g v-for="(r, ri) in tip.rows" :key="ri" :transform="`translate(12, ${28 + ri * 17})`">
                    <circle cx="3" cy="-3" r="3.5" :fill="r.hexColor" />
                    <text x="13" y="0" class="fill-slate-500 text-[11px]">{{ r.name }}</text>
                    <text :x="TW - 24" y="0" text-anchor="end" class="fill-slate-900 text-[11px] font-semibold">{{ r.value }}</text>
                </g>
            </g>
        </svg>
    </div>
</template>
