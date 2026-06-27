<script setup lang="ts">
import CaseAiAssistant from '@/components/cases/CaseAiAssistant.vue';
import CaseCrossExam from '@/components/cases/CaseCrossExam.vue';
import CaseEventForm from '@/components/cases/CaseEventForm.vue';
import CaseNotes from '@/components/cases/CaseNotes.vue';
import CaseTeamDialog from '@/components/cases/CaseTeamDialog.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import StatTile from '@/components/common/StatTile.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import DashboardCard from '@/components/dashboard/DashboardCard.vue';
import ProgressRing from '@/components/dashboard/ProgressRing.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { gradient } from '@/lib/chartColors';
import { favorabilityLabel, favorabilityToken } from '@/lib/favorability';
import { formatDate, relativeDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarDays,
    Clock,
    Download,
    FileText,
    FolderOpen,
    Gauge,
    Gavel,
    GitBranch,
    ListChecks,
    MoreHorizontal,
    Pencil,
    Plus,
    Scale,
    ShieldCheck,
    Trash2,
    User,
    Users,
} from 'lucide-vue-next';
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

const props = defineProps<{
    case: { data: any };
    stages: EnumOption[];
    statuses: EnumOption[];
    lawyers: { id: number; name: string; designation: string | null; initials: string }[];
    assignedIds: number[];
}>();
const { can } = usePermissions();

// ---- Legal team (co-assignees) ----
const teamOpen = ref(false);

const c = computed(() => props.case.data);
const events = computed<CaseEventRow[]>(() => c.value.events ?? []);
const currentSections = computed<string[]>(() => c.value.current_sections ?? []);

// Favourability (0–100): how strongly the case is assessed to be in our favour.
const hasFavorability = computed(() => c.value.favorability !== null && c.value.favorability !== undefined);
const favToken = computed(() => favorabilityToken(c.value.favorability ?? 0));
const favLabel = computed(() => favorabilityLabel(c.value.favorability ?? 0));

// ---- At-a-glance metrics for the BI stat strip ----
const daysOpen = computed(() => {
    const start = c.value.filing_date ?? c.value.created_at;
    if (!start) return 0;
    return Math.max(0, Math.floor((Date.now() - new Date(start).getTime()) / 86_400_000));
});
const counts = computed<Record<string, number>>(() => c.value.counts ?? {});
const hearingCount = computed(() => counts.value.hearings ?? c.value.hearings?.length ?? 0);
const eventCount = computed(() => counts.value.events ?? events.value.length);
const docCount = computed(() => counts.value.documents ?? 0);
const tasksTotal = computed(() => counts.value.tasks ?? c.value.tasks?.length ?? 0);
const tasksDone = computed(() => counts.value.tasks_done ?? 0);
const taskPct = computed(() => (tasksTotal.value ? Math.round((tasksDone.value / tasksTotal.value) * 100) : 0));
const nextHearingRel = computed(() => (c.value.next_hearing_at ? relativeDate(c.value.next_hearing_at) : '—'));
const statusGradient = computed(() => gradient(c.value.status?.color));

// Cross-exam anticipation needs enough facts to be meaningful (mirrors the AI assistant).
const canRunCrossExam = computed(() => (c.value.description ?? '').trim().length >= 20);

