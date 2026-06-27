<script setup lang="ts">
import DataTable, { type Column } from '@/components/common/DataTable.vue';
import FavorabilityCircle from '@/components/cases/FavorabilityCircle.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import Pagination from '@/components/common/Pagination.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { useFilters } from '@/composables/useFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, MoreHorizontal, Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CaseRow {
    id: string;
    case_number: string;
    title: string;
    type: string;
    status: EnumOption;
    priority: EnumOption;
    favorability: number | null;
    client?: { id: string; name: string } | null;
    lead_lawyer?: { name: string; initials: string } | null;
    court_name: string | null;
    next_hearing_at: string | null;
    deleted_at: string | null;
}

const props = defineProps<{
    cases: Paginated<CaseRow>;
    filters: { search?: string; status?: string; priority?: string; case_type?: string; sort?: string | null; trashed?: boolean };
    options: { statuses: EnumOption[]; priorities: EnumOption[]; types: EnumOption[] };
    trashedCount: number;
}>();

const { can } = usePermissions();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cases', href: '/cases' }];

const { filters } = useFilters('cases.index', {
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    case_type: props.filters.case_type ?? '',
    sort: props.filters.sort ?? '',
    trashed: props.filters.trashed ? '1' : '',
});

const isTrash = computed(() => filters.trashed === '1');

// ---- Selection / bulk ----
const selected = ref<(string | number)[]>([]);
function setTrash(on: boolean) {
    selected.value = [];
    filters.trashed = on ? '1' : '';
}

function bulk(action: 'archive' | 'restore') {
    if (selected.value.length === 0) return;
    router.post('/cases/bulk', { action, ids: selected.value }, { preserveScroll: true, onSuccess: () => (selected.value = []) });
}

// ---- Row actions ----
function archive(row: CaseRow) {
    router.delete(`/cases/${row.id}`, { preserveScroll: true });
}
function restore(row: CaseRow) {
    router.put(`/cases/${row.id}/restore`, {}, { preserveScroll: true });
}

// ---- Permanent delete (confirmed) ----
const confirmOpen = ref(false);
const pending = ref<{ type: 'single'; id: string } | { type: 'bulk' } | null>(null);
const confirmText = computed(() =>
    pending.value?.type === 'bulk'
        ? `Permanently delete ${selected.value.length} case(s)? This cannot be undone.`
        : 'This case and all its data will be permanently deleted. This cannot be undone.',
);
function askForce(row: CaseRow) {
    pending.value = { type: 'single', id: row.id };
    confirmOpen.value = true;
}
function askBulkForce() {
    pending.value = { type: 'bulk' };
    confirmOpen.value = true;
}
function confirmForce() {
    if (!pending.value) return;
    const done = () => {
        confirmOpen.value = false;
        pending.value = null;
    };
    if (pending.value.type === 'single') {
        router.delete(`/cases/${pending.value.id}/force`, { preserveScroll: true, onFinish: done });
    } else {
        router.post('/cases/bulk', { action: 'delete', ids: selected.value }, { preserveScroll: true, onSuccess: () => (selected.value = []), onFinish: done });
    }
}

const columns = computed<Column[]>(() => {
    const base: Column[] = [
        { key: 'case_number', label: 'Case #', sortable: true },
        { key: 'title', label: 'Title', primary: true, sortable: true },
        { key: 'client', label: 'Client' },
        { key: 'status', label: 'Status', sortable: true },
        { key: 'priority', label: 'Priority', sortable: true },
    ];
    if (isTrash.value) {
        base.push({ key: 'deleted_at', label: 'Deleted' });
    } else {
        base.push(
            { key: 'favorability', label: 'In Favour' },
            { key: 'next_hearing_at', label: 'Next Hearing', sortable: true },
            { key: 'lead_lawyer', label: 'Lead' },
        );
    }
    base.push({ key: 'actions', label: '', align: 'right', hideLabelOnMobile: true });
    return base;
});

