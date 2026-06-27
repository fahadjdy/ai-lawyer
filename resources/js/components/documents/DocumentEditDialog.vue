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

interface DocRow {
    id: string;
    name: string;
    case_id: number | null;
    folder_id: number | null;
}

const props = defineProps<{
    open: boolean;
    document: DocRow | null;
    options: { cases: { id: number; name: string }[]; folders: { id: number; uuid: string; name: string }[] };
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const toasts = useToastStore();

const form = useForm<{ name: string; case_id: number | null; folder_id: number | null }>({
    name: '',
    case_id: null,
    folder_id: null,
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen || !props.document) return;
        form.clearErrors();
        form.defaults({
            name: props.document.name,
            case_id: props.document.case_id,
            folder_id: props.document.folder_id,
        });
        form.reset();
    },
);

const close = () => emit('update:open', false);

function submit() {
    if (!props.document) return;
    form.put(`/documents/${props.document.id}`, {
        preserveScroll: true,
        onSuccess: () => close(),
        onError: () => toasts.error('Please correct the highlighted fields.'),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Edit document</DialogTitle>
                <DialogDescription>Rename or move this document to another case or folder.</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <Label for="e_name">Name</Label>
                    <Input id="e_name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="e_case">Case</Label>
                        <SearchableSelect
                            id="e_case"
                            v-model="form.case_id"
                            :options="options.cases.map((c) => ({ value: c.id, label: c.name }))"
                            placeholder="— No case —"
                            clearable
                        />
                        <InputError :message="form.errors.case_id" />
                    </div>
                    <div>
                        <Label for="e_folder">Folder</Label>
                        <SearchableSelect
                            id="e_folder"
                            v-model="form.folder_id"
                            :options="options.folders.map((f) => ({ value: f.id, label: f.name }))"
                            placeholder="— No folder —"
                            clearable
                        />
                        <InputError :message="form.errors.folder_id" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">Save changes</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
