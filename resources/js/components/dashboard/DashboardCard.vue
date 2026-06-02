<script setup lang="ts">
/**
 * Consistent shell for every dashboard widget: an icon chip + title/subtitle
 * header with an optional "View all" link, a staggered fade/slide entrance and
 * a subtle hover lift. Content goes in the default slot; override the header
 * action via the `action` slot.
 */
import type { LucideIcon } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

type Accent = 'indigo' | 'emerald' | 'amber' | 'violet' | 'rose' | 'blue' | 'sky';

withDefaults(
    defineProps<{
        title: string;
        subtitle?: string;
        icon?: LucideIcon;
        accent?: Accent;
        to?: string;
        actionLabel?: string;
        /** Entrance stagger in ms. */
        delay?: number;
        /** Drop the inner padding (for edge-to-edge charts). */
        flush?: boolean;
    }>(),
    { accent: 'indigo', actionLabel: 'View all', delay: 0, flush: false },
);

const chip: Record<Accent, string> = {
    indigo: 'bg-indigo-50 text-indigo-600 ring-indigo-100',
    emerald: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
    amber: 'bg-amber-50 text-amber-600 ring-amber-100',
    violet: 'bg-violet-50 text-violet-600 ring-violet-100',
    rose: 'bg-rose-50 text-rose-600 ring-rose-100',
    blue: 'bg-blue-50 text-blue-600 ring-blue-100',
    sky: 'bg-sky-50 text-sky-600 ring-sky-100',
};
</script>

<template>
    <section
        class="group flex animate-in flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition fill-mode-both fade-in slide-in-from-bottom-3 [animation-duration:600ms] hover:border-slate-300 hover:shadow-md"
        :style="{ animationDelay: `${delay}ms` }"
    >
        <header class="mb-4 flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <span
                    v-if="icon"
                    :class="cn('flex size-9 shrink-0 items-center justify-center rounded-xl ring-1 transition-transform duration-300 group-hover:scale-105', chip[accent])"
                >
                    <component :is="icon" class="size-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="truncate text-sm font-semibold text-slate-900">{{ title }}</h2>
                    <p v-if="subtitle" class="truncate text-xs text-slate-400">{{ subtitle }}</p>
                </div>
            </div>
            <div class="shrink-0">
                <slot name="action">
                    <Link
                        v-if="to"
                        :href="to"
                        class="inline-flex items-center gap-0.5 text-xs font-medium text-indigo-600 transition hover:gap-1.5 hover:text-indigo-700"
                    >
                        {{ actionLabel }}
                        <span aria-hidden="true">→</span>
                    </Link>
                </slot>
            </div>
        </header>

        <div :class="cn('flex-1', flush && '-mx-5 -mb-5')">
            <slot />
        </div>
    </section>
</template>
