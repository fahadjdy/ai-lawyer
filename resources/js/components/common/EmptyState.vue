<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';
import { Inbox } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        icon?: LucideIcon;
    }>(),
    {
        title: 'Nothing here yet',
        description: 'There are no records to display.',
        icon: () => Inbox as unknown as LucideIcon,
    },
);
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-14 text-center">
        <span class="mb-3 flex size-12 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-slate-100">
            <component :is="icon" class="size-6 text-slate-400" />
        </span>
        <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
