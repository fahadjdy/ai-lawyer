<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import DashboardCard from '@/components/dashboard/DashboardCard.vue';
import KpiCard from '@/components/dashboard/KpiCard.vue';
import AreaChart from '@/components/dashboard/AreaChart.vue';
import DonutChart from '@/components/dashboard/DonutChart.vue';
import BarList from '@/components/dashboard/BarList.vue';
import ProgressRing from '@/components/dashboard/ProgressRing.vue';
import { formatDate, relativeDate } from '@/lib/format';
import { dot } from '@/lib/chartColors';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowUpRight,
    Briefcase,
    CalendarClock,
    CheckCircle2,
    Clock,
    Gavel,
    History,
    ListTodo,
    PencilLine,
    PieChart,
    Plus,
    Scale,
    Target,
    Trash2,
    TrendingUp,
    UserPlus,
    Users,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Kpi {
    value: number;
    delta: number | null;
    sub: string;
}

const props = defineProps<{
    userName: string;
    kpis: Record<string, Kpi>;
    caseTrend: Array<{ label: string; cases: number; hearings: number }>;
    casesByStatus: Array<{ label: string; value: number; color: string }>;
    casesByType: Array<{ label: string; value: number }>;
    casesByPriority: Array<{ label: string; value: number; color: string }>;
    taskStats: { total: number; done: number; completion: number };
    winRate: { value: number; resolved: number; favorable: number };
    teamWorkload: Array<{ name: string; initials: string; tasks: number; cases: number; load: number }>;
    recentActivity: Array<{ id: number; event: string | null; subject: string; causer: string | null; when: string | null }>;
    upcomingHearings: { data: any[] };
    myTasks: { data: any[] };
    recentCases: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const greeting = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
});
const today = computed(() =>
    new Date().toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
);

const kpiCards = [
    { key: 'total_cases', label: 'Total Cases', icon: Briefcase, accent: 'indigo', invert: false },
    { key: 'active_cases', label: 'Active Cases', icon: Gavel, accent: 'violet', invert: false },
    { key: 'clients', label: 'Clients', icon: Users, accent: 'sky', invert: false },
    { key: 'open_tasks', label: 'Open Tasks', icon: ListTodo, accent: 'amber', invert: false },
    { key: 'hearings_week', label: 'Hearings (7d)', icon: CalendarClock, accent: 'emerald', invert: false },
    { key: 'overdue_tasks', label: 'Overdue', icon: AlertTriangle, accent: 'rose', invert: true },
] as const;

const trendSeries = [
    { key: 'cases', name: 'New cases', color: 'indigo' },
    { key: 'hearings', name: 'Hearings', color: 'emerald' },
];

const typeItems = computed(() => props.casesByType.map((t) => ({ label: t.label, value: t.value })));
const workloadItems = computed(() =>
    props.teamWorkload.map((m) => ({
        label: m.name,
        value: m.load,
        initials: m.initials,
        sub: `${m.cases} cases · ${m.tasks} tasks`,
    })),
);

const priorityTotal = computed(() => Math.max(1, props.casesByPriority.reduce((s, p) => s + p.value, 0)));
const priorityShown = computed(() => props.casesByPriority.filter((p) => p.value > 0));

