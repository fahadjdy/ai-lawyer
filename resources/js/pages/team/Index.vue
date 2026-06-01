<script setup lang="ts">
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import TeamMemberForm from '@/components/team/TeamMemberForm.vue';
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
import { Head, router } from '@inertiajs/vue3';
import { MoreHorizontal, Plus, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';

interface Member {
    id: string;
    name: string;
    email: string;
    designation: string | null;
    phone: string | null;
    initials: string;
    is_active: boolean;
    roles: string[];
    role: string | null;
    cases_count: number;
    tasks_count: number;
    last_login_at: string | null;
}

defineProps<{ members: Member[]; options: { roles: EnumOption[] }; can: { manage: boolean } }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Team', href: '/team' }];

const roleColor = (role: string) =>
    ({ firm_owner: 'violet', partner: 'blue', associate: 'emerald', paralegal: 'amber', clerk: 'slate' })[role] ?? 'slate';
const roleLabel = (role: string) => role.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());

// ---- Add / edit ----
const formOpen = ref(false);
const editing = ref<Member | null>(null);
function openAdd() {
    editing.value = null;
    formOpen.value = true;
}
function openEdit(m: Member) {
    editing.value = m;
    formOpen.value = true;
}

function toggleActive(m: Member) {
    router.put(`/team/${m.id}`, { name: m.name, designation: m.designation, phone: m.phone, role: m.role, is_active: !m.is_active }, { preserveScroll: true });
}

// ---- Remove ----
const confirmOpen = ref(false);
const deleting = ref<Member | null>(null);
function askDelete(m: Member) {
    deleting.value = m;
    confirmOpen.value = true;
}
function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/team/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false;
            deleting.value = null;
        },
    });
}
</script>

<template>
    <Head title="Team" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Team" description="Members of your firm and their roles.">
                <template v-if="can.manage" #actions>
                    <Button @click="openAdd"><UserPlus class="size-4" /> Add member</Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="m in members"
                    :key="m.id"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition"
                    :class="m.is_active ? '' : 'opacity-60'"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <span class="flex size-11 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">{{ m.initials }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-medium text-slate-900">{{ m.name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ m.designation ?? m.email }}</p>
                            </div>
                        </div>
                        <DropdownMenu v-if="can.manage">
                            <DropdownMenuTrigger as-child>
                                <button class="rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Member actions">
                                    <MoreHorizontal class="size-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-40">
                                <DropdownMenuItem @select="openEdit(m)">Edit</DropdownMenuItem>
                                <DropdownMenuItem @select="toggleActive(m)">{{ m.is_active ? 'Deactivate' : 'Activate' }}</DropdownMenuItem>
                                <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="askDelete(m)">Remove</DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        <StatusBadge v-for="r in m.roles" :key="r" :label="roleLabel(r)" :color="roleColor(r)" :dot="false" />
                        <span v-if="!m.is_active" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">Inactive</span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 text-center">
                        <div><p class="text-lg font-semibold text-slate-900">{{ m.cases_count }}</p><p class="text-xs text-slate-400">Cases</p></div>
                        <div><p class="text-lg font-semibold text-slate-900">{{ m.tasks_count }}</p><p class="text-xs text-slate-400">Tasks</p></div>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">Last login: {{ formatDate(m.last_login_at, true) }}</p>
                </div>

                <!-- Add tile -->
                <button
                    v-if="can.manage"
                    type="button"
                    class="flex min-h-[3rem] flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-slate-200 p-5 text-sm font-medium text-slate-400 transition hover:border-indigo-300 hover:bg-white hover:text-indigo-600"
                    @click="openAdd"
                >
                    <Plus class="size-5" /> Add member
                </button>
            </div>
        </div>

        <TeamMemberForm v-model:open="formOpen" :roles="options.roles" :member="editing" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Remove member?"
            :description="`${deleting?.name ?? ''} will lose access to your firm.`"
            confirm-label="Remove member"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
