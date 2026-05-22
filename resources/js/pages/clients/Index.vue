<script setup lang="ts">
import DataTable, { type Column } from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import Pagination from '@/components/common/Pagination.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/composables/useFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, EnumOption, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';

interface ClientRow {
    id: string;
    name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
    type: EnumOption;
    cases_count: number;
    city: string | null;
}

const props = defineProps<{
    clients: Paginated<ClientRow>;
    filters: { search?: string; type?: string };
    options: { types: EnumOption[] };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clients', href: '/clients' }];
const { filters } = useFilters('clients.index', { search: props.filters.search ?? '', type: props.filters.type ?? '' });

const columns: Column[] = [
    { key: 'name', label: 'Name' },
    { key: 'type', label: 'Type' },
    { key: 'email', label: 'Email' },
    { key: 'phone', label: 'Phone' },
    { key: 'cases_count', label: 'Cases', align: 'center' },
    { key: 'city', label: 'City' },
];

const open = (row: ClientRow) => router.visit(`/clients/${row.id}`);
</script>

<template>
    <Head title="Clients" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Clients" description="People and organizations your firm represents." />

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <Input v-model="filters.search" placeholder="Search clients…" class="pl-9" />
                </div>
                <select v-model="filters.type" class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                    <option value="">All types</option>
                    <option v-for="t in options.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>

            <DataTable :columns="columns" :rows="clients.data" row-key="id" clickable @row-click="open">
                <template #cell-name="{ row }">
                    <div>
                        <p class="font-medium text-slate-900">{{ row.name }}</p>
                        <p v-if="row.company" class="text-xs text-slate-400">{{ row.company }}</p>
                    </div>
                </template>
                <template #cell-type="{ row }"><StatusBadge :label="row.type.label" :color="row.type.color" /></template>
                <template #cell-cases_count="{ row }">
                    <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-medium text-slate-600">{{ row.cases_count }}</span>
                </template>
            </DataTable>

            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ clients.total ?? clients.data.length }} clients</p>
                <Pagination :links="clients.links" />
            </div>
        </div>
    </AppLayout>
</template>
