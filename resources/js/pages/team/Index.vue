<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface Member {
    id: string;
    name: string;
    email: string;
    designation: string | null;
    initials: string;
    is_active: boolean;
    roles: string[];
    cases_count: number;
    tasks_count: number;
    last_login_at: string | null;
}

defineProps<{ members: Member[] }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Team', href: '/team' }];

const roleColor = (role: string) =>
    ({ firm_owner: 'violet', partner: 'blue', associate: 'emerald', paralegal: 'amber', clerk: 'slate' })[role] ?? 'slate';
const roleLabel = (role: string) => role.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
    <Head title="Team" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Team" description="Members of your firm and their roles." />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="m in members" :key="m.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="flex size-11 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">{{ m.initials }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900">{{ m.name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ m.designation ?? m.email }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <StatusBadge v-for="r in m.roles" :key="r" :label="roleLabel(r)" :color="roleColor(r)" :dot="false" />
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 text-center">
                        <div><p class="text-lg font-semibold text-slate-900">{{ m.cases_count }}</p><p class="text-xs text-slate-400">Cases</p></div>
                        <div><p class="text-lg font-semibold text-slate-900">{{ m.tasks_count }}</p><p class="text-xs text-slate-400">Tasks</p></div>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">Last login: {{ formatDate(m.last_login_at, true) }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