function activityIcon(event: string | null) {
    return { created: Plus, updated: PencilLine, deleted: Trash2 }[event ?? ''] ?? Activity;
}
function activityTone(event: string | null): string {
    return (
        { created: 'text-emerald-600 bg-emerald-50', updated: 'text-blue-600 bg-blue-50', deleted: 'text-rose-600 bg-rose-50' }[
            event ?? ''
        ] ?? 'text-slate-500 bg-slate-100'
    );
}
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6">
            <!-- Greeting + quick actions -->
            <PageHeader :title="`${greeting}, ${userName}`" :description="`Here's your firm at a glance — ${today}.`">
                <template #actions>
                    <Link
                        href="/clients/create"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        <UserPlus class="size-4" />
                        Add client
                    </Link>
                    <Link
                        href="/cases/create"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    >
                        <Plus class="size-4" />
                        New case
                    </Link>
                </template>
            </PageHeader>

            <!-- KPI row -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                <KpiCard
                    v-for="(card, i) in kpiCards"
                    :key="card.key"
                    :label="card.label"
                    :icon="card.icon"
                    :accent="card.accent"
                    :value="kpis[card.key].value"
                    :delta="kpis[card.key].delta"
                    :sub="kpis[card.key].sub"
                    :invert="card.invert"
                    :delay="i * 70"
                />
            </div>

            <!-- Trends + distribution -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <DashboardCard
                    class="lg:col-span-2"
                    title="Caseload trend"
                    subtitle="New cases vs hearings · last 6 months"
                    :icon="TrendingUp"
                    accent="indigo"
                    :delay="80"
                >
                    <template #action>
                        <div class="flex items-center gap-3 text-xs text-slate-500">
                            <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-indigo-500" />New cases</span>
                            <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500" />Hearings</span>
                        </div>
                    </template>
                    <AreaChart :data="caseTrend" :series="trendSeries" :height="250" />
                </DashboardCard>

                <DashboardCard title="Case status" subtitle="Distribution across the pipeline" :icon="PieChart" accent="violet" :delay="160">
                    <DonutChart :data="casesByStatus" center-label="Cases" />

                    <div v-if="priorityShown.length" class="mt-5 border-t border-slate-100 pt-4">
                        <p class="mb-2 text-xs font-medium text-slate-500">Cases by priority</p>
                        <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div
                                v-for="p in priorityShown"
                                :key="p.label"
                                :class="dot(p.color)"
                                :style="{ width: `${(p.value / priorityTotal) * 100}%` }"
                                class="transition-all duration-700"
                            />
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                            <span v-for="p in priorityShown" :key="p.label" class="flex items-center gap-1.5 text-xs text-slate-500">
                                <span :class="dot(p.color)" class="size-2 rounded-full" />
                                {{ p.label }} <span class="font-semibold text-slate-700">{{ p.value }}</span>
                            </span>
                        </div>
                    </div>
                </DashboardCard>
            </div>

            <!-- Breakdowns -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <DashboardCard title="Practice areas" subtitle="Cases by matter type" :icon="Scale" accent="blue" :delay="80">
                    <BarList v-if="typeItems.length" :items="typeItems" show-percent />
                    <EmptyState v-else title="No cases yet" :icon="Scale" class="!py-8" />
                </DashboardCard>

                <DashboardCard title="Team workload" subtitle="Active cases + open tasks" :icon="UsersRound" accent="emerald" to="/team" action-label="Team" :delay="160">
                    <BarList v-if="workloadItems.length" :items="workloadItems" />
                    <EmptyState v-else title="No members" :icon="UsersRound" class="!py-8" />
                </DashboardCard>

                <DashboardCard title="Performance" subtitle="Throughput & outcomes" :icon="Target" accent="amber" :delay="240">
                    <div class="grid grid-cols-2 gap-2 py-2">
                        <div class="flex flex-col items-center">
                            <ProgressRing :value="taskStats.completion" label="Task completion" color="indigo" />
                            <p class="mt-0.5 text-xs text-slate-400">{{ taskStats.done }}/{{ taskStats.total }} done</p>
                        </div>
                        <div class="flex flex-col items-center">
                            <ProgressRing :value="winRate.value" label="Win rate" color="emerald" />
                            <p class="mt-0.5 text-xs text-slate-400">{{ winRate.favorable }}/{{ winRate.resolved }} favourable</p>
                        </div>
                    </div>
                </DashboardCard>
            </div>

            <!-- Operational -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <DashboardCard title="Upcoming hearings" subtitle="Next on the calendar" :icon="Gavel" accent="indigo" to="/hearings" :delay="80">
                    <ul v-if="upcomingHearings.data.length" class="space-y-2">
                        <li
                            v-for="h in upcomingHearings.data"
                            :key="h.id"
                            class="flex items-start gap-3 rounded-xl p-2 transition-colors hover:bg-slate-50"
                        >
                            <div class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100">
                                <span class="text-sm font-bold leading-none">{{ new Date(h.scheduled_at).getDate() }}</span>
                                <span class="text-[10px] uppercase">{{ new Date(h.scheduled_at).toLocaleString('en', { month: 'short' }) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ h.case?.title ?? 'Hearing' }}</p>
                                <p class="truncate text-xs text-slate-500">{{ h.purpose }}</p>
                                <p class="mt-0.5 flex items-center gap-1 text-xs text-indigo-600">
                                    <Clock class="size-3" />{{ formatDate(h.scheduled_at, true) }}
                                </p>
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else title="No hearings" description="Nothing scheduled soon." :icon="Gavel" class="!py-8" />
                </DashboardCard>

                <DashboardCard title="My open tasks" subtitle="Assigned to you" :icon="ListTodo" accent="amber" to="/tasks" action-label="Board" :delay="160">
                    <ul v-if="myTasks.data.length" class="divide-y divide-slate-100">
                        <li v-for="t in myTasks.data" :key="t.id" class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ t.title }}</p>
                                <p class="flex items-center gap-1 truncate text-xs" :class="t.is_overdue ? 'text-rose-600' : 'text-slate-500'">
                                    <Clock v-if="t.due_at" class="size-3" />
                                    <span>{{ t.due_at ? relativeDate(t.due_at) : (t.case?.case_number ?? 'General') }}</span>
                                </p>
                            </div>
                            <StatusBadge v-if="t.priority" :label="t.priority.label" :color="t.priority.color" />
                        </li>
                    </ul>
                    <EmptyState v-else title="All clear" description="You have no open tasks." :icon="CheckCircle2" class="!py-8" />
                </DashboardCard>

                <DashboardCard title="Recent activity" subtitle="Latest firm actions" :icon="History" accent="rose" to="/activity" :delay="240">
                    <ul v-if="recentActivity.length" class="space-y-1">
                        <li v-for="a in recentActivity" :key="a.id" class="flex items-center gap-3 rounded-lg px-1 py-1.5">
                            <span :class="['flex size-8 shrink-0 items-center justify-center rounded-full', activityTone(a.event)]">
                                <component :is="activityIcon(a.event)" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-slate-700">
                                    <span class="font-medium text-slate-900">{{ a.causer ?? 'Someone' }}</span>
                                    {{ a.event ?? 'changed' }} a <span class="font-medium">{{ a.subject }}</span>
                                </p>
                            </div>
                            <span class="shrink-0 text-xs text-slate-400">{{ relativeDate(a.when) }}</span>
                        </li>
                    </ul>
                    <EmptyState v-else title="No activity" description="Actions will show up here." :icon="Activity" class="!py-8" />
                </DashboardCard>
            </div>

            <!-- Recent cases -->
            <DashboardCard title="Recent cases" subtitle="Most recently updated matters" :icon="Briefcase" accent="indigo" to="/cases" :delay="80">
                <div v-if="recentCases.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="c in recentCases"
                        :key="c.id"
                        :href="`/cases/${c.id}`"
                        class="group flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="truncate text-sm font-semibold text-slate-800 group-hover:text-indigo-700">{{ c.title }}</p>
                            <ArrowUpRight class="size-4 shrink-0 text-slate-300 transition-colors group-hover:text-indigo-500" />
                        </div>
                        <p class="truncate text-xs text-slate-500">{{ c.case_number }} · {{ c.client ?? 'No client' }}</p>
                        <div class="mt-1 flex items-center gap-2">
                            <StatusBadge :label="c.status.label" :color="c.status.color" />
                            <StatusBadge :label="c.priority.label" :color="c.priority.color" :dot="false" />
                            <span class="ml-auto text-[11px] text-slate-400">{{ relativeDate(c.updated_at) }}</span>
                        </div>
                    </Link>
                </div>
                <EmptyState v-else title="No cases yet" description="Create your first case to get started." :icon="Briefcase" class="!py-8" />
            </DashboardCard>
        </div>
    </AppLayout>
</template>
