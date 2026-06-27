<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToastStore } from '@/stores/toasts';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{ open: boolean; evidenceId: string }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const toasts = useToastStore();

const form = useForm<{ action: string; handler: string; note: string; occurred_at: string }>({
    action: '',
    handler: '',
    note: '',
    occurred_at: '',
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        form.clearErrors();
        form.reset();
    },
);

const close = () => emit('update:open', false);

function submit() {
    form.post(`/evidence/${props.evidenceId}/custody`, {
        preserveScroll: true,
        onSuccess: () => close(),
        onError: () => toasts.error('Please correct the highlighted fields.'),
    });
}

const inputClass =
    'h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400';
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add custody entry</DialogTitle>
                <DialogDescription>Record a hand-off in the chain of custody.</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="c_action">Action</Label>
                        <Input id="c_action" v-model="form.action" placeholder="e.g. Transferred" />
                        <InputError :message="form.errors.action" />
                    </div>
                    <div>
                        <Label for="c_when">When</Label>
                        <input id="c_when" v-model="form.occurred_at" type="datetime-local" :class="inputClass" />
                        <InputError :message="form.errors.occurred_at" />
                    </div>
                </div>
                <div>
                    <Label for="c_handler">Handler</Label>
                    <Input id="c_handler" v-model="form.handler" placeholder="Who took custody" />
                    <InputError :message="form.errors.handler" />
                </div>
                <div>
                    <Label for="c_note">Note <span class="text-slate-400">(optional)</span></Label>
                    <textarea id="c_note" v-model="form.note" rows="2" :class="inputClass" class="!h-auto py-2" />
                    <InputError :message="form.errors.note" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">Add entry</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
