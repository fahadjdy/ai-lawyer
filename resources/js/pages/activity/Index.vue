<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import Pagination from '@/components/common/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Activity, ShieldCheck } from 'lucide-vue-next';

interface Log {
    id: number;
    description: string;
    event: string | null;
    log_name: string | null;
    subject_type: string;
    causer: string | null;
    created_at: string;
}

defineProps<{ activities: Paginated<Log>; filters: { event?: string } }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Activity Log', href: '/activity' }];

const eventColor: Record<string, string> = {
    created: 'text-emerald-600 bg-emerald-50',
    updated: 'text-blue-600 bg-blue-50',
    deleted: 'text-rose-600 bg-rose-50',
};
</script>

<template>
    <Head title="Activity Log" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Activity log" description="Audit trail of changes across your firm." />

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <ol v-if="activities.data.length" class="relative space-y-5 border-l border-slate-200 pl-6">
                    <li v-for="a in activities.data" :key="a.id" class="relative">
                        <span class="absolute -left-[1.85rem] flex size-6 items-center justify-center rounded-full bg-white ring-4 ring-white">
                            <span class="flex size-6 items-center justify-center rounded-full" :class="eventColor[a.event ?? ''] ?? 'text-slate-500 bg-slate-100'">
                                <Activity class="size-3" />
                            </span>
                        </span>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm text-slate-800">
                                <span class="font-medium">{{ a.causer ?? 'System' }}</span>
                                {{ a.event ?? a.description }}
                                <span class="text-slate-500">{{ a.subject_type }}</span>
                            </p>
                            <span class="text-xs text-slate-400">{{ formatDate(a.created_at, true) }}</span>
                        </div>
                    </li>
                </ol>
                <EmptyState v-else :icon="ShieldCheck" title="No activity yet" description="Changes will appear here as your team works." />
            </div>

            <div class="flex items-center justify-end"><Pagination :links="activities.links" /></div>
        </div>
    </AppLayout>
</template>
