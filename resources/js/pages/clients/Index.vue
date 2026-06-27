<script setup lang="ts">
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import Pagination from '@/components/common/Pagination.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFilters } from '@/composables/useFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, EnumOption, Paginated } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Download, MoreHorizontal, Plus, Search, Upload } from 'lucide-vue-next';
import { ref } from 'vue';

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
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clients', href: '/clients' }];
const { filters } = useFilters('clients.index', { search: props.filters.search ?? '', type: props.filters.type ?? '' });

const columns: Column[] = [
    { key: 'name', label: 'Name', primary: true },
    { key: 'type', label: 'Type' },
    { key: 'email', label: 'Email' },
    { key: 'phone', label: 'Phone' },
    { key: 'cases_count', label: 'Cases', align: 'center' },
    { key: 'city', label: 'City' },
    { key: 'actions', label: '', align: 'right', hideLabelOnMobile: true },
];

const open = (row: ClientRow) => router.visit(`/clients/${row.id}`);

// ---- CSV import ----
const importOpen = ref(false);
const importForm = useForm<{ file: File | null }>({ file: null });
function onImportFile(e: Event) {
    importForm.file = (e.target as HTMLInputElement).files?.[0] ?? null;
}
function submitImport() {
    importForm.post('/clients/import', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importOpen.value = false;
            importForm.reset();
        },
    });
}

// Delete flow
const confirmOpen = ref(false);
const deleting = ref<ClientRow | null>(null);
function askDelete(row: ClientRow) {
    deleting.value = row;
    confirmOpen.value = true;
}
function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/clients/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false;
            deleting.value = null;
        },
    });
}
</script>

<template>
    <Head title="Clients" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Clients" description="People and organizations your firm represents.">
                <template #actions>
                    <Button variant="outline" as-child><a href="/clients/export"><Download class="size-4" /> Export CSV</a></Button>
                    <Button v-if="can.create" variant="outline" @click="importOpen = true"><Upload class="size-4" /> Import CSV</Button>
                    <Button v-if="can.create" as-child><Link href="/clients/create"><Plus class="size-4" /> New client</Link></Button>
                </template>
            </PageHeader>

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
                <template #cell-actions="{ row }">
                    <div class="flex justify-end" @click.stop>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Client actions">
                                    <MoreHorizontal class="size-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-36">
                                <DropdownMenuItem @select="router.visit(`/clients/${row.id}`)">View</DropdownMenuItem>
                                <DropdownMenuItem @select="router.visit(`/clients/${row.id}/edit`)">Edit</DropdownMenuItem>
                                <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="askDelete(row)">Delete</DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </template>
            </DataTable>

            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ clients.total ?? clients.data.length }} clients</p>
                <Pagination :links="clients.links" />
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete client?"
            :description="`“${deleting?.name ?? ''}” and their link to cases will be removed.`"
            confirm-label="Delete client"
            @confirm="confirmDelete"
        />

        <Dialog :open="importOpen" @update:open="importOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Import clients from CSV</DialogTitle>
                    <DialogDescription>
                        Columns: Name, Company, Type, Email, Phone, City, State, PAN, GSTIN. Rows whose email already exists are skipped. (Tip: export first to get the format.)
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label for="csv">CSV file</Label>
                        <input id="csv" type="file" accept=".csv,text/csv" class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm" @change="onImportFile" />
                        <p v-if="importForm.errors.file" class="mt-1 text-xs text-rose-600">{{ importForm.errors.file }}</p>
                        <p v-if="importForm.progress" class="mt-1 text-xs text-slate-400">Uploading… {{ Math.round(importForm.progress.percentage ?? 0) }}%</p>
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" @click="importOpen = false">Cancel</Button>
                        <Button :disabled="importForm.processing || !importForm.file" @click="submitImport">Import</Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
