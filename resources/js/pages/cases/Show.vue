<script setup lang="ts">
import CaseAiAssistant from '@/components/cases/CaseAiAssistant.vue';
import CaseEventForm from '@/components/cases/CaseEventForm.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, GitBranch, ListChecks, MoreHorizontal, Pencil, Plus, Scale, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CaseEventRow {
    id: string;
    stage: EnumOption;
    title: string;
    description: string | null;
    sections: string[];
    occurred_on: string | null;
    created_by: string | null;
    created_at: string;
}

const props = defineProps<{ case: { data: any }; stages: EnumOption[] }>();
const { can } = usePermissions();

const c = computed(() => props.case.data);
const events = computed<CaseEventRow[]>(() => c.value.events ?? []);
const currentSections = computed<string[]>(() => c.value.current_sections ?? []);

// Sections newly added at an entry vs. the chronologically previous (older) one.
// Events are newest-first, so the older entry is the next index.
function addedSections(i: number): string[] {
    const prev = events.value[i + 1]?.sections ?? [];
    return (events.value[i].sections ?? []).filter((s) => !prev.includes(s));
}

// Fields fed to the AI assistant to suggest applicable sections for this case.
const aiFields = computed(() => ({
    title: c.value.title,
    description: c.value.description,
    case_type: c.value.type?.value,
    opposing_party: c.value.opposing_party,
    court_name: c.value.court?.name,
}));

// The tracking timeline, oldest-first, so the AI re-analysis is history-aware.
const aiHistory = computed(() =>
    [...events.value].reverse().map((e) => ({
        stage: e.stage?.label,
        title: e.title,
        sections: e.sections ?? [],
        notes: e.description ?? '',
    })),
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Cases', href: '/cases' },
    { title: c.value.case_number, href: `/cases/${c.value.id}` },
]);

const destroy = () => {
    if (confirm('Archive this case? It can be restored by an administrator.')) {
        router.delete(`/cases/${c.value.id}`);
    }
};

// ---- Case Tracking: add / edit / delete entries ----
const eventFormOpen = ref(false);
const editingEvent = ref<CaseEventRow | null>(null);
function openAddEvent() {
    editingEvent.value = null;
    eventFormOpen.value = true;
}
function openEditEvent(e: CaseEventRow) {
    editingEvent.value = e;
    eventFormOpen.value = true;
}

