<script setup lang="ts">
import CaseAiAssistant from '@/components/cases/CaseAiAssistant.vue';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { caseSchema, type CaseFormValues } from '@/validation/caseSchema';
import { useToastStore } from '@/stores/toasts';
import { gradient } from '@/lib/chartColors';
import { FAV_TEXT, favorabilityLabel, favorabilityToken } from '@/lib/favorability';
import { cn } from '@/lib/utils';
import { toTypedSchema } from '@vee-validate/zod';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { useForm } from 'vee-validate';
import { computed, ref, watchEffect } from 'vue';
import type { EnumOption } from '@/types';

const props = defineProps<{
    options: {
        statuses: EnumOption[];
        priorities: EnumOption[];
        types: EnumOption[];
        clients: { id: number; name: string }[];
        lawyers: { id: number; name: string; designation: string | null }[];
    };
    initial?: Partial<CaseFormValues>;
    submitUrl: string;
    method?: 'post' | 'put';
    submitLabel?: string;
}>();

const toasts = useToastStore();
const processing = ref(false);

const { defineField, handleSubmit, errors, setErrors } = useForm<CaseFormValues>({
    validationSchema: toTypedSchema(caseSchema),
    initialValues: {
        title: '',
        case_number: '',
        client_id: null,
        case_type: 'civil',
        status: 'intake',
        priority: 'medium',
        favorability: null,
        description: '',
        court_name: '',
        court_type: '',
        jurisdiction: '',
        judge_name: '',
        opposing_party: '',
        opposing_counsel: '',
        filing_date: '',
        next_hearing_at: '',
        lead_lawyer_id: null,
        ...props.initial,
    },
});

// vee-validate field bindings.
const [title] = defineField('title');
const [caseNumber] = defineField('case_number');
const [clientId] = defineField('client_id');
const [caseType] = defineField('case_type');
const [status] = defineField('status');
const [priority] = defineField('priority');
const [favorability] = defineField('favorability');
const [description] = defineField('description');

// Favourability is an optional 0–100 assessment. A local slider + "assessed"
// toggle drive the actual (nullable) form value so an un-assessed case stays
// null rather than defaulting to a misleading number.
const favAssessed = ref(props.initial?.favorability !== null && props.initial?.favorability !== undefined);
const favSlider = ref(typeof props.initial?.favorability === 'number' ? props.initial.favorability : 50);
watchEffect(() => {
    favorability.value = favAssessed.value ? favSlider.value : null;
});
const favToken = computed(() => favorabilityToken(favSlider.value));
const favGrad = computed(() => gradient(favToken.value));
const favTextClass = computed(() => FAV_TEXT[favToken.value]);
const favLabelText = computed(() => favorabilityLabel(favSlider.value));
const [courtName] = defineField('court_name');
const [courtType] = defineField('court_type');
const [jurisdiction] = defineField('jurisdiction');
const [judgeName] = defineField('judge_name');
const [opposingParty] = defineField('opposing_party');
const [opposingCounsel] = defineField('opposing_counsel');
const [filingDate] = defineField('filing_date');
const [nextHearingAt] = defineField('next_hearing_at');
const [leadLawyerId] = defineField('lead_lawyer_id');

const submit = handleSubmit(
    (values) => {
        processing.value = true;
        const verb = props.method ?? 'post';
        router[verb](props.submitUrl, values as Record<string, unknown>, {
            onError: (serverErrors) => {
                // Surface server-side validation back into the form.
                setErrors(serverErrors as Record<string, string>);
                toasts.error('Please correct the highlighted fields.');
            },
            onFinish: () => (processing.value = false),
        });
    },
    // Without this, a failed client-side validation makes the Save button look
    // dead — vee-validate just refuses to call the submit handler. Tell the user.
    () => toasts.error('Please correct the highlighted fields.'),
);

// Snapshot of the fields the AI assistant analyzes (kept reactive).
const aiFields = computed(() => ({
    title: title.value,
    description: description.value,
    case_type: caseType.value,
    opposing_party: opposingParty.value,
    court_name: courtName.value,
}));

function applySummary(text: string) {
    description.value = text;
    toasts.success('Summary applied to the description.');
}
function applyPriority(value: string) {
    priority.value = value;
}

const inputClass = 'h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400';
</script>

