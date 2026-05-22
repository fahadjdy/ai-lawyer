<script setup lang="ts">
/**
 * Consistent page title block with an optional description and a right-aligned
 * actions slot (filters, primary buttons).
 */
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ title }}</h1>
            <p v-if="description" class="text-sm text-slate-500">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
