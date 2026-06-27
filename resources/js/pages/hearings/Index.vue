<script setup lang="ts">
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import HearingForm from '@/components/hearings/HearingForm.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, Download, List, MoreHorizontal, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface HearingRow {
    id: string;
    case_id: number | null;
    scheduled_at: string;
    status: EnumOption | null;
    purpose: string | null;
    court_room: string | null;
    judge_name: string | null;
    notes: string | null;
    outcome: string | null;
    next_hearing_at: string | null;
    case: { id: string; title: string; case_number: string } | null;
}

const props = defineProps<{
    hearings: { data: HearingRow[] };
    upcoming: { data: HearingRow[] };
    range: { from: string; to: string };
    options: { statuses: EnumOption[]; cases: { id: number; name: string }[] };
    can: { manage: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Hearings', href: '/hearings' }];

const view = ref<'calendar' | 'agenda'>('calendar');

// Group hearings by calendar day for an agenda/timeline view.
const grouped = computed(() => {
    const map: Record<string, HearingRow[]> = {};
    for (const h of props.hearings.data) {
        const day = new Date(h.scheduled_at).toDateString();
        (map[day] ??= []).push(h);
    }
    return Object.entries(map);
});

// ---- Month calendar grid ----
const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const dayKey = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

// The displayed month is anchored on the range's start date.
const monthStart = computed(() => {
    const d = new Date(props.range.from + 'T00:00:00');
    return new Date(d.getFullYear(), d.getMonth(), 1);
});
const monthLabel = computed(() => monthStart.value.toLocaleString('en', { month: 'long', year: 'numeric' }));

const hearingsByDay = computed(() => {
    const map: Record<string, HearingRow[]> = {};
    for (const h of props.hearings.data) {
        (map[dayKey(new Date(h.scheduled_at))] ??= []).push(h);
    }
    return map;
});

// A fixed 6-week (42-day) grid starting on the Sunday on/before the 1st.
const weeks = computed(() => {
    const gridStart = new Date(monthStart.value);
    gridStart.setDate(gridStart.getDate() - gridStart.getDay());
    const month = monthStart.value.getMonth();
    const todayKey = dayKey(new Date());

    const days = Array.from({ length: 42 }, (_, i) => {
        const d = new Date(gridStart);
        d.setDate(gridStart.getDate() + i);
        const key = dayKey(d);
        return { key, date: d.getDate(), inMonth: d.getMonth() === month, isToday: key === todayKey, raw: d, hearings: hearingsByDay.value[key] ?? [] };
    });

    return Array.from({ length: 6 }, (_, w) => days.slice(w * 7, w * 7 + 7));
});

function navigateMonth(offset: number) {
    const m = new Date(monthStart.value.getFullYear(), monthStart.value.getMonth() + offset, 1);
    router.get(
        '/hearings',
        { from: dayKey(m), to: dayKey(new Date(m.getFullYear(), m.getMonth() + 1, 1)) },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}
function goToday() {
    const now = new Date();
    const m = new Date(now.getFullYear(), now.getMonth(), 1);
    router.get(
        '/hearings',
        { from: dayKey(m), to: dayKey(new Date(m.getFullYear(), m.getMonth() + 1, 1)) },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

// ---- Schedule / edit modal ----
const formOpen = ref(false);
const editing = ref<HearingRow | null>(null);
const presetDate = ref<string | null>(null);
function openSchedule() {
    editing.value = null;
    presetDate.value = null;
    formOpen.value = true;
}
function openEdit(h: HearingRow) {
    editing.value = h;
    presetDate.value = null;
    formOpen.value = true;
}
function openScheduleOn(d: Date) {
    if (!props.can.manage) return;
    editing.value = null;
    presetDate.value = `${dayKey(d)}T10:00`;
    formOpen.value = true;
}
function onChip(h: HearingRow) {
    if (props.can.manage) openEdit(h);
    else if (h.case) router.visit(`/cases/${h.case.id}`);
}

// ---- Delete ----
const confirmOpen = ref(false);
const deleting = ref<HearingRow | null>(null);
function askDelete(h: HearingRow) {
    deleting.value = h;
    confirmOpen.value = true;
}
function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/hearings/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false;
            deleting.value = null;
        },
    });
}

const timeOf = (iso: string) => new Date(iso).toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Head title="Hearings" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Hearings" :description="`${formatDate(range.from)} – ${formatDate(range.to)}`">
                <template #actions>
                    <Button variant="outline" as-child><a href="/hearings/export"><Download class="size-4" /> Export .ics</a></Button>
                    <Button v-if="can.manage" @click="openSchedule"><Plus class="size-4" /> Schedule hearing</Button>
                </template>
            </PageHeader>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-sm">
                    <button type="button" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 transition" :class="view === 'calendar' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-slate-700'" @click="view = 'calendar'"><CalendarDays class="size-4" /> Calendar</button>
                    <button type="button" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 transition" :class="view === 'agenda' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-slate-700'" @click="view = 'agenda'"><List class="size-4" /> Agenda</button>
                </div>
                <div v-if="view === 'calendar'" class="flex items-center gap-1">
                    <Button variant="outline" size="sm" aria-label="Previous month" @click="navigateMonth(-1)"><ChevronLeft class="size-4" /></Button>
                    <span class="min-w-[9rem] text-center text-sm font-semibold text-slate-700">{{ monthLabel }}</span>
                    <Button variant="outline" size="sm" aria-label="Next month" @click="navigateMonth(1)"><ChevronRight class="size-4" /></Button>
                    <Button variant="outline" size="sm" class="ml-1" @click="goToday">Today</Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <!-- Month calendar grid -->
                    <div v-if="view === 'calendar'" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50 text-center text-[11px] font-medium uppercase tracking-wide text-slate-400">
                            <div v-for="wd in weekdays" :key="wd" class="py-2">{{ wd }}</div>
                        </div>
                        <div v-for="(wk, wi) in weeks" :key="wi" class="grid grid-cols-7">
                            <div
                                v-for="day in wk"
                                :key="day.key"
                                class="min-h-[6rem] border-b border-r border-slate-100 p-1.5 last:border-r-0"
                                :class="[day.inMonth ? 'bg-white' : 'bg-slate-50/40', can.manage ? 'cursor-pointer hover:bg-indigo-50/40' : '']"
                                @click="openScheduleOn(day.raw)"
                            >
                                <div class="mb-1 flex justify-end">
                                    <span v-if="day.isToday" class="flex size-5 items-center justify-center rounded-full bg-indigo-600 text-[11px] font-semibold text-white">{{ day.date }}</span>
                                    <span v-else class="text-[11px] font-medium" :class="day.inMonth ? 'text-slate-500' : 'text-slate-300'">{{ day.date }}</span>
                                </div>
                                <div class="space-y-1">
                                    <button
                                        v-for="h in day.hearings.slice(0, 3)"
                                        :key="h.id"
                                        type="button"
                                        class="block w-full truncate rounded bg-indigo-50 px-1.5 py-0.5 text-left text-[11px] text-indigo-700 transition hover:bg-indigo-100"
                                        :title="`${timeOf(h.scheduled_at)} · ${h.case?.title ?? h.purpose ?? 'Hearing'}`"
                                        @click.stop="onChip(h)"
                                    >
                                        <span class="font-semibold">{{ timeOf(h.scheduled_at) }}</span> {{ h.case?.case_number ?? h.purpose ?? 'Hearing' }}
                                    </button>
                                    <span v-if="day.hearings.length > 3" class="block px-1 text-[10px] text-slate-400">+{{ day.hearings.length - 3 }} more</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Agenda -->
                    <template v-if="view === 'agenda'">
                    <template v-if="grouped.length">
                        <div v-for="[day, items] in grouped" :key="day" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="mb-3 text-sm font-semibold text-slate-900">{{ formatDate(items[0].scheduled_at) }}</h3>
                            <ul class="space-y-3">
                                <li v-for="h in items" :key="h.id" class="flex items-start justify-between gap-3 rounded-lg border border-slate-100 p-3">
                                    <div class="min-w-0">
                                        <Link v-if="h.case" :href="`/cases/${h.case.id}`" class="text-sm font-medium text-indigo-600 hover:underline">{{ h.case.title }}</Link>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ h.purpose ?? 'Hearing' }} · {{ timeOf(h.scheduled_at) }}
                                            <span v-if="h.judge_name"> · {{ h.judge_name }}</span>
                                            <span v-if="h.court_room"> · Room {{ h.court_room }}</span>
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <StatusBadge v-if="h.status" :label="h.status.label" :color="h.status.color" />
                                        <DropdownMenu v-if="can.manage">
                                            <DropdownMenuTrigger as-child>
                                                <button class="rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Hearing actions">
                                                    <MoreHorizontal class="size-4" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-36">
                                                <DropdownMenuItem @select="openEdit(h)">Edit</DropdownMenuItem>
                                                <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="askDelete(h)">Delete</DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </template>
                    <EmptyState v-else :icon="CalendarDays" title="No hearings" description="No hearings in this period.">
                        <template v-if="can.manage" #action>
                            <Button @click="openSchedule"><Plus class="size-4" /> Schedule hearing</Button>
                        </template>
                    </EmptyState>
                    </template>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold text-slate-900">Next up</h2>
                    <ul v-if="upcoming.data.length" class="space-y-3">
                        <li v-for="h in upcoming.data" :key="h.id" class="flex items-start gap-3">
                            <div class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg bg-indigo-50 text-indigo-700">
                                <span class="text-xs font-bold leading-none">{{ new Date(h.scheduled_at).getDate() }}</span>
                                <span class="text-[10px] uppercase">{{ new Date(h.scheduled_at).toLocaleString('en', { month: 'short' }) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ h.case?.title ?? 'Hearing' }}</p>
                                <p class="truncate text-xs text-slate-500">{{ formatDate(h.scheduled_at, true) }}</p>
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else title="Nothing upcoming" />
                </div>
            </div>
        </div>

        <HearingForm v-model:open="formOpen" :hearing="editing" :preset-date="presetDate" :options="options" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete hearing?"
            :description="`This hearing on ${deleting ? formatDate(deleting.scheduled_at, true) : ''} will be removed.`"
            confirm-label="Delete hearing"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
