<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, ListChecks, Pencil, Scale, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ case: { data: any } }>();
const { can } = usePermissions();

const c = computed(() => props.case.data);
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Cases', href: '/cases' },
    { title: c.value.case_number, href: `/cases/${c.value.id}` },
]);

const destroy = () => {
    if (confirm('Archive this case? It can be restored by an administrator.')) {
        router.delete(`/cases/${c.value.id}`);
    }
};
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
    </AppLayout>
</template>
