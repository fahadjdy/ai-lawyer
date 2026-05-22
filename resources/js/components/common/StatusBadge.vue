<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Renders a soft, color-coded pill from a backend enum color token. Centralises
 * the color → Tailwind class mapping so badges look consistent everywhere.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        color?: string;
        dot?: boolean;
    }>(),
    { color: 'slate', dot: true },
);

const palette: Record<string, string> = {
    slate: 'bg-slate-50 text-slate-700 ring-slate-200',
    zinc: 'bg-zinc-50 text-zinc-700 ring-zinc-200',
    blue: 'bg-blue-50 text-blue-700 ring-blue-200',
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    violet: 'bg-violet-50 text-violet-700 ring-violet-200',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    rose: 'bg-rose-50 text-rose-700 ring-rose-200',
};

const dotPalette: Record<string, string> = {
    slate: 'bg-slate-400',
    zinc: 'bg-zinc-400',
    blue: 'bg-blue-500',
    indigo: 'bg-indigo-500',
    violet: 'bg-violet-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    rose: 'bg-rose-500',
};

const classes = computed(() => palette[props.color] ?? palette.slate);
const dotClass = computed(() => dotPalette[props.color] ?? dotPalette.slate);
</script>

<template>
    <span
        :class="cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', classes)"
    >
        <span v-if="dot" :class="cn('size-1.5 rounded-full', dotClass)" />
        {{ label }}
    </span>
</template>