const confirmEventOpen = ref(false);
const deletingEvent = ref<CaseEventRow | null>(null);
function askDeleteEvent(e: CaseEventRow) {
    deletingEvent.value = e;
    confirmEventOpen.value = true;
}
function confirmDeleteEvent() {
    if (!deletingEvent.value) return;
    router.delete(`/cases/${c.value.id}/events/${deletingEvent.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            confirmEventOpen.value = false;
            deletingEvent.value = null;
        },
    });
}
</script>

<template>
    <Head :title="c.case_number" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6">
            <PageHeader :title="c.title" :description="`${c.case_number} · ${c.type?.label ?? ''}`">
                <template #actions>
                    <StatusBadge :label="c.status.label" :color="c.status.color" />
                    <StatusBadge :label="c.priority.label" :color="c.priority.color" />
                    <Button v-if="can('cases.update')" variant="outline" as-child>
                        <Link :href="`/cases/${c.id}/edit`"><Pencil class="mr-1.5 size-4" /> Edit</Link>
                    </Button>
                    <Button v-if="can('cases.delete')" variant="outline" class="text-rose-600 hover:bg-rose-50" @click="destroy">
                        <Trash2 class="size-4" />
                    </Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main column -->
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900">Overview</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ c.description || 'No description provided.' }}</p>
                        <div v-if="c.tags?.length" class="mt-4 flex flex-wrap gap-1.5">
                            <span v-for="tag in c.tags" :key="tag" class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600">#{{ tag }}</span>
                        </div>
                    </div>

                    <!-- Case Tracking -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <GitBranch class="size-4 text-slate-400" />
                                <h2 class="text-sm font-semibold text-slate-900">Case tracking</h2>
                            </div>
                            <Button v-if="can('cases.update')" size="sm" variant="outline" @click="openAddEvent"><Plus class="size-4" /> Add update</Button>
                        </div>

                        <!-- Current applicable sections -->
                        <div v-if="currentSections.length" class="mb-5 rounded-lg border border-indigo-100 bg-indigo-50/50 p-3">
                            <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-500">Current applicable sections</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="s in currentSections" :key="s" class="rounded-md bg-indigo-600 px-2 py-0.5 text-xs font-bold text-white">§ {{ s }}</span>
                            </div>
                        </div>

                        <!-- Timeline -->
                        <ol v-if="events.length" class="relative space-y-5 border-l border-slate-200 pl-6">
                            <li v-for="(e, i) in events" :key="e.id" class="relative">
                                <span class="absolute -left-[1.72rem] top-1.5 size-2.5 rounded-full bg-indigo-500 ring-4 ring-white" />
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <StatusBadge :label="e.stage.label" :color="e.stage.color" :dot="false" />
                                        <span class="text-xs text-slate-400">{{ formatDate(e.occurred_on) }}</span>
                                    </div>
                                    <DropdownMenu v-if="can('cases.update')">
                                        <DropdownMenuTrigger as-child>
                                            <button class="rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Update actions">
                                                <MoreHorizontal class="size-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-32">
                                            <DropdownMenuItem @select="openEditEvent(e)">Edit</DropdownMenuItem>
                                            <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="askDeleteEvent(e)">Delete</DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                                <p class="mt-1 text-sm font-medium text-slate-800">{{ e.title }}</p>
                                <div v-if="e.sections?.length" class="mt-1.5 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="s in e.sections"
                                        :key="s"
                                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium"
                                        :class="addedSections(i).includes(s) ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' : 'bg-slate-100 text-slate-600'"
                                    >
                                        § {{ s }}<span v-if="addedSections(i).includes(s)" class="ml-0.5 font-bold text-emerald-500" title="Added at this stage">+</span>
                                    </span>
                                </div>
                                <p v-if="e.description" class="mt-1.5 whitespace-pre-line text-xs leading-relaxed text-slate-500">{{ e.description }}</p>
                                <p class="mt-1 text-[11px] text-slate-400">by {{ e.created_by ?? 'System' }}</p>
                            </li>
                        </ol>
                        <EmptyState
                            v-else
                            :icon="GitBranch"
                            title="No tracking yet"
                            description="Add the first update to record this case's stages and how its sections change."
                        >
                            <template v-if="can('cases.update')" #action>
                                <Button @click="openAddEvent"><Plus class="size-4" /> Add update</Button>
                            </template>
                        </EmptyState>
                    </div>

                    <!-- Hearings timeline -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <CalendarDays class="size-4 text-slate-400" />
                            <h2 class="text-sm font-semibold text-slate-900">Hearings</h2>
                        </div>
                        <ol v-if="c.hearings?.length" class="relative space-y-4 border-l border-slate-200 pl-5">
                            <li v-for="h in c.hearings" :key="h.id" class="relative">
                                <span class="absolute -left-[1.4rem] top-1 size-2.5 rounded-full bg-indigo-500 ring-4 ring-white" />
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-slate-800">{{ h.purpose ?? 'Hearing' }}</p>
                                    <StatusBadge v-if="h.status" :label="h.status.label" :color="h.status.color" />
                                </div>
                                <p class="text-xs text-slate-500">{{ formatDate(h.scheduled_at, true) }} · {{ h.judge_name ?? '—' }}</p>
                                <p v-if="h.outcome" class="mt-1 text-xs text-slate-600">Outcome: {{ h.outcome }}</p>
                            </li>
                        </ol>
                        <EmptyState v-else :icon="CalendarDays" title="No hearings" description="No hearings recorded for this case." />
                    </div>

                    <!-- Tasks -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <ListChecks class="size-4 text-slate-400" />
                            <h2 class="text-sm font-semibold text-slate-900">Tasks</h2>
                        </div>
                        <ul v-if="c.tasks?.length" class="divide-y divide-slate-100">
                            <li v-for="t in c.tasks" :key="t.id" class="flex items-center justify-between gap-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-800">{{ t.title }}</p>
                                    <p class="text-xs text-slate-500">Due {{ formatDate(t.due_at) }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <StatusBadge v-if="t.priority" :label="t.priority.label" :color="t.priority.color" />
                                    <StatusBadge v-if="t.status" :label="t.status.label" :color="t.status.color" />
                                </div>
                            </li>
                        </ul>
                        <EmptyState v-else :icon="ListChecks" title="No tasks" description="No tasks for this case." />
                    </div>
                </div>

                <!-- Sidebar column -->
                <div class="space-y-6">
                    <!-- AI: which sections may apply to this case (history-aware) -->
                    <CaseAiAssistant :fields="aiFields" :show-apply="false" :history="aiHistory" />

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900">Client</h2>
                        <div v-if="c.client" class="mt-3">
                            <Link :href="`/clients/${c.client.id}`" class="text-sm font-medium text-indigo-600 hover:underline">{{ c.client.name }}</Link>
                            <p class="text-xs text-slate-500">{{ c.client.company }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ c.client.email }} · {{ c.client.phone }}</p>
                        </div>
                        <p v-else class="mt-2 text-sm text-slate-400">No client linked.</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-3 flex items-center gap-2">
                            <Scale class="size-4 text-slate-400" />
                            <h2 class="text-sm font-semibold text-slate-900">Court details</h2>
                        </div>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Court</dt><dd class="text-right text-slate-800">{{ c.court?.name ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Type</dt><dd class="text-right text-slate-800">{{ c.court?.type ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Judge</dt><dd class="text-right text-slate-800">{{ c.court?.judge_name ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Opposing</dt><dd class="text-right text-slate-800">{{ c.opposing_party ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Filing date</dt><dd class="text-right text-slate-800">{{ formatDate(c.filing_date) }}</dd></div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-3 text-sm font-semibold text-slate-900">Legal team</h2>
                        <div v-if="c.lead_lawyer" class="mb-3 flex items-center gap-2">
                            <span class="flex size-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">{{ c.lead_lawyer.initials }}</span>
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ c.lead_lawyer.name }}</p>
                                <p class="text-xs text-slate-500">Lead · {{ c.lead_lawyer.designation ?? '' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="a in c.assignees" :key="a.id" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 py-0.5 pl-0.5 pr-2.5 text-xs text-slate-600">
                                <span class="flex size-5 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold">{{ a.initials }}</span>
                                {{ a.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <CaseEventForm v-model:open="eventFormOpen" :case-uuid="c.id" :stages="stages" :event="editingEvent" />
        <ConfirmDialog
            v-model:open="confirmEventOpen"
            title="Delete this update?"
            :description="`“${deletingEvent?.title ?? ''}” will be removed from the tracking timeline.`"
            confirm-label="Delete update"
            @confirm="confirmDeleteEvent"
        />
    </AppLayout>
</template>
