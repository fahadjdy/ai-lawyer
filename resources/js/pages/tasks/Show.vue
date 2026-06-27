<script setup lang="ts">
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import TaskForm from '@/components/tasks/TaskForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, relativeDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CheckSquare, History, MessageSquare, Pencil, Plus, Send, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface TaskData {
    id: string;
    title: string;
    description: string | null;
    case_id: number | null;
    assigned_to: number | null;
    status: EnumOption;
    priority: EnumOption;
    due_at: string | null;
    completed_at: string | null;
    is_overdue: boolean;
    case: { id: string; case_number: string; title: string } | null;
    assignee: { name: string; initials: string } | null;
    creator: string | null;
    created_at: string | null;
}

interface Change {
    label: string;
    from: string | null;
    to: string | null;
}
interface TimelineEntry {
    id: number;
    event: string | null;
    causer: string | null;
    causer_initials: string | null;
    created_at: string;
    changes: Change[];
}

interface ChecklistItem {
    id: string;
    title: string;
    is_done: boolean;
}
interface CommentEntry {
    id: string;
    body: string;
    author: string | null;
    author_initials: string | null;
    created_at: string | null;
    can_delete: boolean;
}

const props = defineProps<{
    task: TaskData;
    timeline: TimelineEntry[];
    checklist: ChecklistItem[];
    comments: CommentEntry[];
    options: {
        statuses: EnumOption[];
        priorities: EnumOption[];
        cases: { id: number; name: string }[];
        users: { id: number; name: string }[];
    };
    can: { manage: boolean };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Tasks', href: '/tasks' },
    { title: props.task.title, href: `/tasks/${props.task.id}` },
]);

// ---- Edit / delete ----
const editOpen = ref(false);
const confirmOpen = ref(false);
function confirmDelete() {
    router.delete(`/tasks/${props.task.id}`, { onFinish: () => (confirmOpen.value = false) });
}

// ---- Checklist (subtasks) ----
const newItem = ref('');
const checklistProgress = computed(() => {
    const total = props.checklist.length;
    const done = props.checklist.filter((i) => i.is_done).length;
    return { total, done, pct: total ? Math.round((done / total) * 100) : 0 };
});
function addItem() {
    const title = newItem.value.trim();
    if (!title) return;
    router.post(`/tasks/${props.task.id}/items`, { title }, { preserveScroll: true, onSuccess: () => (newItem.value = '') });
}
function toggleItem(item: ChecklistItem) {
    router.put(`/tasks/${props.task.id}/items/${item.id}`, { is_done: !item.is_done }, { preserveScroll: true });
}
function deleteItem(item: ChecklistItem) {
    router.delete(`/tasks/${props.task.id}/items/${item.id}`, { preserveScroll: true });
}

// ---- Comments ----
const newComment = ref('');
function addComment() {
    const body = newComment.value.trim();
    if (!body) return;
    router.post(`/tasks/${props.task.id}/comments`, { body }, { preserveScroll: true, onSuccess: () => (newComment.value = '') });
}
function deleteComment(comment: CommentEntry) {
    router.delete(`/tasks/${props.task.id}/comments/${comment.id}`, { preserveScroll: true });
}

// Friendly one-liner for each timeline entry.
function verb(entry: TimelineEntry): string {
    if (entry.event === 'created') return 'created this task';
    if (entry.event === 'deleted') return 'deleted this task';
    const c = entry.changes;
    if (c.length === 1 && c[0].label === 'Status') return `moved this task to “${c[0].to}”`;
    if (c.length === 1 && c[0].label === 'Assignee') return c[0].to ? `assigned this task to ${c[0].to}` : 'unassigned this task';
    return 'updated this task';
}
// Show the detailed change chips only when they add info beyond the one-liner.
const showChips = (entry: TimelineEntry) => entry.changes.length > 1 || (entry.changes[0] && !['Status', 'Assignee'].includes(entry.changes[0].label));

