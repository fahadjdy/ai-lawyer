<script setup lang="ts">
import DataTable, { type Column } from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import Pagination from '@/components/common/Pagination.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { useFilters } from '@/composables/useFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';

interface CaseRow {
    id: string;
    case_number: string;
    title: string;
    type: string;
    status: EnumOption;
    priority: EnumOption;
    client?: { id: string; name: string } | null;
    lead_lawyer?: { name: string; initials: string } | null;
    court_name: string | null;
    next_hearing_at: string | null;
}

const props = defineProps<{
    cases: Paginated<CaseRow>;
    filters: { search?: string; status?: string; priority?: string; case_type?: string; sort?: string };
    options: { statuses: EnumOption[]; priorities: EnumOption[]; types: EnumOption[] };
}>();

const { can } = usePermissions();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cases', href: '/cases' }];

const { filters } = useFilters('cases.index', {
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    case_type: props.filters.case_type ?? '',
});

const columns: Column[] = [
    { key: 'case_number', label: 'Case #' },
    { key: 'title', label: 'Title', primary: true },
    { key: 'client', label: 'Client' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Priority' },
    { key: 'next_hearing_at', label: 'Next Hearing' },
    { key: 'lead_lawyer', label: 'Lead' },
];

const open = (row: CaseRow) => router.visit(`/cases/${row.id}`);
</script>

<template>
    <Head title="Cases" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Cases" description="All matters managed by your firm.">
                <template #actions>
                    <Button v-if="can('cases.create')" as-child>
                        <Link href="/cases/create"><Plus class="mr-1.5 size-4" /> New Case</Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Filter bar -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <Input v-model="filters.search" placeholder="Search by title, number, court, party…" class="pl-9" />
                </div>
                <select v-model="filters.status" class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in options.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="filters.priority" class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                    <option value="">All priorities</option>
                    <option v-for="p in options.priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
                <select v-model="filters.case_type" class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                    <option value="">All types</option>
                    <option v-for="t in options.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>

            <DataTable :columns="columns" :rows="cases.data" row-key="id" clickable @row-click="open"
                empty-title="No cases found" empty-description="Adjust your filters or create a new case.">
                <template #cell-case_number="{ row }">
                    <span class="font-mono text-xs font-medium text-slate-900">{{ row.case_number }}</span>
                </template>
                <template #cell-title="{ row }">
                    <div class="max-w-xs">
                        <p class="truncate font-medium text-slate-900">{{ row.title }}</p>
                        <p class="truncate text-xs text-slate-400">{{ row.court_name ?? '—' }}</p>
                    </div>
                </template>
                <template #cell-client="{ row }">{{ row.client?.name ?? '—' }}</template>
                <template #cell-status="{ row }"><StatusBadge :label="row.status.label" :color="row.status.color" /></template>
                <template #cell-priority="{ row }"><StatusBadge :label="row.priority.label" :color="row.priority.color" /></template>
                <template #cell-next_hearing_at="{ row }">{{ formatDate(row.next_hearing_at) }}</template>
                <template #cell-lead_lawyer="{ row }">
                    <span v-if="row.lead_lawyer" class="inline-flex items-center gap-2">
                        <span class="flex size-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                            {{ row.lead_lawyer.initials }}
                        </span>
                        <span class="text-sm text-slate-600">{{ row.lead_lawyer.name }}</span>
                    </span>
                    <span v-else>—</span>
                </template>
            </DataTable>

            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ cases.meta?.total ?? cases.data.length }} cases</p>
                <Pagination :links="cases.links" />
            </div>
        </div>
    </AppLayout>
</template>
