<script setup lang="ts">
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import DashboardCard from '@/components/dashboard/DashboardCard.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDate } from '@/lib/format';
import { router } from '@inertiajs/vue3';
import { MoreHorizontal, Pin, Plus, StickyNote } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Note {
    id: string;
    body: string;
    is_pinned: boolean;
    author: { name: string; initials: string } | null;
    created_at: string;
}

const props = defineProps<{ caseId: string; notes: Note[]; canManage: boolean }>();

// Pinned first, then newest.
const sorted = computed(() =>
    [...props.notes].sort((a, b) => Number(b.is_pinned) - Number(a.is_pinned) || +new Date(b.created_at) - +new Date(a.created_at)),
);

const composing = ref(false);
const editingId = ref<string | null>(null);
const body = ref('');
const pinned = ref(false);
const processing = ref(false);

function startAdd() {
    editingId.value = null;
    body.value = '';
    pinned.value = false;
    composing.value = true;
}
function startEdit(n: Note) {
    editingId.value = n.id;
    body.value = n.body;
    pinned.value = n.is_pinned;
    composing.value = true;
}
function cancel() {
    composing.value = false;
    body.value = '';
    editingId.value = null;
}
function save() {
    if (!body.value.trim()) return;
    processing.value = true;
    const payload = { body: body.value, is_pinned: pinned.value };
    const opts = { preserveScroll: true, onSuccess: cancel, onFinish: () => (processing.value = false) };
    if (editingId.value) {
        router.put(`/cases/${props.caseId}/notes/${editingId.value}`, payload, opts);
    } else {
        router.post(`/cases/${props.caseId}/notes`, payload, opts);
    }
}
function togglePin(n: Note) {
    router.put(`/cases/${props.caseId}/notes/${n.id}`, { body: n.body, is_pinned: !n.is_pinned }, { preserveScroll: true });
}

const confirmOpen = ref(false);
const deleting = ref<Note | null>(null);
function askDelete(n: Note) {
    deleting.value = n;
    confirmOpen.value = true;
}
function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/cases/${props.caseId}/notes/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false;
            deleting.value = null;
        },
    });
}

const inputClass =
    'w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400';
</script>

<template>
    <DashboardCard title="Notes" subtitle="Internal annotations" :icon="StickyNote" accent="amber" :delay="280">
        <template v-if="canManage" #action>
            <Button size="sm" variant="outline" @click="composing ? cancel() : startAdd()"><Plus class="size-4" /> Add note</Button>
        </template>

        <!-- Composer -->
        <div v-if="composing" class="mb-4 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
            <textarea v-model="body" rows="3" :class="inputClass" placeholder="Write a note…" />
            <div class="mt-2 flex items-center justify-between">
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                    <input v-model="pinned" type="checkbox" class="rounded border-slate-300" /> Pin to top
                </label>
                <div class="flex gap-2">
                    <Button size="sm" variant="outline" type="button" @click="cancel">Cancel</Button>
                    <Button size="sm" type="button" :disabled="processing || !body.trim()" @click="save">{{ editingId ? 'Save' : 'Add' }}</Button>
                </div>
            </div>
        </div>

        <ul v-if="sorted.length" class="space-y-3">
            <li v-for="n in sorted" :key="n.id" class="rounded-lg border border-slate-100 p-3" :class="n.is_pinned ? 'bg-amber-50/50' : ''">
                <div class="flex items-start justify-between gap-2">
                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ n.body }}</p>
                    <DropdownMenu v-if="canManage">
                        <DropdownMenuTrigger as-child>
                            <button class="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Note actions">
                                <MoreHorizontal class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-36">
                            <DropdownMenuItem @select="startEdit(n)">Edit</DropdownMenuItem>
                            <DropdownMenuItem @select="togglePin(n)">{{ n.is_pinned ? 'Unpin' : 'Pin' }}</DropdownMenuItem>
                            <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="askDelete(n)">Delete</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <p class="mt-1.5 flex items-center gap-1.5 text-[11px] text-slate-400">
                    <Pin v-if="n.is_pinned" class="size-3 text-amber-500" />
                    {{ n.author?.name ?? 'Someone' }} · {{ formatDate(n.created_at, true) }}
                </p>
            </li>
        </ul>
        <EmptyState v-else :icon="StickyNote" title="No notes" description="Capture internal notes about this matter." />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete note?"
            description="This note will be permanently removed."
            confirm-label="Delete note"
            @confirm="confirmDelete"
        />
    </DashboardCard>
</template>
