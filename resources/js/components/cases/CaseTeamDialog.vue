<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useToastStore } from '@/stores/toasts';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Lawyer {
    id: number;
    name: string;
    designation: string | null;
    initials: string;
}

const props = defineProps<{
    open: boolean;
    caseId: string;
    lawyers: Lawyer[];
    selected: number[];
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const toasts = useToastStore();
const chosen = ref<number[]>([]);
const processing = ref(false);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) chosen.value = [...props.selected];
    },
    { immediate: true },
);

function toggle(id: number) {
    chosen.value = chosen.value.includes(id) ? chosen.value.filter((x) => x !== id) : [...chosen.value, id];
}

const close = () => emit('update:open', false);

function submit() {
    processing.value = true;
    router.post(
        `/cases/${props.caseId}/assignees`,
        { assignees: chosen.value },
        {
            preserveScroll: true,
            onSuccess: close,
            onError: () => toasts.error('Could not update the legal team.'),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Legal team</DialogTitle>
                <DialogDescription>Choose the lawyers assigned to this case.</DialogDescription>
            </DialogHeader>

            <div class="space-y-1">
                <label
                    v-for="l in lawyers"
                    :key="l.id"
                    class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-100 p-2.5 hover:bg-slate-50"
                >
                    <input type="checkbox" :checked="chosen.includes(l.id)" class="rounded border-slate-300" @change="toggle(l.id)" />
                    <span class="flex size-7 items-center justify-center rounded-full bg-indigo-100 text-[11px] font-semibold text-indigo-700">{{ l.initials }}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-slate-800">{{ l.name }}</span>
                        <span v-if="l.designation" class="block truncate text-xs text-slate-500">{{ l.designation }}</span>
                    </span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <Button variant="outline" type="button" @click="close">Cancel</Button>
                <Button type="button" :disabled="processing" @click="submit">Save team</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
