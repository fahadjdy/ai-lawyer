<script setup lang="ts">
/**
 * Animated horizontal bar list. Each row's bar grows from 0 to its share of the
 * max value on mount, with a staggered delay. Optionally renders leading
 * initials (team workload) and a trailing percent. Colors fall back to an
 * ordered palette when an item omits its token.
 */
import { computed, onMounted, ref } from 'vue';
import { dot, gradient, SERIES_TOKENS } from '@/lib/chartColors';
import { cn } from '@/lib/utils';

export interface BarItem {
    label: string;
    value: number;
    color?: string;
    initials?: string;
    sub?: string;
}

const props = withDefaults(
    defineProps<{
        items: BarItem[];
        showPercent?: boolean;
        valueSuffix?: string;
    }>(),
    { showPercent: false, valueSuffix: '' },
);

const mounted = ref(false);
onMounted(() => requestAnimationFrame(() => (mounted.value = true)));

const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)));
const total = computed(() => Math.max(1, props.items.reduce((s, i) => s + i.value, 0)));

function color(item: BarItem, index: number): string {
    return item.color ?? SERIES_TOKENS[index % SERIES_TOKENS.length];
}
function widthPct(value: number): number {
    return Math.round((value / max.value) * 100);
}
function sharePct(value: number): number {
    return Math.round((value / total.value) * 100);
}
</script>

<template>
    <div class="space-y-3.5">
        <div v-for="(item, i) in items" :key="item.label" class="group/bar">
            <div class="mb-1.5 flex items-center justify-between gap-2 text-sm">
                <span class="flex min-w-0 items-center gap-2">
                    <span
                        v-if="item.initials"
                        :class="cn('flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold text-white', dot(color(item, i)))"
                    >
                        {{ item.initials }}
                    </span>
                    <span class="truncate font-medium text-slate-700">{{ item.label }}</span>
                    <span v-if="item.sub" class="shrink-0 text-xs text-slate-400">{{ item.sub }}</span>
                </span>
                <span class="shrink-0 tabular-nums text-slate-500">
                    <span class="font-semibold text-slate-700">{{ item.value }}{{ valueSuffix }}</span>
                    <span v-if="showPercent" class="ml-1 text-xs text-slate-400">{{ sharePct(item.value) }}%</span>
                </span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                <div
                    :class="cn('h-full rounded-full bg-gradient-to-r transition-all duration-1000 ease-out', gradient(color(item, i)))"
                    :style="{ width: mounted ? `${widthPct(item.value)}%` : '0%', transitionDelay: `${i * 80}ms` }"
                />
            </div>
        </div>
    </div>
</template>
