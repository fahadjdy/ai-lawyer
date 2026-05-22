<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

/**
 * Renders Laravel paginator links (the `links` array from a paginated payload).
 */
defineProps<{
    links: { url: string | null; label: string; active: boolean }[];
}>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center gap-1">
        <template v-for="(link, i) in links" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                preserve-state
                :class="
                    cn(
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm transition',
                        link.active
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100',
                    )
                "
                v-html="link.label"
            />
            <span
                v-else
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-slate-300"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
