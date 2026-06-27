<script setup lang="ts">
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToastStore } from '@/stores/toasts';
import { useForm } from '@inertiajs/vue3';
import { FileText, UploadCloud } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface DocTarget {
    id: string;
    name: string;
    version: number;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        mode?: 'create' | 'version';
        target?: DocTarget | null;
        presetFile?: File | null;
        options: { cases: { id: number; name: string }[]; folders: { id: number; uuid: string; name: string }[] };
    }>(),
    { mode: 'create', target: null, presetFile: null },
);

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const toasts = useToastStore();
const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{ file: File | null; name: string; case_id: number | null; folder_id: number | null }>({
    file: null,
    name: '',
    case_id: null,
    folder_id: null,
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        form.clearErrors();
        form.reset();
        if (props.presetFile) setFile(props.presetFile);
    },
);

function setFile(file: File | null) {
    form.file = file;
    if (file && !form.name && props.mode === 'create') {
        form.name = file.name.replace(/\.[^/.]+$/, '');
    }
}

function onFileChange(e: Event) {
    setFile((e.target as HTMLInputElement).files?.[0] ?? null);
}

const close = () => emit('update:open', false);

function submit() {
    const opts = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => close(),
        onError: () => toasts.error('Please correct the highlighted fields.'),
    };

    if (props.mode === 'version' && props.target) {
        form.post(`/documents/${props.target.id}/versions`, opts);
    } else {
        form.post('/documents', opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ mode === 'version' ? 'Upload new version' : 'Upload document' }}</DialogTitle>
                <DialogDescription>
                    {{ mode === 'version' ? `A new version of “${target?.name}” (currently v${target?.version}).` : 'Add a file to your secure repository.' }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- File picker / drop target -->
                <div>
                    <input ref="fileInput" type="file" class="hidden" @change="onFileChange" />
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-left text-sm text-slate-500 transition hover:border-indigo-300 hover:bg-indigo-50/40"
                        @click="fileInput?.click()"
                    >
                        <component :is="form.file ? FileText : UploadCloud" class="size-5 shrink-0 text-slate-400" />
                        <span v-if="form.file" class="min-w-0 truncate font-medium text-slate-700">{{ form.file.name }}</span>
                        <span v-else>Click to browse — PDF, DOCX, images, audio &amp; video (max 50&nbsp;MB).</span>
                    </button>
                    <InputError :message="form.errors.file" />
                    <p v-if="form.progress" class="mt-1.5 text-xs text-slate-400">Uploading… {{ Math.round(form.progress.percentage ?? 0) }}%</p>
                </div>

                <template v-if="mode === 'create'">
                    <div>
                        <Label for="d_name">Display name</Label>
                        <Input id="d_name" v-model="form.name" placeholder="Defaults to the file name" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="d_case">Case <span class="text-slate-400">(optional)</span></Label>
                            <SearchableSelect
                                id="d_case"
                                v-model="form.case_id"
                                :options="options.cases.map((c) => ({ value: c.id, label: c.name }))"
                                placeholder="— No case —"
                                clearable
                            />
                            <InputError :message="form.errors.case_id" />
                        </div>
                        <div>
                            <Label for="d_folder">Folder <span class="text-slate-400">(optional)</span></Label>
                            <SearchableSelect
                                id="d_folder"
                                v-model="form.folder_id"
                                :options="options.folders.map((f) => ({ value: f.id, label: f.name }))"
                                placeholder="— No folder —"
                                clearable
                            />
                            <InputError :message="form.errors.folder_id" />
                        </div>
                    </div>
                </template>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="form.processing || !form.file">
                        {{ mode === 'version' ? 'Upload version' : 'Upload' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
