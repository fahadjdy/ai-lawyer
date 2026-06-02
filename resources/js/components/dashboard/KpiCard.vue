<script setup lang="ts">
/**
 * Headline metric card: animated count-up value, a colored accent bar + icon,
 * an optional month-over-month trend chip, and a hover glow. Entrance is
 * staggered via the `delay` prop so the KPI row cascades in.
 */
import { computed } from 'vue';
import type { LucideIcon } from 'lucide-vue-next';
import { Minus, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { useCountUp } from '@/composables/useCountUp';
import { cn } from '@/lib/utils';

type Accent = 'indigo' | 'emerald' | 'amber' | 'rose' | 'violet' | 'sky';

const props = withDefaults(
    defineProps<{
        label: string;
        value: number;
        icon: LucideIcon;
        accent?: Accent;
        /** Signed percent change vs the previous period. null hides the chip. */
        delta?: number | null;
        /** Caption under the value (defaults to the delta label). */
        sub?: string;
        suffix?: string;
        /** When true a rising delta is bad (e.g. overdue) and shown in rose. */
        invert?: boolean;
        delay?: number;
    }>(),
    { accent: 'indigo', delta: null, delay: 0, invert: false },
);

const display = useCountUp(() => props.value);

const accents: Record<Accent, { chip: string; bar: string; glow: string }> = {
    indigo: { chip: 'bg-indigo-50 text-indigo-600 ring-indigo-100', bar: 'from-indigo-500 to-violet-500', glow: 'bg-indigo-400/20' },
    emerald: { chip: 'bg-emerald-50 text-emerald-600 ring-emerald-100', bar: 'from-emerald-500 to-teal-500', glow: 'bg-emerald-400/20' },
    amber: { chip: 'bg-amber-50 text-amber-600 ring-amber-100', bar: 'from-amber-500 to-orange-500', glow: 'bg-amber-400/20' },
    rose: { chip: 'bg-rose-50 text-rose-600 ring-rose-100', bar: 'from-rose-500 to-pink-500', glow: 'bg-rose-400/20' },
    violet: { chip: 'bg-violet-50 text-violet-600 ring-violet-100', bar: 'from-violet-500 to-fuchsia-500', glow: 'bg-violet-400/20' },
    sky: { chip: 'bg-sky-50 text-sky-600 ring-sky-100', bar: 'from-sky-500 to-blue-500', glow: 'bg-sky-400/20' },
};

const a = computed(() => accents[props.accent]);
const hasDelta = computed(() => props.delta !== null && props.delta !== undefined);
const rising = computed(() => (props.delta ?? 0) > 0);
const flat = computed(() => (props.delta ?? 0) === 0);
const trendIcon = computed(() => (flat.value ? Minus : rising.value ? TrendingUp : TrendingDown));
// "Good" direction depends on the metric: more cases = good, more overdue = bad.
const positive = computed(() => (props.invert ? !rising.value : rising.value));
const trendClass = computed(() =>
    flat.value ? 'text-slate-500 bg-slate-100' : positive.value ? 'text-emerald-700 bg-emerald-50' : 'text-rose-700 bg-rose-50',
);
</script>

<template>
    <div
        class="group relative flex animate-in flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition duration-300 fill-mode-both fade-in slide-in-from-bottom-4 [animation-duration:550ms] hover:-translate-y-1 hover:shadow-lg"
        :style="{ animationDelay: `${delay}ms` }"
    >
        <span :class="cn('absolute inset-x-0 top-0 h-1 bg-gradient-to-r', a.bar)" />
        <span
            :class="cn('pointer-events-none absolute -right-8 -top-8 size-28 rounded-full opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-100', a.glow)"
        />

        <div class="flex items-start justify-between">
            <p class="text-sm font-medium text-slate-500">{{ label }}</p>
            <span
                :class="cn('flex size-10 items-center justify-center rounded-xl ring-1 transition-transform duration-300 group-hover:-rotate-6 group-hover:scale-110', a.chip)"
            >
                <component :is="icon" class="size-5" />
            </span>
        </div>

        <div class="mt-3 flex items-end gap-2">
            <span class="text-3xl font-semibold tracking-tight text-slate-900 tabular-nums">{{ display }}{{ suffix }}</span>
            <span
                v-if="hasDelta"
                :class="cn('mb-1 inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-semibold', trendClass)"
            >
                <component :is="trendIcon" class="size-3" />
                {{ Math.abs(delta ?? 0) }}%
            </span>
        </div>
        <p class="mt-1 text-xs text-slate-400">{{ sub ?? 'vs last month' }}</p>
    </div>
</template>
