<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    hearings: { data: any[] };
    upcoming: { data: any[] };
    range: { from: string; to: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Hearings', href: '/hearings' }];

// Group hearings by calendar day for an agenda/timeline view.
const grouped = computed(() => {
    const map: Record<string, any[]> = {};
    for (const h of props.hearings.data) {
        const day = new Date(h.scheduled_at).toDateString();
        (map[day] ??= []).push(h);
    }
    return Object.entries(map);
});
</script>

<template>
    <Head title="Hearings" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Hearings" :description="`${formatDate(range.from)} – ${formatDate(range.to)}`" />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <template v-if="grouped.length">
                        <div v-for="[day, items] in grouped" :key="day" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="mb-3 text-sm font-semibold text-slate-900">{{ formatDate(items[0].scheduled_at) }}</h3>
                            <ul class="space-y-3">
                                <li v-for="h in items" :key="h.id" class="flex items-start justify-between gap-3 rounded-lg border border-slate-100 p-3">
                                    <div class="min-w-0">
                                        <Link v-if="h.case" :href="`/cases/${h.case.id}`" class="text-sm font-medium text-indigo-600 hover:underline">{{ h.case.title }}</Link>
                                        <p class="text-xs text-slate-500">{{ h.purpose }} · {{ new Date(h.scheduled_at).toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' }) }} · {{ h.judge_name ?? '—' }}</p>
                                    </div>
                                    <StatusBadge v-if="h.status" :label="h.status.label" :color="h.status.color" />
                                </li>
                            </ul>
                        </div>
                    </template>
                    <EmptyState v-else :icon="CalendarDays" title="No hearings" description="No hearings in this period." />
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold text-slate-900">Next up</h2>
                    <ul v-if="upcoming.data.length" class="space-y-3">
                        <li v-for="h in upcoming.data" :key="h.id" class="flex items-start gap-3">
                            <div class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg bg-indigo-50 text-indigo-700">
                                <span class="text-xs font-bold leading-none">{{ new Date(h.scheduled_at).getDate() }}</span>
                                <span class="text-[10px] uppercase">{{ new Date(h.scheduled_at).toLocaleString('en', { month: 'short' }) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ h.case?.title ?? 'Hearing' }}</p>
                                <p class="truncate text-xs text-slate-500">{{ formatDate(h.scheduled_at, true) }}</p>
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else title="Nothing upcoming" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
