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
import { CalendarDays, MoreHorizontal, Plus } from 'lucide-vue-next';
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

// Group hearings by calendar day for an agenda/timeline view.
const grouped = computed(() => {
    const map: Record<string, HearingRow[]> = {};
    for (const h of props.hearings.data) {
        const day = new Date(h.scheduled_at).toDateString();
        (map[day] ??= []).push(h);
    }
    return Object.entries(map);
});

// ---- Schedule / edit modal ----
const formOpen = ref(false);
const editing = ref<HearingRow | null>(null);
function openSchedule() {
    editing.value = null;
    formOpen.value = true;
}
function openEdit(h: HearingRow) {
    editing.value = h;
    formOpen.value = true;
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
                <template v-if="can.manage" #actions>
                    <Button @click="openSchedule"><Plus class="size-4" /> Schedule hearing</Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
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

        <HearingForm v-model:open="formOpen" :hearing="editing" :options="options" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete hearing?"
            :description="`This hearing on ${deleting ? formatDate(deleting.scheduled_at, true) : ''} will be removed.`"
            confirm-label="Delete hearing"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
