<script setup lang="ts">
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import TaskForm from '@/components/tasks/TaskForm.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { GripVertical, LayoutGrid, List, MoreHorizontal, Plus, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface TaskRow {
    id: string;
    title: string;
    description: string | null;
    case_id: number | null;
    assigned_to: number | null;
    status: EnumOption | null;
    priority: EnumOption | null;
    due_at: string | null;
    is_overdue: boolean;
    case: { id: string; case_number: string; title: string } | null;
    assignee: { name: string; initials: string } | null;
}

interface ColumnData {
    label: string;
    color: string;
    tasks: { data: TaskRow[] };
}

const props = defineProps<{
    columns: Record<string, ColumnData>;
    options: {
        statuses: EnumOption[];
        priorities: EnumOption[];
        cases: { id: number; name: string }[];
        users: { id: number; name: string }[];
    };
    meId: number;
}>();

const { can } = usePermissions();
const canManage = computed(() => can('tasks.manage'));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tasks', href: '/tasks' }];
const columnKeys = computed(() => Object.keys(props.columns));

// ---- View toggle (board / list) + filters ---------------------------------
const view = ref<'board' | 'list'>('board');
onMounted(() => {
    const saved = localStorage.getItem('tasks.view');
    if (saved === 'board' || saved === 'list') view.value = saved;
});
watch(view, (v) => localStorage.setItem('tasks.view', v));

const search = ref('');
const priorityFilter = ref('');
const assigneeFilter = ref('');

const filtersActive = computed(() => !!(search.value.trim() || priorityFilter.value || assigneeFilter.value));

const priorityOptions = computed(() => [
    { value: '', label: 'All priorities' },
    ...props.options.priorities.map((p) => ({ value: p.value, label: p.label })),
]);
const assigneeOptions = computed(() => [
    { value: '', label: 'Anyone' },
    { value: 'me', label: 'Assigned to me' },
    ...props.options.users.map((u) => ({ value: String(u.id), label: u.name })),
]);

