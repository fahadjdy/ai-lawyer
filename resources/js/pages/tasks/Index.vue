<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ColumnData {
    label: string;
    color: string;
    tasks: { data: any[] };
}

const props = defineProps<{
    columns: Record<string, ColumnData>;
    options: { statuses: EnumOption[]; priorities: EnumOption[] };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tasks', href: '/tasks' }];
const columnEntries = computed(() => Object.entries(props.columns));
</script>

<template>
    <Head title="Tasks" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Task board" description="Track work across your matters." />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="[key, col] in columnEntries" :key="key" class="flex flex-col rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                    <div class="mb-3 flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <StatusBadge :label="col.label" :color="col.color" :dot="true" />
                        </div>
                        <span class="text-xs font-medium text-slate-400">{{ col.tasks.data.length }}</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <article
                            v-for="t in col.tasks.data"
                            :key="t.id"
                            class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition hover:shadow-md"
                        >
                            <p class="text-sm font-medium text-slate-800">{{ t.title }}</p>
                            <p v-if="t.case" class="mt-0.5 text-xs text-slate-400">{{ t.case.case_number }}</p>
                            <div class="mt-2.5 flex items-center justify-between">
                                <StatusBadge v-if="t.priority" :label="t.priority.label" :color="t.priority.color" />
                                <span class="text-xs" :class="t.is_overdue ? 'font-medium text-rose-600' : 'text-slate-400'">
                                    {{ formatDate(t.due_at) }}
                                </span>
                            </div>
                            <div v-if="t.assignee" class="mt-2 flex items-center gap-1.5">
                                <span class="flex size-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700">{{ t.assignee.initials }}</span>
                                <span class="text-xs text-slate-500">{{ t.assignee.name }}</span>
                            </div>
                        </article>
                        <p v-if="!col.tasks.data.length" class="px-1 py-6 text-center text-xs text-slate-400">No tasks</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
