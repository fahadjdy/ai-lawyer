<script setup lang="ts">
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToastStore } from '@/stores/toasts';
import { hearingSchema, type HearingFormValues } from '@/validation/hearingSchema';
import { router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { ref, watch } from 'vue';
import type { EnumOption } from '@/types';

interface HearingRow {
    id: string;
    case_id: number | null;
    scheduled_at: string | null;
    status: EnumOption | null;
    purpose: string | null;
    court_room: string | null;
    judge_name: string | null;
    notes: string | null;
    outcome: string | null;
    next_hearing_at: string | null;
}

const props = defineProps<{
    open: boolean;
    hearing?: HearingRow | null;
    // Pre-fill the date/time for a NEW hearing (e.g. clicking a calendar day).
    presetDate?: string | null;
    options: { statuses: EnumOption[]; cases: { id: number; name: string }[] };
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const toasts = useToastStore();
const processing = ref(false);

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm<HearingFormValues>({
    validationSchema: toTypedSchema(hearingSchema),
    initialValues: { status: 'scheduled', scheduled_at: '' } as HearingFormValues,
});

const [caseId] = defineField('case_id');
const [scheduledAt] = defineField('scheduled_at');
const [status] = defineField('status');
const [purpose] = defineField('purpose');
const [courtRoom] = defineField('court_room');
const [judgeName] = defineField('judge_name');
const [notes] = defineField('notes');
const [outcome] = defineField('outcome');
const [nextHearingAt] = defineField('next_hearing_at');

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        const h = props.hearing;
        resetForm({
            values: {
                case_id: (h?.case_id ?? null) as unknown as number,
                scheduled_at: h?.scheduled_at ? h.scheduled_at.slice(0, 16) : (props.presetDate ?? ''),
                status: h?.status?.value ?? 'scheduled',
                purpose: h?.purpose ?? '',
                court_room: h?.court_room ?? '',
                judge_name: h?.judge_name ?? '',
                notes: h?.notes ?? '',
                outcome: h?.outcome ?? '',
                next_hearing_at: h?.next_hearing_at ? h.next_hearing_at.slice(0, 16) : '',
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
    if (props.hearing) {
        router.put(`/hearings/${props.hearing.id}`, values as Record<string, unknown>, opts);
    } else {
        router.post('/hearings', values as Record<string, unknown>, opts);
    }
});

const inputClass =
    'h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400';
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ hearing ? 'Edit hearing' : 'Schedule hearing' }}</DialogTitle>
                <DialogDescription>{{ hearing ? 'Update the hearing details.' : 'Add a hearing to a matter.' }}</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <Label for="h_case">Case</Label>
                    <SearchableSelect
                        id="h_case"
                        v-model="caseId"
                        :options="options.cases.map((c) => ({ value: c.id, label: c.name }))"
                        placeholder="— Select a case —"
                    />
                    <InputError :message="errors.case_id" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="h_when">Date &amp; time</Label>
                        <input id="h_when" v-model="scheduledAt" type="datetime-local" :class="inputClass" />
                        <InputError :message="errors.scheduled_at" />
                    </div>
                    <div>
                        <Label for="h_status">Status</Label>
                        <select id="h_status" v-model="status" :class="inputClass">
                            <option v-for="s in options.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                </div>

                <div>
                    <Label for="h_purpose">Purpose</Label>
                    <Input id="h_purpose" v-model="purpose" placeholder="e.g. Final arguments" />
                    <InputError :message="errors.purpose" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><Label for="h_room">Court room</Label><Input id="h_room" v-model="courtRoom" /></div>
                    <div><Label for="h_judge">Judge</Label><Input id="h_judge" v-model="judgeName" /></div>
                </div>

                <div>
                    <Label for="h_notes">Notes</Label>
                    <textarea id="h_notes" v-model="notes" rows="2" :class="inputClass" class="!h-auto py-2" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="h_outcome">Outcome</Label>
                        <textarea id="h_outcome" v-model="outcome" rows="2" :class="inputClass" class="!h-auto py-2" />
                    </div>
                    <div>
                        <Label for="h_next">Next hearing</Label>
                        <input id="h_next" v-model="nextHearingAt" type="datetime-local" :class="inputClass" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="processing">{{ hearing ? 'Save changes' : 'Schedule' }}</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