function matches(t: TaskRow): boolean {
    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        const hay = `${t.title} ${t.case?.case_number ?? ''} ${t.assignee?.name ?? ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
    }
    if (priorityFilter.value && t.priority?.value !== priorityFilter.value) return false;
    if (assigneeFilter.value === 'me') {
        if (t.assigned_to !== props.meId) return false;
    } else if (assigneeFilter.value && String(t.assigned_to) !== assigneeFilter.value) return false;
    return true;
}
function clearFilters() {
    search.value = '';
    priorityFilter.value = '';
    assigneeFilter.value = '';
}

// Visible cards per column (filtered), and a flat list for the List view.
const visibleTasks = (key: string) => (board.value[key] ?? []).filter(matches);
const listTasks = computed<TaskRow[]>(() => columnKeys.value.flatMap((k) => visibleTasks(k)));

const listColumns: Column[] = [
    { key: 'title', label: 'Task', primary: true },
    { key: 'case', label: 'Case' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Priority' },
    { key: 'assignee', label: 'Assignee' },
    { key: 'due_at', label: 'Due' },
    { key: 'actions', label: '', align: 'right', hideLabelOnMobile: true },
];

// ---- Drag & drop with a live insertion indicator --------------------------
// Declared before the board watch below: the watch runs immediately during
// setup and reads dragId, so these must already be initialized.
const dragId = ref<string | null>(null);
const dropCol = ref<string | null>(null);
const dropIndex = ref<number | null>(null);
const justDropped = ref<string | null>(null);

// ---- Local board state (enables optimistic drag + animations) -------------
const board = ref<Record<string, TaskRow[]>>({});
function syncBoard() {
    const next: Record<string, TaskRow[]> = {};
    for (const [key, col] of Object.entries(props.columns)) next[key] = [...col.tasks.data];
    board.value = next;
}
// Rebuild from the server whenever props change — but never mid-drag.
watch(() => props.columns, () => { if (!dragId.value) syncBoard(); }, { immediate: true, deep: true });

type RenderItem =
    | { type: 'card'; id: string; task: TaskRow; idx: number }
    | { type: 'indicator'; id: string };

// Cards for a column, with the glowing drop indicator spliced in at the
// hovered position. When filters are active we show only matching cards and
// disable drag (reordering a filtered subset would be ambiguous).
function renderList(key: string): RenderItem[] {
    const full = board.value[key] ?? [];
    const base = filtersActive.value ? full.filter(matches) : full;
    const items: RenderItem[] = base.map((task) => ({ type: 'card', id: task.id, task, idx: full.indexOf(task) }));
    if (!filtersActive.value && dragId.value && dropCol.value === key && dropIndex.value !== null) {
        const at = Math.max(0, Math.min(dropIndex.value, items.length));
        items.splice(at, 0, { type: 'indicator', id: '__drop__' });
    }
    return items;
}

function onDragStart(task: TaskRow, e: DragEvent) {
    if (!canManage.value) return;
    dragId.value = task.id;
    e.dataTransfer?.setData('text/plain', task.id);
    if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
}

function onCardDragOver(key: string, idx: number, e: DragEvent) {
    if (!dragId.value) return;
    e.preventDefault();
    e.stopPropagation();
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const after = e.clientY - rect.top > rect.height / 2;
    dropCol.value = key;
    dropIndex.value = after ? idx + 1 : idx;
}

function onColumnDragOver(key: string) {
    if (!dragId.value) return;
    // Fires only over empty space / gaps (cards stop propagation) → append.
    dropCol.value = key;
    if (dropIndex.value === null) dropIndex.value = (board.value[key] ?? []).length;
}

function onColumnDragLeave(key: string, e: DragEvent) {
    // Clear only when truly leaving the column (not entering a child).
    if (!(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node)) {
        if (dropCol.value === key) {
            dropCol.value = null;
            dropIndex.value = null;
        }
    }
}

function clearDrag() {
    dragId.value = null;
    dropCol.value = null;
    dropIndex.value = null;
}

function onDrop(key: string) {
    const id = dragId.value;
    const target = dropIndex.value ?? (board.value[key]?.length ?? 0);
    if (!id) return clearDrag();

    // Locate the card's current column & index.
    let srcCol: string | undefined;
    let srcIdx = -1;
    for (const k of columnKeys.value) {
        const i = (board.value[k] ?? []).findIndex((t) => t.id === id);
        if (i !== -1) { srcCol = k; srcIdx = i; break; }
    }
    if (srcCol === undefined) return clearDrag();

    let insertIdx = target;
    if (srcCol === key && srcIdx < target) insertIdx = target - 1;

    // No-op (dropped in the same slot).
    if (srcCol === key && insertIdx === srcIdx) return clearDrag();

    const card = board.value[srcCol][srcIdx];
    board.value[srcCol].splice(srcIdx, 1);
    card.status = props.options.statuses.find((s) => s.value === key) ?? card.status;
    insertIdx = Math.max(0, Math.min(insertIdx, board.value[key].length));
    board.value[key].splice(insertIdx, 0, card);

    justDropped.value = id;
    setTimeout(() => (justDropped.value = null), 600);

    const ids = board.value[key].map((t) => t.id);
    clearDrag();

    // Persist the target column's new order (covers reorder + cross-column).
    router.post('/tasks/reorder', { status: key, ids }, { preserveScroll: true, preserveState: true });
}

// ---- Create / edit modal --------------------------------------------------
const formOpen = ref(false);
const editing = ref<TaskRow | null>(null);
const createStatus = ref('todo');
function openCreate(status = 'todo') {
    editing.value = null;
    createStatus.value = status;
    formOpen.value = true;
}
function openEdit(task: TaskRow) {
    editing.value = task;
    formOpen.value = true;
}

// ---- Menu-based status move (mobile / a11y fallback for drag) --------------
function moveTo(task: TaskRow, status: string) {
    if (task.status?.value === status) return;
    router.put(`/tasks/${task.id}`, { status, silent: true }, { preserveScroll: true });
}
const otherStatuses = (task: TaskRow) => props.options.statuses.filter((s) => s.value !== task.status?.value);

// ---- Delete ----------------------------------------------------------------
const confirmOpen = ref(false);
const deleting = ref<TaskRow | null>(null);
function askDelete(task: TaskRow) {
    deleting.value = task;
    confirmOpen.value = true;
}
function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/tasks/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => { confirmOpen.value = false; deleting.value = null; },
    });
}
</script>

<template>
    <Head title="Tasks" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Tasks" description="Track work across your matters.">
                <template v-if="canManage" #actions>
                    <Button @click="openCreate()"><Plus class="size-4" /> Add task</Button>
                </template>
            </PageHeader>

            <!-- Filters + view toggle -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="relative sm:max-w-xs sm:flex-1">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <Input v-model="search" placeholder="Search tasks…" class="pl-9" />
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:w-auto">
                        <div class="sm:w-40"><SearchableSelect v-model="priorityFilter" :options="priorityOptions" placeholder="Priority" /></div>
                        <div class="sm:w-48"><SearchableSelect v-model="assigneeFilter" :options="assigneeOptions" placeholder="Assignee" /></div>
                    </div>
                    <button v-if="filtersActive" type="button" class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-700" @click="clearFilters">
                        <X class="size-3.5" /> Clear
                    </button>
                </div>

                <div class="inline-flex shrink-0 rounded-lg border border-slate-200 bg-white p-0.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition"
                        :class="view === 'board' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:text-slate-700'"
                        @click="view = 'board'"
                    >
                        <LayoutGrid class="size-4" /> Board
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition"
                        :class="view === 'list' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:text-slate-700'"
                        @click="view = 'list'"
                    >
                        <List class="size-4" /> List
                    </button>
                </div>
            </div>

            <p v-if="view === 'board' && filtersActive" class="text-xs text-slate-400">Drag reordering is paused while filters are active.</p>

            <!-- BOARD VIEW -->
            <div v-show="view === 'board'" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="key in columnKeys"
                    :key="key"
                    class="flex flex-col rounded-xl border bg-slate-50/60 p-3 transition-colors duration-200"
                    :class="dragId && dropCol === key ? 'border-indigo-300 bg-indigo-50/50 ring-1 ring-indigo-200' : 'border-slate-200'"
                >
                    <div class="mb-3 flex items-center justify-between px-1">
                        <StatusBadge :label="columns[key].label" :color="columns[key].color" :dot="true" />
                        <span class="text-xs font-medium text-slate-400">{{ visibleTasks(key).length }}</span>
                    </div>

                    <TransitionGroup
                        name="card"
                        tag="div"
                        class="flex flex-1 flex-col gap-2"
                        @dragover.prevent="onColumnDragOver(key)"
                        @drop.prevent="onDrop(key)"
                        @dragleave="onColumnDragLeave(key, $event)"
                    >
                        <div v-for="item in renderList(key)" :key="item.id">
                            <!-- Animated drop indicator -->
                            <div
                                v-if="item.type === 'indicator'"
                                class="my-0.5 h-1.5 animate-pulse rounded-full bg-indigo-400 shadow-[0_0_10px_2px_rgba(99,102,241,0.55)]"
                            />

                            <!-- Task card -->
                            <article
                                v-else
                                class="group rounded-lg border bg-white p-3 shadow-sm transition-all duration-200"
                                :class="[
                                    canManage && !filtersActive ? 'cursor-grab active:cursor-grabbing' : '',
                                    dragId === item.task.id ? 'border-indigo-300 opacity-40' : 'border-slate-200 hover:shadow-md',
                                    justDropped === item.task.id ? 'ring-2 ring-indigo-300' : '',
                                ]"
                                :draggable="canManage && !filtersActive"
                                @dragstart="onDragStart(item.task, $event)"
                                @dragend="clearDrag"
                                @dragover="onCardDragOver(key, item.idx, $event)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex min-w-0 items-start gap-1.5">
                                        <GripVertical v-if="canManage" class="mt-0.5 size-3.5 shrink-0 text-slate-300 transition group-hover:text-slate-400" />
                                        <Link
                                            :href="`/tasks/${item.task.id}`"
                                            draggable="false"
                                            class="text-sm font-medium text-slate-800 transition hover:text-indigo-600 hover:underline"
                                        >
                                            {{ item.task.title }}
                                        </Link>
                                    </div>
                                    <DropdownMenu v-if="canManage">
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                class="shrink-0 rounded-md p-1 text-slate-400 opacity-100 transition hover:bg-slate-100 hover:text-slate-600 md:opacity-0 md:group-hover:opacity-100"
                                                aria-label="Task actions"
                                            >
                                                <MoreHorizontal class="size-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-44">
                                            <DropdownMenuItem @select="router.visit(`/tasks/${item.task.id}`)">View details</DropdownMenuItem>
                                            <DropdownMenuItem @select="openEdit(item.task)">Edit</DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuLabel class="text-[11px] uppercase tracking-wide text-slate-400">Move to</DropdownMenuLabel>
                                            <DropdownMenuItem v-for="s in otherStatuses(item.task)" :key="s.value" @select="moveTo(item.task, s.value)">
                                                {{ s.label }}
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="askDelete(item.task)">Delete</DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <p v-if="item.task.case" class="mt-0.5 pl-5 text-xs text-slate-400">{{ item.task.case.case_number }}</p>
                                <div class="mt-2.5 flex items-center justify-between pl-5">
                                    <StatusBadge v-if="item.task.priority" :label="item.task.priority.label" :color="item.task.priority.color" />
                                    <span class="text-xs" :class="item.task.is_overdue ? 'font-medium text-rose-600' : 'text-slate-400'">
                                        {{ formatDate(item.task.due_at) }}
                                    </span>
                                </div>
                                <div v-if="item.task.assignee" class="mt-2 flex items-center gap-1.5 pl-5">
                                    <span class="flex size-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700">{{ item.task.assignee.initials }}</span>
                                    <span class="text-xs text-slate-500">{{ item.task.assignee.name }}</span>
                                </div>
                            </article>
                        </div>
                    </TransitionGroup>

                    <button
                        v-if="canManage"
                        class="mt-2 flex items-center justify-center gap-1 rounded-lg border border-dashed border-slate-200 py-2 text-xs font-medium text-slate-400 transition hover:border-indigo-300 hover:bg-white hover:text-indigo-600"
                        @click="openCreate(key)"
                    >
                        <Plus class="size-3.5" /> Add
                    </button>
                    <p v-else-if="!visibleTasks(key).length" class="px-1 py-6 text-center text-xs text-slate-400">No tasks</p>
                </div>
            </div>

            <!-- LIST VIEW -->
            <div v-show="view === 'list'">
                <DataTable
                    :columns="listColumns"
                    :rows="listTasks"
                    row-key="id"
                    clickable
                    empty-title="No tasks"
                    empty-description="No tasks match your filters."
                    @row-click="(t) => router.visit(`/tasks/${t.id}`)"
                >
                    <template #cell-title="{ row }"><span class="font-medium text-slate-900">{{ row.title }}</span></template>
                    <template #cell-case="{ row }">{{ row.case?.case_number ?? '—' }}</template>
                    <template #cell-status="{ row }"><StatusBadge v-if="row.status" :label="row.status.label" :color="row.status.color" /></template>
                    <template #cell-priority="{ row }"><StatusBadge v-if="row.priority" :label="row.priority.label" :color="row.priority.color" /></template>
                    <template #cell-assignee="{ row }">
                        <span v-if="row.assignee" class="inline-flex items-center gap-1.5">
                            <span class="flex size-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700">{{ row.assignee.initials }}</span>
                            <span class="text-slate-600">{{ row.assignee.name }}</span>
                        </span>
                        <span v-else class="text-slate-400">—</span>
                    </template>
                    <template #cell-due_at="{ row }">
                        <span :class="row.is_overdue ? 'font-medium text-rose-600' : 'text-slate-500'">{{ formatDate(row.due_at) }}</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end" @click.stop>
                            <DropdownMenu v-if="canManage">
                                <DropdownMenuTrigger as-child>
                                    <button class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Task actions"><MoreHorizontal class="size-4" /></button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-44">
                                    <DropdownMenuItem @select="router.visit(`/tasks/${row.id}`)">View details</DropdownMenuItem>
                                    <DropdownMenuItem @select="openEdit(row)">Edit</DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuLabel class="text-[11px] uppercase tracking-wide text-slate-400">Move to</DropdownMenuLabel>
                                    <DropdownMenuItem v-for="s in otherStatuses(row)" :key="s.value" @select="moveTo(row, s.value)">{{ s.label }}</DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="askDelete(row)">Delete</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </template>
                </DataTable>
                <p class="mt-3 text-sm text-slate-500">{{ listTasks.length }} task{{ listTasks.length === 1 ? '' : 's' }}</p>
            </div>
        </div>

        <TaskForm v-model:open="formOpen" :task="editing" :options="options" :default-status="createStatus" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete task?"
            :description="`“${deleting?.title ?? ''}” will be removed from the board.`"
            confirm-label="Delete task"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>

<style scoped>
/* FLIP: cards glide to make room as the drop indicator moves. */
.card-move {
    transition: transform 0.2s ease;
}
.card-enter-active,
.card-leave-active {
    transition: all 0.18s ease;
}
.card-enter-from,
.card-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
/* Take leaving cards out of flow so siblings animate smoothly. */
.card-leave-active {
    position: absolute;
    width: calc(100% - 1.5rem);
}
</style>
