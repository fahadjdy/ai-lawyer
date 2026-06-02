<script setup lang="ts">
/**
 * Animated radial progress ring with a count-up percentage in the centre. The
 * arc draws in on mount via a stroke-dashoffset transition. Used for the
 * task-completion and win-rate gauges.
 */
import { computed, onMounted, ref } from 'vue';
import { useCountUp } from '@/composables/useCountUp';
import { hex } from '@/lib/chartColors';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        value: number; // 0–100
        label: string;
        sublabel?: string;
        color?: string;
        size?: number;
    }>(),
    { color: 'indigo', size: 132 },
);

const R = 54;
const C = 2 * Math.PI * R;
const stroke = 12;

const mounted = ref(false);
onMounted(() => requestAnimationFrame(() => (mounted.value = true)));

const display = useCountUp(() => props.value);
const clamped = computed(() => Math.max(0, Math.min(100, props.value)));
const offset = computed(() => (mounted.value ? C * (1 - clamped.value / 100) : C));
const strokeHex = computed(() => hex(props.color));
</script>

<template>
    <div class="flex flex-col items-center">
        <div class="relative" :style="{ width: `${size}px`, height: `${size}px` }">
            <svg :viewBox="`0 0 ${R * 2 + stroke} ${R * 2 + stroke}`" class="size-full -rotate-90">
                <circle
                    :cx="R + stroke / 2"
                    :cy="R + stroke / 2"
                    :r="R"
                    fill="none"
                    stroke="currentColor"
                    class="text-slate-100"
                    :stroke-width="stroke"
                />
                <circle
                    :cx="R + stroke / 2"
                    :cy="R + stroke / 2"
                    :r="R"
                    fill="none"
                    :stroke="strokeHex"
                    :stroke-width="stroke"
                    stroke-linecap="round"
                    :stroke-dasharray="C"
                    :stroke-dashoffset="offset"
                    class="transition-[stroke-dashoffset] ease-out"
                    :style="{ transitionDuration: '1400ms' }"
                />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-2xl font-bold tabular-nums text-slate-900">{{ display }}<span class="text-base">%</span></span>
            </div>
        </div>
        <p :class="cn('mt-1 text-sm font-medium text-slate-700')">{{ label }}</p>
        <p v-if="sublabel" class="text-xs text-slate-400">{{ sublabel }}</p>
    </div>
</template>
