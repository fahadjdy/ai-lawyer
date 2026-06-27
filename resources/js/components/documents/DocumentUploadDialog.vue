<script setup lang="ts">
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToastStore } from '@/stores/toasts';
import { useForm } from '@inertiajs/vue3';
import { FileText, UploadCloud, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
        presetFiles?: File[] | null;
        options: { cases: { id: number; name: string }[]; folders: { id: number; uuid: string; name: string }[] };
    }>(),
    { mode: 'create', target: null, presetFiles: null },
);

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const toasts = useToastStore();
const fileInput = ref<HTMLInputElement | null>(null);

// `selected` is the UI source of truth; the right field is synced onto the
// form (single `file` for a version, `files[]` for a create) before posting.
const selected = ref<File[]>([]);
const multiple = computed(() => props.mode === 'create');

const form = useForm<{ file: File | null; files: File[]; name: string; case_id: number | null; folder_id: number | null }>({
    file: null,
    files: [],
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
        selected.value = [];
        if (props.presetFiles?.length) setFiles(props.presetFiles);
    },
);

function setFiles(files: File[]) {
    selected.value = multiple.value ? files : files.slice(0, 1);
    form.files = selected.value;
    form.file = selected.value[0] ?? null;
    if (props.mode === 'create' && !form.name && selected.value.length === 1) {
        form.name = selected.value[0].name.replace(/\.[^/.]+$/, '');
    }
}

function onFileChange(e: Event) {
    setFiles(Array.from((e.target as HTMLInputElement).files ?? []));
}

function removeFile(index: number) {
    const next = selected.value.slice();
    next.splice(index, 1);
    setFiles(next);
    if (fileInput.value) fileInput.value.value = '';
}

// Surface whichever validation key the server used: `files` (array rule),
// `file` (version mode), or the first per-file error `files.0`.
const fileError = computed(() => {
    const e = form.errors as Record<string, string>;
    return e.files || e.file || e['files.0'] || Object.entries(e).find(([k]) => k.startsWith('files.'))?.[1];
});

const close = () => emit('update:open', false);

function submit() {
    const opts = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => close(),
        onError: () => toasts.error('Please correct the highlighted fields.'),
    };

    if (props.mode === 'version' && props.target) {
        // Versions take a single `file`; drop the unused array so we don't upload twice.
        form.transform(({ files, ...rest }) => rest).post(`/documents/${props.target.id}/versions`, opts);
    } else {
        form.transform(({ file, ...rest }) => rest).post('/documents', opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ mode === 'version' ? 'Upload new version' : 'Upload document' }}</DialogTitle>
                <DialogDescription>
                    {{ mode === 'version' ? `A new version of “${target?.name}” (currently v${target?.version}).` : 'Add one or more files to your secure repository.' }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- File picker / drop target -->
                <div>
                    <input ref="fileInput" type="file" class="hidden" :multiple="multiple" @change="onFileChange" />
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-left text-sm text-slate-500 transition hover:border-indigo-300 hover:bg-indigo-50/40"
                        @click="fileInput?.click()"
                    >
                        <UploadCloud class="size-5 shrink-0 text-slate-400" />
                        <span v-if="selected.length === 0">Click to browse — PDF, DOCX, images, audio &amp; video (max 50&nbsp;MB{{ multiple ? ' each, up to 20 files' : '' }}).</span>
                        <span v-else-if="selected.length === 1" class="min-w-0 truncate font-medium text-slate-700">{{ selected[0].name }}</span>
                        <span v-else class="font-medium text-slate-700">{{ selected.length }} files selected — click to change</span>
                    </button>

                    <!-- Selected file list (multi-upload) -->
                    <ul v-if="selected.length > 1" class="mt-2 space-y-1">
                        <li v-for="(f, i) in selected" :key="f.name + i" class="flex items-center gap-2 rounded-md bg-slate-50 px-2.5 py-1.5 text-xs text-slate-600">
                            <FileText class="size-3.5 shrink-0 text-slate-400" />
                            <span class="min-w-0 flex-1 truncate">{{ f.name }}</span>
                            <button type="button" class="text-slate-400 hover:text-rose-600" aria-label="Remove file" @click="removeFile(i)"><X class="size-3.5" /></button>
                        </li>
                    </ul>

                    <InputError :message="fileError" />
                    <p v-if="form.progress" class="mt-1.5 text-xs text-slate-400">Uploading… {{ Math.round(form.progress.percentage ?? 0) }}%</p>
                </div>

                <template v-if="mode === 'create'">
                    <div v-if="selected.length <= 1">
                        <Label for="d_name">Display name</Label>
                        <Input id="d_name" v-model="form.name" placeholder="Defaults to the file name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <p v-else class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">Each file becomes its own document, named after the file.</p>

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
                    <Button type="submit" :disabled="form.processing || selected.length === 0">
                        {{ mode === 'version' ? 'Upload version' : selected.length > 1 ? `Upload ${selected.length} files` : 'Upload' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
