<script setup lang="ts">
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import Pagination from '@/components/common/Pagination.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EvidenceForm from '@/components/evidence/EvidenceForm.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/composables/usePermissions';
import { useFilters } from '@/composables/useFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface EvidenceRow {
    id: string;
    reference_number: string | null;
    title: string;
    description: string | null;
    type: EnumOption;
    status: EnumOption;
    collected_at: string | null;
    collected_by: string | null;
    case: { id: string; case_number: string; title: string } | null;
    case_id: number | null;
    document_id: number | null;
}

const props = defineProps<{
    evidence: Paginated<EvidenceRow>;
    filters: { status?: string; type?: string };
    options: {
        statuses: EnumOption[];
        types: EnumOption[];
        cases: { id: number; name: string }[];
        documents: { id: number; name: string }[];
    };
}>();

const { can } = usePermissions();
const canManage = computed(() => can('evidence.manage'));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Evidence', href: '/evidence' }];
const { filters } = useFilters('evidence.index', { status: props.filters.status ?? '', type: props.filters.type ?? '' });

// ---- Create / edit modal ----
const formOpen = ref(false);
const editing = ref<EvidenceRow | null>(null);
function openCreate() {
    editing.value = null;
    formOpen.value = true;
}
function openEdit(row: EvidenceRow) {
    editing.value = row;
    formOpen.value = true;
}

// ---- Delete ----
const confirmOpen = ref(false);
const deleting = ref<EvidenceRow | null>(null);
function askDelete(row: EvidenceRow) {
    deleting.value = row;
    confirmOpen.value = true;
}
function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/evidence/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false;
            deleting.value = null;
        },
    });
}

const columns: Column[] = [
    { key: 'reference_number', label: 'Ref #' },
    { key: 'title', label: 'Title', primary: true },
    { key: 'type', label: 'Type' },
    { key: 'status', label: 'Status' },
    { key: 'case', label: 'Case' },
    { key: 'collected_at', label: 'Collected' },
    { key: 'actions', label: '', align: 'right', hideLabelOnMobile: true },
];
</script>

<template>
    <Head title="Evidence" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Evidence" description="Track exhibits and chain of custody.">
                <template v-if="canManage" #actions>
                    <Button @click="openCreate"><Plus class="size-4" /> Record evidence</Button>
                </template>
            </PageHeader>

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
                <template #cell-title="{ row }">
                    <Link :href="`/evidence/${row.id}`" class="font-medium text-slate-900 hover:text-indigo-600 hover:underline">{{ row.title }}</Link>
                </template>
                <template #cell-type="{ row }">{{ row.type.label }}</template>
                <template #cell-status="{ row }"><StatusBadge :label="row.status.label" :color="row.status.color" /></template>
                <template #cell-case="{ row }">
                    <Link v-if="row.case" :href="`/cases/${row.case.id}`" class="text-indigo-600 hover:underline">{{ row.case.case_number }}</Link>
                    <span v-else>—</span>
                </template>
                <template #cell-collected_at="{ row }">{{ formatDate(row.collected_at) }}</template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end" @click.stop>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Evidence actions">
                                    <MoreHorizontal class="size-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-40">
                                <DropdownMenuItem @select="router.visit(`/evidence/${row.id}`)">View details</DropdownMenuItem>
                                <template v-if="canManage">
                                    <DropdownMenuItem @select="openEdit(row)">Edit</DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="askDelete(row)">Delete</DropdownMenuItem>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </template>
            </DataTable>

            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ evidence.total ?? evidence.data.length }} items</p>
                <Pagination :links="evidence.links" />
            </div>
        </div>

        <EvidenceForm v-model:open="formOpen" :evidence="editing" :options="options" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete evidence?"
            :description="`“${deleting?.title ?? ''}” will be removed from the register.`"
            confirm-label="Delete evidence"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
