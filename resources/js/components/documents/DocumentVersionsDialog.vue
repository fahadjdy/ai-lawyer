<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatDate } from '@/lib/format';
import { Download, History } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface DocTarget {
    id: string;
    name: string;
}

interface DocVersion {
    id: string;
    version: number;
    is_latest: boolean;
    size: string;
    original_name: string;
    uploaded_by: string | null;
    created_at: string | null;
}

const props = defineProps<{ open: boolean; target?: DocTarget | null }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const loading = ref(false);
const versions = ref<DocVersion[]>([]);

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen || !props.target) return;
        loading.value = true;
        versions.value = [];
        try {
            const res = await fetch(`/documents/${props.target.id}/versions`, { headers: { Accept: 'application/json' } });
            if (res.ok) versions.value = (await res.json()).versions ?? [];
        } finally {
            loading.value = false;
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2"><History class="size-4 text-slate-400" /> Version history</DialogTitle>
                <DialogDescription>All saved versions of “{{ target?.name }}”, newest first.</DialogDescription>
            </DialogHeader>

            <p v-if="loading" class="py-6 text-center text-sm text-slate-400">Loading…</p>
            <p v-else-if="versions.length === 0" class="py-6 text-center text-sm text-slate-400">No versions found.</p>

            <ul v-else class="max-h-80 space-y-2 overflow-y-auto">
                <li
                    v-for="v in versions"
                    :key="v.id"
                    class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm"
                    :class="v.is_latest ? 'bg-indigo-50/50' : 'bg-white'"
                >
                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">v{{ v.version }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-medium text-slate-800">
                            <span class="truncate">{{ v.original_name }}</span>
                            <span v-if="v.is_latest" class="shrink-0 rounded-full bg-indigo-100 px-1.5 text-[10px] font-medium text-indigo-700">current</span>
                        </p>
                        <p class="text-xs text-slate-400">
                            {{ v.size }}<span v-if="v.uploaded_by"> · {{ v.uploaded_by }}</span><span v-if="v.created_at"> · {{ formatDate(v.created_at) }}</span>
                        </p>
                    </div>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="`/documents/${v.id}/download`"><Download class="size-3.5" /> Download</a>
                    </Button>
                </li>
            </ul>

            <div class="flex justify-end pt-1">
                <Button variant="outline" @click="emit('update:open', false)">Close</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
