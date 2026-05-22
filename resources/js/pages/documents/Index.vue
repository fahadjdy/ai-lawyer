<script setup lang="ts">
import DataTable, { type Column } from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import Pagination from '@/components/common/Pagination.vue';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/composables/useFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Search, UploadCloud } from 'lucide-vue-next';

interface DocRow {
    id: string;
    name: string;
    extension: string | null;
    size: string;
    version: number;
    case: { id: string; case_number: string } | null;
    uploaded_by: string | null;
    created_at: string;
}

const props = defineProps<{ documents: Paginated<DocRow>; filters: { search?: string } }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Documents', href: '/documents' }];
const { filters } = useFilters('documents.index', { search: props.filters.search ?? '' });

const columns: Column[] = [
    { key: 'name', label: 'Name' },
    { key: 'case', label: 'Case' },
    { key: 'size', label: 'Size' },
    { key: 'version', label: 'Ver.', align: 'center' },
    { key: 'uploaded_by', label: 'Uploaded by' },
    { key: 'created_at', label: 'Date' },
];
</script>

<template>
    <Head title="Documents" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Documents" description="Secure document repository with versioning." />

            <!-- Drag & drop upload zone (UI shell; wired in next phase) -->
            <div class="flex items-center justify-between gap-4 rounded-xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-500">
                <div class="flex items-center gap-3">
                    <UploadCloud class="size-5 text-slate-400" />
                    <span>Drag &amp; drop files here, or browse. Supports PDF, DOCX, images, audio &amp; video.</span>
                </div>
            </div>

            <div class="relative max-w-md">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <Input v-model="filters.search" placeholder="Search documents…" class="pl-9" />
            </div>

            <DataTable :columns="columns" :rows="documents.data" row-key="id" empty-title="No documents">
                <template #cell-name="{ row }">
                    <span class="inline-flex items-center gap-2">
                        <FileText class="size-4 text-slate-400" />
                        <span class="font-medium text-slate-900">{{ row.name }}</span>
                        <span v-if="row.extension" class="rounded bg-slate-100 px-1.5 text-[10px] uppercase text-slate-500">{{ row.extension }}</span>
                    </span>
                </template>
                <template #cell-case="{ row }">
                    <Link v-if="row.case" :href="`/cases/${row.case.id}`" class="text-indigo-600 hover:underline">{{ row.case.case_number }}</Link>
                    <span v-else>—</span>
                </template>
                <template #cell-version="{ row }">v{{ row.version }}</template>
                <template #cell-created_at="{ row }">{{ formatDate(row.created_at) }}</template>
            </DataTable>

            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ documents.total ?? documents.data.length }} documents</p>
                <Pagination :links="documents.links" />
            </div>
        </div>
    </AppLayout>
</template>
