<script setup lang="ts">
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToastStore } from '@/stores/toasts';
import type { EnumOption } from '@/types';
import { evidenceSchema, type EvidenceFormValues } from '@/validation/evidenceSchema';
import { router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { ref, watch } from 'vue';

interface EvidenceRow {
    id: string;
    reference_number: string | null;
    title: string;
    description: string | null;
    type: EnumOption | null;
    status: EnumOption | null;
    collected_at: string | null;
    collected_by: string | null;
    case_id: number | null;
    document_id: number | null;
}

const props = defineProps<{
    open: boolean;
    evidence?: EvidenceRow | null;
    options: {
        statuses: EnumOption[];
        types: EnumOption[];
        cases: { id: number; name: string }[];
        documents: { id: number; name: string }[];
    };
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const toasts = useToastStore();
const processing = ref(false);

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm<EvidenceFormValues>({
    validationSchema: toTypedSchema(evidenceSchema),
    initialValues: { type: 'document', status: 'collected', title: '' } as EvidenceFormValues,
});

const [caseId] = defineField('case_id');
const [documentId] = defineField('document_id');
const [referenceNumber] = defineField('reference_number');
const [title] = defineField('title');
const [description] = defineField('description');
const [type] = defineField('type');
const [status] = defineField('status');
const [collectedAt] = defineField('collected_at');
const [collectedBy] = defineField('collected_by');

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        const e = props.evidence;
        resetForm({
            values: {
                case_id: (e?.case_id ?? null) as unknown as number,
                document_id: e?.document_id ?? null,
                reference_number: e?.reference_number ?? '',
                title: e?.title ?? '',
                description: e?.description ?? '',
                type: e?.type?.value ?? 'document',
                status: e?.status?.value ?? 'collected',
                collected_at: e?.collected_at ? e.collected_at.slice(0, 16) : '',
                collected_by: e?.collected_by ?? '',
            },
        });
    },
    { immediate: true },
);

const close = () => emit('update:open', false);

const submit = handleSubmit((values) => {
    processing.value = true;
    const opts = {
        preserveScroll: true,
        onError: (e: Record<string, string>) => {
            setErrors(e);
            toasts.error('Please correct the highlighted fields.');
        },
        onSuccess: close,
        onFinish: () => (processing.value = false),
    };
    if (props.evidence) {
        router.put(`/evidence/${props.evidence.id}`, values as Record<string, unknown>, opts);
    } else {
        router.post('/evidence', values as Record<string, unknown>, opts);
    }
});

const inputClass =
    'h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400';
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ evidence ? 'Edit evidence' : 'Record evidence' }}</DialogTitle>
                <DialogDescription>{{ evidence ? 'Update the exhibit details.' : 'Add an exhibit to a case.' }}</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <Label for="ev_title">Title</Label>
                    <Input id="ev_title" v-model="title" placeholder="e.g. CCTV footage – main gate" />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="ev_case">Case</Label>
                        <SearchableSelect
                            id="ev_case"
                            v-model="caseId"
                            :options="options.cases.map((c) => ({ value: c.id, label: c.name }))"
                            placeholder="— Select a case —"
                        />
                        <InputError :message="errors.case_id" />
                    </div>
                    <div>
                        <Label for="ev_ref">Reference / exhibit #</Label>
                        <Input id="ev_ref" v-model="referenceNumber" placeholder="e.g. Exhibit A" />
                        <InputError :message="errors.reference_number" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="ev_type">Type</Label>
                        <select id="ev_type" v-model="type" :class="inputClass">
                            <option v-for="t in options.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                    <div>
                        <Label for="ev_status">Status</Label>
                        <select id="ev_status" v-model="status" :class="inputClass">
                            <option v-for="s in options.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                </div>

                <div>
                    <Label for="ev_doc">Linked document <span class="text-slate-400">(optional)</span></Label>
                    <SearchableSelect
                        id="ev_doc"
                        v-model="documentId"
                        :options="options.documents.map((d) => ({ value: d.id, label: d.name }))"
                        placeholder="— None —"
                        clearable
                    />
                    <InputError :message="errors.document_id" />
                </div>

                <div>
                    <Label for="ev_desc">Description</Label>
                    <textarea id="ev_desc" v-model="description" rows="3" :class="inputClass" class="!h-auto py-2" />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="ev_when">Collected at</Label>
                        <input id="ev_when" v-model="collectedAt" type="datetime-local" :class="inputClass" />
                        <InputError :message="errors.collected_at" />
                    </div>
                    <div>
                        <Label for="ev_by">Collected by</Label>
                        <Input id="ev_by" v-model="collectedBy" placeholder="Officer / handler name" />
                        <InputError :message="errors.collected_by" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="processing">{{ evidence ? 'Save changes' : 'Record evidence' }}</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
