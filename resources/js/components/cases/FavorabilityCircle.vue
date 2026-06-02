<script setup lang="ts">
/**
 * Compact circular favourability indicator — a small colour-coded ring with the
 * percentage in the centre. Designed for dense tables where a full bar would
 * eat horizontal space. Renders a muted dashed circle when unassessed (null).
 */
import { computed } from 'vue';
import { FAV_TEXT, favorabilityLabel, favorabilityToken } from '@/lib/favorability';
import { hex } from '@/lib/chartColors';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        value: number | null | undefined;
        size?: number;
    }>(),
    { size: 40 },
);

const R = 15;
const C = 2 * Math.PI * R;

const has = computed(() => props.value !== null && props.value !== undefined);
const token = computed(() => favorabilityToken(props.value ?? 0));
const offset = computed(() => C * (1 - (props.value ?? 0) / 100));
const title = computed(() =>
    has.value ? `${props.value}% in favour — ${favorabilityLabel(props.value ?? 0)}` : 'Favourability not assessed',
);
</script>

<template>
    <div :style="{ width: `${size}px`, height: `${size}px` }" :title="title" class="relative inline-flex items-center justify-center">
        <template v-if="has">
            <svg viewBox="0 0 36 36" class="size-full -rotate-90">
                <circle cx="18" cy="18" :r="R" fill="none" stroke="currentColor" class="text-slate-100" stroke-width="3.5" />
                <circle
                    cx="18"
                    cy="18"
                    :r="R"
                    fill="none"
                    :stroke="hex(token)"
                    stroke-width="3.5"
                    stroke-linecap="round"
                    :stroke-dasharray="C"
                    :stroke-dashoffset="offset"
                    class="transition-[stroke-dashoffset] duration-700 ease-out"
                />
            </svg>
            <span :class="cn('absolute text-[11px] font-bold tabular-nums', FAV_TEXT[token])">{{ value }}</span>
        </template>
        <span
            v-else
            class="flex size-full items-center justify-center rounded-full border border-dashed border-slate-200 text-xs text-slate-300"
            >—</span
        >
    </div>
</template>
