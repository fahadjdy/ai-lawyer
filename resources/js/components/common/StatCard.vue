<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

defineProps<{
    label: string;
    value: string | number;
    icon?: LucideIcon;
    hint?: string;
    tone?: 'default' | 'warning' | 'danger';
}>();

const toneRing: Record<string, string> = {
    default: 'text-indigo-600 bg-indigo-50',
    warning: 'text-amber-600 bg-amber-50',
    danger: 'text-rose-600 bg-rose-50',
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">{{ label }}</p>
            <span v-if="icon" :class="cn('flex size-9 items-center justify-center rounded-lg', toneRing[tone ?? 'default'])">
                <component :is="icon" class="size-4.5" />
            </span>
        </div>
        <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</p>
    </div>
</template>