const open = (row: CaseRow) => {
    if (!isTrash.value) router.visit(`/cases/${row.id}`);
};
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

            <!-- Active / Trash toggle -->
            <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-sm">
                <button type="button" class="rounded-md px-3 py-1.5 font-medium transition" :class="!isTrash ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-slate-700'" @click="setTrash(false)">Active</button>
                <button type="button" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition" :class="isTrash ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-slate-700'" @click="setTrash(true)">
                    <Trash2 class="size-3.5" /> Trash
                    <span v-if="trashedCount" class="rounded-full px-1.5 text-xs" :class="isTrash ? 'bg-white/20' : 'bg-slate-100 text-slate-500'">{{ trashedCount }}</span>
                </button>
            </div>

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

            <!-- Bulk action bar -->
            <div v-if="selected.length" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm">
                <span class="font-medium text-indigo-800">{{ selected.length }} selected</span>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" @click="selected = []">Clear</Button>
                    <template v-if="isTrash">
                        <Button variant="outline" size="sm" @click="bulk('restore')"><RotateCcw class="size-3.5" /> Restore</Button>
                        <Button variant="outline" size="sm" class="text-rose-600 hover:text-rose-700" @click="askBulkForce"><Trash2 class="size-3.5" /> Delete permanently</Button>
                    </template>
                    <Button v-else variant="outline" size="sm" class="text-rose-600 hover:text-rose-700" @click="bulk('archive')"><Archive class="size-3.5" /> Archive</Button>
                </div>
            </div>

            <DataTable
                :columns="columns"
                :rows="cases.data"
                row-key="id"
                :clickable="!isTrash"
                :selectable="can('cases.delete')"
                v-model:selected="selected"
                v-model:sort="filters.sort"
                @row-click="open"
                :empty-title="isTrash ? 'Trash is empty' : 'No cases found'"
                :empty-description="isTrash ? 'Archived cases will appear here.' : 'Adjust your filters or create a new case.'"
            >
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
                <template #cell-favorability="{ row }">
                    <div class="flex justify-center"><FavorabilityCircle :value="row.favorability" /></div>
                </template>
                <template #cell-next_hearing_at="{ row }">{{ formatDate(row.next_hearing_at) }}</template>
                <template #cell-deleted_at="{ row }">{{ formatDate(row.deleted_at, true) }}</template>
                <template #cell-lead_lawyer="{ row }">
                    <span v-if="row.lead_lawyer" class="inline-flex items-center gap-2">
                        <span class="flex size-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                            {{ row.lead_lawyer.initials }}
                        </span>
                        <span class="text-sm text-slate-600">{{ row.lead_lawyer.name }}</span>
                    </span>
                    <span v-else>—</span>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end" @click.stop>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Case actions">
                                    <MoreHorizontal class="size-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-44">
                                <template v-if="isTrash">
                                    <DropdownMenuItem @select="restore(row)"><RotateCcw class="size-4" /> Restore</DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="askForce(row)"><Trash2 class="size-4" /> Delete permanently</DropdownMenuItem>
                                </template>
                                <template v-else>
                                    <DropdownMenuItem @select="router.visit(`/cases/${row.id}`)">View</DropdownMenuItem>
                                    <DropdownMenuItem v-if="can('cases.update')" @select="router.visit(`/cases/${row.id}/edit`)"><Pencil class="size-4" /> Edit</DropdownMenuItem>
                                    <template v-if="can('cases.delete')">
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="archive(row)"><Archive class="size-4" /> Archive</DropdownMenuItem>
                                    </template>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </template>
            </DataTable>

            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ cases.meta?.total ?? cases.data.length }} {{ isTrash ? 'archived' : '' }} cases</p>
                <Pagination :links="cases.links" />
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete permanently?"
            :description="confirmText"
            confirm-label="Delete permanently"
            @confirm="confirmForce"
        />
    </AppLayout>
</template>