<template>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <form class="space-y-6 lg:col-span-2" @submit.prevent="submit">
        <!-- Case details -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Case details</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <Label for="title">Title</Label>
                    <Input id="title" v-model="title" placeholder="e.g. Sharma vs. State of Maharashtra" />
                    <InputError :message="errors.title" />
                </div>
                <div>
                    <Label for="case_number">Case number</Label>
                    <Input id="case_number" v-model="caseNumber" placeholder="Auto-generated if blank" />
                    <InputError :message="errors.case_number" />
                </div>
                <div>
                    <Label for="client_id">Client</Label>
                    <SearchableSelect
                        id="client_id"
                        v-model="clientId"
                        :options="options.clients.map((c) => ({ value: c.id, label: c.name }))"
                        placeholder="— No client —"
                        clearable
                    />
                    <InputError :message="errors.client_id" />
                </div>
                <div>
                    <Label for="case_type">Type</Label>
                    <select id="case_type" v-model="caseType" :class="inputClass">
                        <option v-for="t in options.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <InputError :message="errors.case_type" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="status">Status</Label>
                        <select id="status" v-model="status" :class="inputClass">
                            <option v-for="s in options.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                    <div>
                        <Label for="priority">Priority</Label>
                        <select id="priority" v-model="priority" :class="inputClass">
                            <option v-for="p in options.priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                        <InputError :message="errors.priority" />
                    </div>
                </div>

                <!-- Favourability: how strongly the case is in our favour -->
                <div class="md:col-span-2">
                    <div class="flex items-center justify-between">
                        <Label for="favorability">
                            Favourability <span class="font-normal text-slate-400">— how much the case is in your favour</span>
                        </Label>
                        <button
                            type="button"
                            class="text-xs font-medium text-slate-500 transition hover:text-slate-700"
                            @click="favAssessed = !favAssessed"
                        >
                            {{ favAssessed ? 'Mark not assessed' : 'Assess now' }}
                        </button>
                    </div>
                    <div v-if="favAssessed" class="mt-2">
                        <div class="flex items-center gap-4">
                            <input
                                id="favorability"
                                v-model.number="favSlider"
                                type="range"
                                min="0"
                                max="100"
                                step="1"
                                class="h-2 flex-1 cursor-pointer accent-indigo-600"
                            />
                            <span :class="cn('w-12 text-right text-lg font-semibold tabular-nums', favTextClass)">{{ favSlider }}%</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div :class="cn('h-full rounded-full bg-gradient-to-r transition-all', favGrad)" :style="{ width: `${favSlider}%` }" />
                        </div>
                        <p :class="cn('mt-1 text-xs font-medium', favTextClass)">{{ favLabelText }}</p>
                    </div>
                    <p v-else class="mt-2 text-sm text-slate-400">Not assessed yet — click “Assess now” to set a favourability.</p>
                    <InputError :message="errors.favorability" />
                </div>

                <div class="md:col-span-2">
                    <Label for="description">Description</Label>
                    <textarea id="description" v-model="description" rows="3" :class="inputClass" class="!h-auto py-2" />
                    <InputError :message="errors.description" />
                </div>
            </div>
        </section>

        <!-- Court & opposing -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Court &amp; jurisdiction</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><Label for="court_name">Court name</Label><Input id="court_name" v-model="courtName" /></div>
                <div><Label for="court_type">Court type</Label><Input id="court_type" v-model="courtType" /></div>
                <div><Label for="jurisdiction">Jurisdiction</Label><Input id="jurisdiction" v-model="jurisdiction" /></div>
                <div><Label for="judge_name">Judge</Label><Input id="judge_name" v-model="judgeName" /></div>
                <div><Label for="opposing_party">Opposing party</Label><Input id="opposing_party" v-model="opposingParty" /></div>
                <div><Label for="opposing_counsel">Opposing counsel</Label><Input id="opposing_counsel" v-model="opposingCounsel" /></div>
            </div>
        </section>

        <!-- Schedule & assignment -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Schedule &amp; assignment</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div><Label for="filing_date">Filing date</Label><input id="filing_date" v-model="filingDate" type="date" :class="inputClass" /></div>
                <div><Label for="next_hearing_at">Next hearing</Label><input id="next_hearing_at" v-model="nextHearingAt" type="datetime-local" :class="inputClass" /></div>
                <div>
                    <Label for="lead_lawyer_id">Lead lawyer</Label>
                    <SearchableSelect
                        id="lead_lawyer_id"
                        v-model="leadLawyerId"
                        :options="options.lawyers.map((l) => ({ value: l.id, label: l.name, hint: l.designation ?? undefined }))"
                        placeholder="— Unassigned —"
                        clearable
                    />
                </div>
            </div>
        </section>

        <div class="flex items-center justify-end gap-3">
            <Button variant="outline" as-child type="button"><Link href="/cases">Cancel</Link></Button>
            <Button type="submit" :disabled="processing">{{ submitLabel ?? 'Save case' }}</Button>
        </div>
    </form>

        <!-- Right: AI Case Assistant -->
        <aside class="lg:col-span-1">
            <CaseAiAssistant
                class="lg:sticky lg:top-20"
                :fields="aiFields"
                @apply-summary="applySummary"
                @apply-priority="applyPriority"
            />
        </aside>
    </div>
</template>
