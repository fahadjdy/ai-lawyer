<script setup lang="ts">
/**
 * Dependency-free animated donut chart. Each segment is a stroked circle whose
 * dash array grows in on mount (staggered). Hovering a segment or legend row
 * highlights it and swaps the centre readout to that slice. Zero-value slices
 * are dropped; an all-zero dataset renders an empty ring.
 */
import { computed, onMounted, ref } from 'vue';
import { useCountUp } from '@/composables/useCountUp';
import { dot, hex } from '@/lib/chartColors';
import { cn } from '@/lib/utils';

export interface DonutSlice {
    label: string;
    value: number;
    color?: string;
}

const props = withDefaults(
    defineProps<{
        data: DonutSlice[];
        centerLabel?: string;
    }>(),
    { centerLabel: 'Total' },
);

const R = 60;
const C = 2 * Math.PI * R;
const VB = 160; // viewBox is VB x VB, centre at VB/2
const stroke = 22;

const mounted = ref(false);
onMounted(() => requestAnimationFrame(() => (mounted.value = true)));

const active = ref<number | null>(null);

const slices = computed(() => props.data.filter((d) => d.value > 0));
const total = computed(() => slices.value.reduce((s, d) => s + d.value, 0));

/** Pre-compute each arc's length + rotation offset so segments sit end-to-end. */
const segments = computed(() => {
    let acc = 0;
    return slices.value.map((d) => {
        const frac = total.value > 0 ? d.value / total.value : 0;
        const len = frac * C;
        const seg = { ...d, len, offset: -acc, pct: Math.round(frac * 100), hexColor: hex(d.color) };
        acc += len;
        return seg;
    });
});

const centerValue = computed(() => (active.value !== null ? segments.value[active.value]?.value ?? 0 : total.value));
const centerCaption = computed(() =>
    active.value !== null ? segments.value[active.value]?.label ?? props.centerLabel : props.centerLabel,
);
const display = useCountUp(() => centerValue.value, 900);
</script>

<template>
    <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-center sm:gap-6">
        <!-- Ring -->
        <div class="relative mx-auto aspect-square w-[180px] shrink-0">
            <svg :viewBox="`0 0 ${VB} ${VB}`" class="size-full">
                <g :transform="`rotate(-90 ${VB / 2} ${VB / 2})`">
                    <!-- track -->
                    <circle
                        :cx="VB / 2"
                        :cy="VB / 2"
                        :r="R"
                        fill="none"
                        stroke="currentColor"
                        class="text-slate-100"
                        :stroke-width="stroke"
                    />
                    <circle
                        v-for="(seg, i) in segments"
                        :key="seg.label"
                        :cx="VB / 2"
                        :cy="VB / 2"
                        :r="R"
                        fill="none"
                        :stroke="seg.hexColor"
                        :stroke-width="active === i ? stroke + 4 : stroke"
                        :stroke-dasharray="mounted ? `${seg.len} ${C - seg.len}` : `0 ${C}`"
                        :stroke-dashoffset="seg.offset"
                        stroke-linecap="butt"
                        class="cursor-pointer transition-all duration-700 ease-out"
                        :class="{ 'opacity-40': active !== null && active !== i }"
                        :style="{ transitionDelay: `${i * 90}ms` }"
                        @mouseenter="active = i"
                        @mouseleave="active = null"
                    />
                </g>
            </svg>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-3xl font-bold tabular-nums text-slate-900">{{ display }}</span>
                <span class="max-w-[88px] truncate text-xs font-medium text-slate-400">{{ centerCaption }}</span>
            </div>
        </div>

        <!-- Legend -->
        <ul class="w-full min-w-0 flex-1 space-y-1.5">
            <li
                v-for="(seg, i) in segments"
                :key="seg.label"
                class="flex cursor-default items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm transition-colors"
                :class="active === i ? 'bg-slate-50' : ''"
                @mouseenter="active = i"
                @mouseleave="active = null"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <span :class="cn('size-2.5 shrink-0 rounded-full', dot(seg.color))" />
                    <span class="truncate text-slate-600">{{ seg.label }}</span>
                </span>
                <span class="shrink-0 tabular-nums">
                    <span class="font-semibold text-slate-800">{{ seg.value }}</span>
                    <span class="ml-1 text-xs text-slate-400">{{ seg.pct }}%</span>
                </span>
            </li>
            <li v-if="!segments.length" class="px-2 py-1.5 text-sm text-slate-400">No data to display.</li>
        </ul>
    </div>
</template>