const eventDot: Record<string, string> = {
    created: 'bg-emerald-500',
    updated: 'bg-blue-500',
    deleted: 'bg-rose-500',
};
</script>

<template>
    <Head :title="task.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6">
            <PageHeader :title="task.title" :description="task.case ? `${task.case.case_number} · ${task.case.title}` : 'General task'">
                <template #actions>
                    <StatusBadge :label="task.status.label" :color="task.status.color" />
                    <template v-if="can.manage">
                        <Button variant="outline" @click="editOpen = true"><Pencil class="size-4" /> Edit</Button>
                        <Button variant="outline" class="text-rose-600 hover:text-rose-700" @click="confirmOpen = true">
                            <Trash2 class="size-4" /> Delete
                        </Button>
                    </template>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Details -->
                <div class="space-y-6 lg:col-span-1">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-3 text-sm font-semibold text-slate-900">Details</h2>
                        <dl class="space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500">Status</dt>
                                <dd><StatusBadge :label="task.status.label" :color="task.status.color" /></dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500">Priority</dt>
                                <dd><StatusBadge :label="task.priority.label" :color="task.priority.color" /></dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500">Assignee</dt>
                                <dd class="flex items-center gap-1.5 text-slate-800">
                                    <span v-if="task.assignee" class="flex size-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700">{{ task.assignee.initials }}</span>
                                    {{ task.assignee?.name ?? 'Unassigned' }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500">Due</dt>
                                <dd :class="task.is_overdue ? 'font-medium text-rose-600' : 'text-slate-800'">{{ formatDate(task.due_at, true) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500">Case</dt>
                                <dd>
                                    <Link v-if="task.case" :href="`/cases/${task.case.id}`" class="text-indigo-600 hover:underline">{{ task.case.case_number }}</Link>
                                    <span v-else class="text-slate-400">—</span>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500">Created by</dt>
                                <dd class="text-slate-800">{{ task.creator ?? '—' }}</dd>
                            </div>
                            <div v-if="task.completed_at" class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500">Completed</dt>
                                <dd class="text-emerald-600">{{ formatDate(task.completed_at, true) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="task.description" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-2 text-sm font-semibold text-slate-900">Description</h2>
                        <p class="whitespace-pre-line text-sm text-slate-600">{{ task.description }}</p>
                    </div>

                    <!-- Checklist (subtasks) -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <CheckSquare class="size-4 text-slate-400" /> Checklist
                            <span v-if="checklistProgress.total" class="ml-auto text-xs font-normal text-slate-400">{{ checklistProgress.done }}/{{ checklistProgress.total }}</span>
                        </h2>

                        <div v-if="checklistProgress.total" class="mb-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: checklistProgress.pct + '%' }" />
                        </div>

                        <ul class="space-y-1">
                            <li v-for="item in checklist" :key="item.id" class="group flex items-center gap-2 rounded-md px-1 py-1 hover:bg-slate-50">
                                <input
                                    type="checkbox"
                                    :checked="item.is_done"
                                    :disabled="!can.manage"
                                    class="size-4 shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-400 disabled:opacity-60"
                                    @change="toggleItem(item)"
                                />
                                <span class="min-w-0 flex-1 truncate text-sm" :class="item.is_done ? 'text-slate-400 line-through' : 'text-slate-700'">{{ item.title }}</span>
                                <button v-if="can.manage" type="button" class="text-slate-300 opacity-0 transition hover:text-rose-600 group-hover:opacity-100" aria-label="Remove item" @click="deleteItem(item)">
                                    <X class="size-3.5" />
                                </button>
                            </li>
                        </ul>

                        <p v-if="checklistProgress.total === 0" class="py-1 text-sm text-slate-400">No subtasks yet.</p>

                        <form v-if="can.manage" class="mt-2 flex items-center gap-2" @submit.prevent="addItem">
                            <input
                                v-model="newItem"
                                type="text"
                                placeholder="Add a subtask…"
                                maxlength="255"
                                class="h-8 flex-1 rounded-md border border-slate-200 px-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                            />
                            <Button type="submit" size="sm" variant="outline" :disabled="!newItem.trim()"><Plus class="size-3.5" /></Button>
                        </form>
                    </div>
                </div>

                <!-- Comments + Track history -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Comments -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <MessageSquare class="size-4 text-slate-400" /> Comments
                            <span v-if="comments.length" class="text-xs font-normal text-slate-400">({{ comments.length }})</span>
                        </h2>

                        <form class="mb-4 flex items-start gap-2" @submit.prevent="addComment">
                            <textarea
                                v-model="newComment"
                                rows="2"
                                placeholder="Write a comment…"
                                maxlength="5000"
                                class="min-h-9 flex-1 rounded-md border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                @keydown.enter.exact.prevent="addComment"
                            />
                            <Button type="submit" :disabled="!newComment.trim()"><Send class="size-4" /></Button>
                        </form>

                        <ul v-if="comments.length" class="space-y-4">
                            <li v-for="c in comments" :key="c.id" class="group flex gap-3">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[11px] font-semibold text-indigo-700">{{ c.author_initials ?? '?' }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-sm font-medium text-slate-800">{{ c.author ?? 'Unknown' }}</span>
                                        <span class="text-xs text-slate-400" :title="formatDate(c.created_at, true)">{{ relativeDate(c.created_at) }}</span>
                                        <button v-if="c.can_delete" type="button" class="ml-auto text-slate-300 opacity-0 transition hover:text-rose-600 group-hover:opacity-100" aria-label="Delete comment" @click="deleteComment(c)">
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                    <p class="whitespace-pre-line text-sm text-slate-600">{{ c.body }}</p>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-slate-400">No comments yet. Start the discussion.</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <History class="size-4 text-slate-400" /> Track history
                        </h2>

                        <ol v-if="timeline.length" class="relative space-y-5 border-l border-slate-200 pl-6">
                            <li v-for="entry in timeline" :key="entry.id" class="relative">
                                <span class="absolute -left-[1.7rem] top-1 flex size-3 items-center justify-center rounded-full ring-4 ring-white" :class="eventDot[entry.event ?? ''] ?? 'bg-slate-400'" />
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <p class="text-sm text-slate-800">
                                        <span class="font-medium">{{ entry.causer ?? 'System' }}</span>
                                        {{ verb(entry) }}
                                    </p>
                                    <span class="text-xs text-slate-400" :title="formatDate(entry.created_at, true)">{{ relativeDate(entry.created_at) }}</span>
                                </div>

                                <ul v-if="showChips(entry)" class="mt-2 space-y-1.5">
                                    <li v-for="(c, i) in entry.changes" :key="i" class="flex flex-wrap items-center gap-1.5 text-xs">
                                        <span class="text-slate-400">{{ c.label }}:</span>
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-600">{{ c.from ?? '—' }}</span>
                                        <ArrowRight class="size-3 text-slate-300" />
                                        <span class="rounded bg-indigo-50 px-1.5 py-0.5 font-medium text-indigo-700">{{ c.to ?? '—' }}</span>
                                    </li>
                                </ul>
                            </li>
                        </ol>

                        <EmptyState
                            v-else
                            :icon="History"
                            title="No history yet"
                            description="Status changes, reassignments and edits will appear here as the task moves."
                        >
                            <template v-if="can.manage" #action>
                                <Button @click="editOpen = true"><Plus class="size-4" /> Make a change</Button>
                            </template>
                        </EmptyState>
                    </div>
                </div>
            </div>
        </div>

        <TaskForm v-model:open="editOpen" :task="{ ...task }" :options="options" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete task?"
            :description="`“${task.title}” will be removed.`"
            confirm-label="Delete task"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