// Cached AI results, keyed by kind ('analysis' | 'cross_exam'), with their staleness.
const aiInsights = computed<Record<string, { payload: any; is_stale: boolean; generated_at: string | null }>>(() => c.value.ai_insights ?? {});

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
            <!-- ===== Hero ===== -->
            <section
                class="relative animate-in overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm fill-mode-both fade-in slide-in-from-bottom-3 [animation-duration:500ms]"
            >
                <span :class="cn('absolute inset-x-0 top-0 h-1 bg-gradient-to-r', statusGradient)" />
                <div class="p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-slate-900 px-2 py-0.5 font-mono text-xs font-medium text-white">{{ c.case_number }}</span>
                                <StatusBadge v-if="c.type" :label="c.type.label" color="slate" :dot="false" />
                                <StatusBadge :label="c.status.label" :color="c.status.color" />
                                <StatusBadge :label="c.priority.label" :color="c.priority.color" />
                            </div>
                            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">{{ c.title }}</h1>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Button v-if="can('cases.update')" variant="outline" as-child>
                                <Link :href="`/cases/${c.id}/edit`"><Pencil class="mr-1.5 size-4" /> Edit</Link>
                            </Button>
                            <Button v-if="can('cases.delete')" variant="outline" class="text-rose-600 hover:bg-rose-50" @click="destroy">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-6 border-t border-slate-100 pt-5 lg:flex-row lg:items-center lg:justify-between">
                        <!-- Key meta -->
                        <dl class="grid flex-1 grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="flex items-center gap-2.5">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-500"><User class="size-4" /></span>
                                <div class="min-w-0">
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Client</dt>
                                    <dd class="truncate text-sm font-medium text-slate-800">
                                        <Link v-if="c.client" :href="`/clients/${c.client.id}`" class="hover:text-indigo-600 hover:underline">{{ c.client.name }}</Link>
                                        <span v-else>—</span>
                                    </dd>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span
                                    v-if="c.lead_lawyer"
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-xs font-semibold text-indigo-700"
                                    >{{ c.lead_lawyer.initials }}</span
                                >
                                <span v-else class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-500"><Users class="size-4" /></span>
                                <div class="min-w-0">
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Lead lawyer</dt>
                                    <dd class="truncate text-sm font-medium text-slate-800">{{ c.lead_lawyer?.name ?? 'Unassigned' }}</dd>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-500"><CalendarClock class="size-4" /></span>
                                <div class="min-w-0">
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Next hearing</dt>
                                    <dd class="truncate text-sm font-medium text-slate-800">
                                        {{ c.next_hearing_at ? formatDate(c.next_hearing_at) : '—' }}
                                        <span v-if="c.next_hearing_at" class="text-xs font-normal text-indigo-500">· {{ nextHearingRel }}</span>
                                    </dd>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-500"><CalendarDays class="size-4" /></span>
                                <div class="min-w-0">
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Filing date</dt>
                                    <dd class="truncate text-sm font-medium text-slate-800">{{ formatDate(c.filing_date) }}</dd>
                                </div>
                            </div>
                        </dl>

                        <!-- Favourability gauge -->
                        <div class="flex shrink-0 items-center justify-center lg:w-48 lg:border-l lg:border-slate-100 lg:pl-6">
                            <ProgressRing
                                v-if="hasFavorability"
                                :value="c.favorability"
                                :color="favToken"
                                :size="124"
                                label="In our favour"
                                :sublabel="favLabel"
                            />
                            <div v-else class="flex flex-col items-center gap-1 py-2 text-center">
                                <span class="flex size-12 items-center justify-center rounded-full bg-slate-50 text-slate-300"><Gauge class="size-6" /></span>
                                <p class="text-sm font-medium text-slate-500">Favourability</p>
                                <p class="text-xs text-slate-400">Not assessed</p>
                                <Link
                                    v-if="can('cases.update')"
                                    :href="`/cases/${c.id}/edit`"
                                    class="text-xs font-medium text-indigo-600 hover:underline"
                                    >Set now</Link
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== BI stat strip ===== -->
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <StatTile label="Days open" :value="daysOpen" :icon="Clock" accent="indigo" :delay="0" />
                <StatTile label="Hearings" :value="hearingCount" :icon="Gavel" accent="violet" :delay="60" />
                <StatTile label="Tasks done" :value="tasksDone" :display="`${tasksDone}/${tasksTotal}`" :icon="ListChecks" accent="amber" :delay="120" />
                <StatTile label="Tracking updates" :value="eventCount" :icon="GitBranch" accent="sky" :delay="180" />
                <StatTile label="Documents" :value="docCount" :icon="FileText" accent="emerald" :delay="240" />
                <StatTile label="Next hearing" :value="0" :display="nextHearingRel" :icon="CalendarClock" accent="rose" :delay="300" />
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- ===== Main column ===== -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Summary -->
                    <DashboardCard title="Summary" subtitle="Matter overview" :icon="FileText" accent="indigo" :delay="40">
                        <p class="whitespace-pre-line text-sm leading-relaxed text-slate-600">
                            {{ c.description || 'No description provided.' }}
                        </p>
                        <div v-if="c.tags?.length" class="mt-4 flex flex-wrap gap-1.5">
                            <span v-for="tag in c.tags" :key="tag" class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600">#{{ tag }}</span>
                        </div>
                    </DashboardCard>

                    <!-- Case tracking -->
                    <DashboardCard title="Case tracking" subtitle="Stage history & applicable sections" :icon="GitBranch" accent="violet" :delay="100">
                        <template #action>
                            <Button v-if="can('cases.update')" size="sm" variant="outline" @click="openAddEvent"><Plus class="size-4" /> Add update</Button>
                        </template>

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
                                        :class="
                                            addedSections(i).includes(s)
                                                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
                                                : 'bg-slate-100 text-slate-600'
                                        "
                                    >
                                        § {{ s
                                        }}<span v-if="addedSections(i).includes(s)" class="ml-0.5 font-bold text-emerald-500" title="Added at this stage">+</span>
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
                    </DashboardCard>

                    <!-- Hearings -->
                    <DashboardCard title="Hearings" subtitle="Scheduled & past sittings" :icon="Gavel" accent="sky" :delay="160">
                        <ol v-if="c.hearings?.length" class="relative space-y-4 border-l border-slate-200 pl-5">
                            <li v-for="h in c.hearings" :key="h.id" class="relative">
                                <span class="absolute -left-[1.4rem] top-1 size-2.5 rounded-full bg-sky-500 ring-4 ring-white" />
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-slate-800">{{ h.purpose ?? 'Hearing' }}</p>
                                    <StatusBadge v-if="h.status" :label="h.status.label" :color="h.status.color" />
                                </div>
                                <p class="text-xs text-slate-500">{{ formatDate(h.scheduled_at, true) }} · {{ h.judge_name ?? '—' }}</p>
                                <p v-if="h.outcome" class="mt-1 text-xs text-slate-600">Outcome: {{ h.outcome }}</p>
                            </li>
                        </ol>
                        <EmptyState v-else :icon="CalendarDays" title="No hearings" description="No hearings recorded for this case." />
                    </DashboardCard>

                    <!-- Cross-examination prep (AI: opponent & judge questions) -->
                    <CaseCrossExam
                        :case-id="c.id"
                        :can-generate="canRunCrossExam"
                        :stored="aiInsights.cross_exam?.payload ?? null"
                        :initial-stale="aiInsights.cross_exam?.is_stale ?? false"
                        :generated-at="aiInsights.cross_exam?.generated_at ?? null"
                    />

                    <!-- Tasks -->
                    <DashboardCard title="Tasks" subtitle="Work items for this matter" :icon="ListChecks" accent="amber" to="/tasks" action-label="Board" :delay="220">
                        <div v-if="tasksTotal" class="mb-4">
                            <div class="mb-1.5 flex items-center justify-between text-xs text-slate-500">
                                <span>{{ tasksDone }} of {{ tasksTotal }} complete</span>
                                <span class="font-semibold text-slate-700">{{ taskPct }}%</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all duration-700 ease-out" :style="{ width: `${taskPct}%` }" />
                            </div>
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
                    </DashboardCard>

                    <!-- Documents & Evidence -->
                    <DashboardCard title="Documents & Evidence" subtitle="Files & exhibits on record" :icon="FolderOpen" accent="emerald" :delay="300">
                        <div v-if="c.documents?.length || c.evidence?.length" class="space-y-4">
                            <div v-if="c.documents?.length">
                                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Documents</p>
                                <ul class="divide-y divide-slate-100">
                                    <li v-for="d in c.documents" :key="d.id" class="flex items-center justify-between gap-3 py-2">
                                        <span class="inline-flex min-w-0 items-center gap-2">
                                            <FileText class="size-4 shrink-0 text-slate-400" />
                                            <span class="truncate text-sm text-slate-700">{{ d.name }}</span>
                                            <span v-if="d.extension" class="rounded bg-slate-100 px-1.5 text-[10px] uppercase text-slate-500">{{ d.extension }}</span>
                                        </span>
                                        <a :href="`/documents/${d.id}/download`" class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-indigo-600 hover:underline">
                                            <Download class="size-3.5" /> {{ d.size }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="c.evidence?.length">
                                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Evidence</p>
                                <ul class="divide-y divide-slate-100">
                                    <li v-for="e in c.evidence" :key="e.id" class="flex items-center justify-between gap-3 py-2">
                                        <Link :href="`/evidence/${e.id}`" class="inline-flex min-w-0 items-center gap-2 hover:opacity-80">
                                            <Gavel class="size-4 shrink-0 text-slate-400" />
                                            <span class="truncate text-sm text-slate-700">{{ e.reference_number ? `[${e.reference_number}] ` : '' }}{{ e.title }}</span>
                                        </Link>
                                        <StatusBadge :label="e.status.label" :color="e.status.color" />
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <EmptyState v-else :icon="ShieldCheck" title="No files" description="No documents or evidence linked to this case yet." />
                    </DashboardCard>

                    <!-- Notes -->
                    <CaseNotes :case-id="c.id" :notes="c.notes ?? []" :can-manage="can('cases.update')" />
                </div>

                <!-- ===== Sidebar ===== -->
                <div class="space-y-6">
                    <!-- AI: which sections may apply to this case (history-aware, cached) -->
                    <CaseAiAssistant
                        :fields="aiFields"
                        :show-apply="false"
                        :history="aiHistory"
                        :case-id="c.id"
                        :stored="aiInsights.analysis?.payload ?? null"
                        :initial-stale="aiInsights.analysis?.is_stale ?? false"
                        :generated-at="aiInsights.analysis?.generated_at ?? null"
                    />

                    <DashboardCard title="Client" :icon="User" accent="emerald" :delay="120">
                        <div v-if="c.client" class="space-y-1">
                            <Link :href="`/clients/${c.client.id}`" class="text-sm font-medium text-indigo-600 hover:underline">{{ c.client.name }}</Link>
                            <p v-if="c.client.company" class="text-xs text-slate-500">{{ c.client.company }}</p>
                            <p class="text-xs text-slate-500">{{ c.client.email }} · {{ c.client.phone }}</p>
                        </div>
                        <p v-else class="text-sm text-slate-400">No client linked.</p>
                    </DashboardCard>

                    <DashboardCard title="Court details" :icon="Scale" accent="blue" :delay="180">
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex justify-between gap-2">
                                <dt class="text-slate-500">Court</dt>
                                <dd class="text-right font-medium text-slate-800">{{ c.court?.name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-slate-500">Type</dt>
                                <dd class="text-right font-medium text-slate-800">{{ c.court?.type ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-slate-500">Jurisdiction</dt>
                                <dd class="text-right font-medium text-slate-800">{{ c.court?.jurisdiction ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-slate-500">Judge</dt>
                                <dd class="text-right font-medium text-slate-800">{{ c.court?.judge_name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-slate-500">Opposing party</dt>
                                <dd class="text-right font-medium text-slate-800">{{ c.opposing_party ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-slate-500">Opposing counsel</dt>
                                <dd class="text-right font-medium text-slate-800">{{ c.opposing_counsel ?? '—' }}</dd>
                            </div>
                        </dl>
                    </DashboardCard>

                    <DashboardCard title="Legal team" :icon="Users" accent="violet" :delay="240">
                        <template v-if="can('cases.assign')" #action>
                            <button class="text-xs font-medium text-indigo-600 hover:underline" @click="teamOpen = true">Edit</button>
                        </template>
                        <div v-if="c.lead_lawyer" class="mb-3 flex items-center gap-2.5">
                            <span class="flex size-9 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">{{ c.lead_lawyer.initials }}</span>
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ c.lead_lawyer.name }}</p>
                                <p class="text-xs text-slate-500">Lead · {{ c.lead_lawyer.designation ?? '' }}</p>
                            </div>
                        </div>
                        <div v-if="c.assignees?.length" class="flex flex-wrap gap-2">
                            <span
                                v-for="a in c.assignees"
                                :key="a.id"
                                class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 py-0.5 pl-0.5 pr-2.5 text-xs text-slate-600"
                            >
                                <span class="flex size-5 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold">{{ a.initials }}</span>
                                {{ a.name }}
                            </span>
                        </div>
                        <p v-else-if="!c.lead_lawyer" class="text-sm text-slate-400">No one assigned.</p>
                    </DashboardCard>
                </div>
            </div>
        </div>

        <CaseEventForm
            v-model:open="eventFormOpen"
            :case-uuid="c.id"
            :stages="stages"
            :statuses="statuses"
            :current-status="c.status?.value"
            :event="editingEvent"
        />
        <CaseTeamDialog v-model:open="teamOpen" :case-id="c.id" :lawyers="lawyers" :selected="assignedIds" />
        <ConfirmDialog
            v-model:open="confirmEventOpen"
            title="Delete this update?"
            :description="`“${deletingEvent?.title ?? ''}” will be removed from the tracking timeline.`"
            confirm-label="Delete update"
            @confirm="confirmDeleteEvent"
        />
    </AppLayout>
</template>
