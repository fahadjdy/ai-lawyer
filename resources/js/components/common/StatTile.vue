<script setup lang="ts">
/**
 * Compact metric tile (icon + value + label) for at-a-glance metric strips.
 * Numeric values count up on mount; pass `display` to show a pre-formatted
 * string instead (e.g. "3/8" or "in 5 days"). Entrance is staggered via `delay`.
 */
import type { LucideIcon } from 'lucide-vue-next';
import { useCountUp } from '@/composables/useCountUp';
import { cn } from '@/lib/utils';

type Accent = 'indigo' | 'emerald' | 'amber' | 'rose' | 'violet' | 'sky' | 'slate';

const props = withDefaults(
    defineProps<{
        label: string;
        value: number;
        display?: string;
        icon: LucideIcon;
        accent?: Accent;
        delay?: number;
    }>(),
    { accent: 'slate', delay: 0 },
);

const count = useCountUp(() => props.value);

const chip: Record<Accent, string> = {
    indigo: 'bg-indigo-50 text-indigo-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    rose: 'bg-rose-50 text-rose-600',
    violet: 'bg-violet-50 text-violet-600',
    sky: 'bg-sky-50 text-sky-600',
    slate: 'bg-slate-100 text-slate-600',
};
</script>

<template>
    <div
        class="group flex animate-in items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition fill-mode-both fade-in slide-in-from-bottom-3 [animation-duration:500ms] hover:-translate-y-0.5 hover:shadow-md"
        :style="{ animationDelay: `${delay}ms` }"
    >
        <span
            :class="cn('flex size-10 shrink-0 items-center justify-center rounded-lg transition-transform duration-300 group-hover:scale-110', chip[accent])"
        >
            <component :is="icon" class="size-5" />
        </span>
        <div class="min-w-0">
            <p class="text-lg font-semibold leading-tight tabular-nums text-slate-900">{{ display ?? count }}</p>
            <p class="truncate text-xs text-slate-500">{{ label }}</p>
        </div>
    </div>
</template>
