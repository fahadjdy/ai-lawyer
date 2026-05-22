<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import StatCard from '@/components/common/StatCard.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Briefcase, CalendarClock, CheckSquare, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Stats {
    total_cases: number;
    active_cases: number;
    clients: number;
    open_tasks: number;
    overdue_tasks: number;
    cases_by_status: Record<string, number>;
}

const props = defineProps<{
    stats: Stats;
    statusLegend: EnumOption[];
    upcomingHearings: { data: any[] };
    myTasks: { data: any[] };
    recentCases: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];
const totalForBar = computed(() => Math.max(1, props.stats.total_cases));
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6">
            <PageHeader title="Dashboard" description="Your firm at a glance." />

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                <StatCard label="Total Cases" :value="stats.total_cases" :icon="Briefcase" />
                <StatCard label="Active Cases" :value="stats.active_cases" :icon="CalendarClock" />
                <StatCard label="Clients" :value="stats.clients" :icon="Users" />
                <StatCard label="Open Tasks" :value="stats.open_tasks" :icon="CheckSquare" />
                <StatCard label="Overdue" :value="stats.overdue_tasks" :icon="AlertTriangle" tone="danger" />
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                    <h2 class="text-sm font-semibold text-slate-900">Cases by status</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="s in statusLegend" :key="s.value" class="flex items-center gap-3">
                            <div class="w-24 shrink-0">
                                <StatusBadge :label="s.label" :color="s.color" :dot="false" />
                            </div>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-indigo-500 transition-all"
                                    :style="{ width: `${((stats.cases_by_status[s.value] ?? 0) / totalForBar) * 100}%` }"
                                />
                            </div>
                            <span class="w-8 text-right text-sm font-medium text-slate-600">{{ stats.cases_by_status[s.value] ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-900">Upcoming hearings</h2>
                        <Link href="/hearings" class="text-xs font-medium text-indigo-600 hover:underline">View all</Link>
                    </div>
                    <ul v-if="upcomingHearings.data.length" class="mt-4 space-y-3">
                        <li v-for="h in upcomingHearings.data" :key="h.id" class="flex items-start gap-3">
                            <div class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg bg-indigo-50 text-indigo-700">
                                <span class="text-xs font-bold leading-none">{{ new Date(h.scheduled_at).getDate() }}</span>
                                <span class="text-[10px] uppercase">{{ new Date(h.scheduled_at).toLocaleString('en', { month: 'short' }) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ h.case?.title ?? 'Hearing' }}</p>
                                <p class="truncate text-xs text-slate-500">{{ h.purpose }} · {{ formatDate(h.scheduled_at, true) }}</p>
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else title="No hearings" description="Nothing scheduled in this window." class="mt-4" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-900">My open tasks</h2>
                        <Link href="/tasks" class="text-xs font-medium text-indigo-600 hover:underline">Board</Link>
                    </div>
                    <ul v-if="myTasks.data.length" class="mt-4 divide-y divide-slate-100">
                        <li v-for="t in myTasks.data" :key="t.id" class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ t.title }}</p>
                                <p class="truncate text-xs text-slate-500">{{ t.case?.case_number ?? 'General' }}</p>
                            </div>
                            <StatusBadge v-if="t.priority" :label="t.priority.label" :color="t.priority.color" />
                        </li>
                    </ul>
                    <EmptyState v-else title="All clear" description="You have no open tasks." class="mt-4" />
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-900">Recent cases</h2>
                        <Link href="/cases" class="text-xs font-medium text-indigo-600 hover:underline">View all</Link>
                    </div>
                    <ul v-if="recentCases.length" class="mt-4 divide-y divide-slate-100">
                        <li v-for="c in recentCases" :key="c.id" class="py-2.5">
                            <Link :href="`/cases/${c.id}`" class="flex items-center justify-between gap-3 hover:opacity-80">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-800">{{ c.title }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ c.case_number }} · {{ c.client ?? 'No client' }}</p>
                                </div>
                                <StatusBadge :label="c.status.label" :color="c.status.color" />
                            </Link>
                        </li>
                    </ul>
                    <EmptyState v-else title="No cases yet" class="mt-4" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
