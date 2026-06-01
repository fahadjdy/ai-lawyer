<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Lock, Save } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface Perm {
    name: string;
    label: string;
}
interface Group {
    key: string;
    label: string;
    permissions: Perm[];
}
interface Role {
    id: number;
    name: string;
    label: string;
    color: string;
    locked: boolean;
    permissions: string[];
}

const props = defineProps<{ roles: Role[]; groups: Group[] }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Roles & Rights', href: '/roles' }];

const selectedId = ref<number>(props.roles[0]?.id);
const selectedRole = computed(() => props.roles.find((r) => r.id === selectedId.value)!);

// Editable copy of each role's permission set.
const local = reactive<Record<number, string[]>>(
    Object.fromEntries(props.roles.map((r) => [r.id, [...r.permissions]])),
);

const saving = ref(false);

const isChecked = (perm: string) => selectedRole.value.locked || (local[selectedId.value]?.includes(perm) ?? false);

function toggle(perm: string) {
    if (selectedRole.value.locked) return;
    const arr = local[selectedId.value];
    const i = arr.indexOf(perm);
    if (i === -1) arr.push(perm);
    else arr.splice(i, 1);
}

const groupChecked = (g: Group) => g.permissions.filter((p) => local[selectedId.value]?.includes(p.name)).length;
function toggleGroup(g: Group) {
    if (selectedRole.value.locked) return;
    const arr = local[selectedId.value];
    const allOn = g.permissions.every((p) => arr.includes(p.name));
    for (const p of g.permissions) {
        const i = arr.indexOf(p.name);
        if (allOn && i !== -1) arr.splice(i, 1);
        if (!allOn && i === -1) arr.push(p.name);
    }
}

const dirty = computed(() => {
    if (selectedRole.value.locked) return false;
    const orig = [...selectedRole.value.permissions].sort().join('|');
    const cur = [...(local[selectedId.value] ?? [])].sort().join('|');
    return orig !== cur;
});

const countFor = (role: Role) => (role.locked ? 'All' : (local[role.id]?.length ?? 0));

function save() {
    saving.value = true;
    router.put(
        `/roles/${selectedId.value}`,
        { permissions: local[selectedId.value] },
        { preserveScroll: true, preserveState: true, onFinish: () => (saving.value = false) },
    );
}
function reset() {
    local[selectedId.value] = [...selectedRole.value.permissions];
}
</script>

<template>
    <Head title="Roles & Rights" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Roles & Rights" description="Control which abilities each role grants across your firm." />

            <!-- Role selector -->
            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                <button
                    v-for="role in roles"
                    :key="role.id"
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                    :class="selectedId === role.id ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                    @click="selectedId = role.id"
                >
                    <StatusBadge :label="role.label" :color="role.color" :dot="true" />
                    <span class="text-xs text-slate-400">{{ countFor(role) }}</span>
                    <Lock v-if="role.locked" class="size-3 text-slate-400" />
                </button>
            </div>

            <!-- Selected role rights -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                    <div>
                        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900">
                            {{ selectedRole.label }}
                            <Lock v-if="selectedRole.locked" class="size-4 text-slate-400" />
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{
                                selectedRole.locked
                                    ? 'The Firm Owner always has full access — these rights cannot be changed.'
                                    : 'Tick the abilities this role should grant.'
                            }}
                        </p>
                    </div>
                    <div v-if="dirty" class="flex items-center gap-2">
                        <Button variant="outline" size="sm" type="button" @click="reset">Reset</Button>
                        <Button size="sm" type="button" :disabled="saving" @click="save"><Save class="size-4" /> Save changes</Button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="g in groups" :key="g.key" class="rounded-lg border border-slate-200">
                        <label
                            class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2"
                            :class="selectedRole.locked ? '' : 'cursor-pointer'"
                        >
                            <span class="text-sm font-semibold text-slate-800">{{ g.label }}</span>
                            <span class="flex items-center gap-2">
                                <span class="text-[11px] text-slate-400">{{ groupChecked(g) }}/{{ g.permissions.length }}</span>
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-400 disabled:opacity-60"
                                    :checked="selectedRole.locked || groupChecked(g) === g.permissions.length"
                                    :disabled="selectedRole.locked"
                                    @change="toggleGroup(g)"
                                />
                            </span>
                        </label>
                        <div class="space-y-1 p-3">
                            <label
                                v-for="p in g.permissions"
                                :key="p.name"
                                class="flex items-center gap-2 rounded px-1.5 py-1 text-sm text-slate-600"
                                :class="selectedRole.locked ? '' : 'cursor-pointer hover:bg-slate-50'"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-400 disabled:opacity-60"
                                    :checked="isChecked(p.name)"
                                    :disabled="selectedRole.locked"
                                    @change="toggle(p.name)"
                                />
                                {{ p.label }}
                            </label>
                        </div>
                    </div>
                </div>

                <div v-if="dirty" class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3">
                    <Button variant="outline" size="sm" type="button" @click="reset">Reset</Button>
                    <Button size="sm" type="button" :disabled="saving" @click="save"><Save class="size-4" /> Save changes</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
