<script setup lang="ts">
import DataTable, { type Column } from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import Pagination from '@/components/common/Pagination.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { useFilters } from '@/composables/useFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption, Paginated } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface EvidenceRow {
    id: string;
    reference_number: string | null;
    title: string;
    type: { value: string; label: string };
    status: EnumOption;
    collected_at: string | null;
    case: { id: string; case_number: string } | null;
}

const props = defineProps<{
    evidence: Paginated<EvidenceRow>;
    filters: { status?: string; type?: string };
    options: { statuses: EnumOption[]; types: EnumOption[] };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Evidence', href: '/evidence' }];
const { filters } = useFilters('evidence.index', { status: props.filters.status ?? '', type: props.filters.type ?? '' });

const columns: Column[] = [
    { key: 'reference_number', label: 'Ref #' },
    { key: 'title', label: 'Title' },
    { key: 'type', label: 'Type' },
    { key: 'status', label: 'Status' },
    { key: 'case', label: 'Case' },
    { key: 'collected_at', label: 'Collected' },
];
</script>

<template>
    <Head title="Evidence" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Evidence" description="Track exhibits and chain of custody." />

            <div class="flex flex-wrap gap-3">
                <select v-model="filters.status" class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in options.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="filters.type" class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                    <option value="">All types</option>
                    <option v-for="t in options.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>

            <DataTable :columns="columns" :rows="evidence.data" row-key="id" empty-title="No evidence recorded">
                <template #cell-reference_number="{ row }"><span class="font-mono text-xs">{{ row.reference_number ?? '—' }}</span></template>
                <template #cell-title="{ row }"><span class="font-medium text-slate-900">{{ row.title }}</span></template>
                <template #cell-type="{ row }">{{ row.type.label }}</template>
                <template #cell-status="{ row }"><StatusBadge :label="row.status.label" :color="row.status.color" /></template>
                <template #cell-case="{ row }">
                    <Link v-if="row.case" :href="`/cases/${row.case.id}`" class="text-indigo-600 hover:underline">{{ row.case.case_number }}</Link>
                    <span v-else>—</span>
                </template>
                <template #cell-collected_at="{ row }">{{ formatDate(row.collected_at) }}</template>
            </DataTable>

            <div class="flex items-center justify-end"><Pagination :links="evidence.links" /></div>
        </div>
    </AppLayout>
</template>
