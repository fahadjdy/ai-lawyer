<script setup lang="ts">
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToastStore } from '@/stores/toasts';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    open: boolean;
    options: { cases: { id: number; name: string }[] };
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const toasts = useToastStore();

const form = useForm<{ name: string; case_id: number | null }>({ name: '', case_id: null });

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
    form.post('/document-folders', {
        preserveScroll: true,
        onSuccess: () => close(),
        onError: () => toasts.error('Please correct the highlighted fields.'),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>New folder</DialogTitle>
                <DialogDescription>Organise documents into a named folder.</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <Label for="f_name">Folder name</Label>
                    <Input id="f_name" v-model="form.name" placeholder="e.g. Pleadings" />
                    <InputError :message="form.errors.name" />
                </div>
                <div>
                    <Label for="f_case">Case <span class="text-slate-400">(optional)</span></Label>
                    <SearchableSelect
                        id="f_case"
                        v-model="form.case_id"
                        :options="options.cases.map((c) => ({ value: c.id, label: c.name }))"
                        placeholder="— No case —"
                        clearable
                    />
                    <InputError :message="form.errors.case_id" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">Create folder</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
