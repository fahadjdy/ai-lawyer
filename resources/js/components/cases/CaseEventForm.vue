<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/http';
import type { EnumOption } from '@/types';
import { router } from '@inertiajs/vue3';
import { Loader2, Sparkles, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface CaseEvent {
    id: string;
    stage: EnumOption;
    title: string;
    description: string | null;
    sections: string[];
    occurred_on: string | null;
}

const props = defineProps<{
    open: boolean;
    caseUuid: string;
    stages: EnumOption[];
    event?: CaseEvent | null;
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const stage = ref('complaint');
const title = ref('');
const occurredOn = ref('');
const description = ref('');
const sections = ref<string[]>([]);
const sectionInput = ref('');
const errors = ref<Record<string, string>>({});
const processing = ref(false);

function today(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        errors.value = {};
        sectionInput.value = '';
        const e = props.event;
        stage.value = e?.stage?.value ?? 'complaint';
        title.value = e?.title ?? '';
        description.value = e?.description ?? '';
        sections.value = [...(e?.sections ?? [])];
        occurredOn.value = e?.occurred_on ?? today();
    },
    { immediate: true },
);

// Section chips: commit on Enter / comma; split pasted comma-lists.
function commitInput() {
    const tokens = sectionInput.value
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean);
    for (const t of tokens) {
        if (!sections.value.includes(t)) sections.value.push(t);
    }
    sectionInput.value = '';
}
function onSectionKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        commitInput();
    } else if (e.key === 'Backspace' && !sectionInput.value && sections.value.length) {
        sections.value.pop();
    }
}
function removeSection(i: number) {
    sections.value.splice(i, 1);
}

// AI: auto-fill applicable sections from the update title (case-aware).
const suggesting = ref(false);
const suggestError = ref<string | null>(null);
const lastSuggestedFor = ref('');

async function suggestSections(auto = false) {
    const text = title.value.trim();
    if (!text || suggesting.value) return;
    // On auto (title blur), only run once per title and only if none added yet.
    if (auto && (sections.value.length > 0 || text.length < 4 || text === lastSuggestedFor.value)) return;

    suggesting.value = true;
    suggestError.value = null;
    lastSuggestedFor.value = text;
    try {
        const { ok, data } = await postJson<{ sections?: { section: string }[]; message?: string }>(
            `/cases/${props.caseUuid}/suggest-sections`,
            { text },
        );
        if (!ok) {
            suggestError.value = data?.message ?? 'Could not suggest sections.';
            return;
        }
        for (const s of data.sections ?? []) {
            const num = (s.section ?? '').trim();
            if (num && !sections.value.includes(num)) sections.value.push(num);
        }
    } catch {
        suggestError.value = 'Network error — please try again.';
    } finally {
        suggesting.value = false;
    }
}

const close = () => emit('update:open', false);

function submit() {
    commitInput();
    processing.value = true;
    errors.value = {};
    const payload = {
        stage: stage.value,
        title: title.value,
        description: description.value,
        sections: sections.value,
        occurred_on: occurredOn.value || null,
    };
    const opts = {
        preserveScroll: true,
        onError: (e: Record<string, string>) => (errors.value = e),
        onSuccess: close,
        onFinish: () => (processing.value = false),
    };
    if (props.event) {
        router.put(`/cases/${props.caseUuid}/events/${props.event.id}`, payload, opts);
    } else {
        router.post(`/cases/${props.caseUuid}/events`, payload, opts);
    }
}

const inputClass =
    'h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400';
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ event ? 'Edit update' : 'Add case update' }}</DialogTitle>
                <DialogDescription>Record a stage and the sections applicable at this point.</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="e_stage">Stage</Label>
                        <select id="e_stage" v-model="stage" :class="inputClass">
                            <option v-for="s in stages" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <InputError :message="errors.stage" />
                    </div>
                    <div>
                        <Label for="e_date">Date</Label>
                        <input id="e_date" v-model="occurredOn" type="date" :class="inputClass" />
                        <InputError :message="errors.occurred_on" />
                    </div>
                </div>

                <div>
                    <Label for="e_title">Title</Label>
                    <Input
                        id="e_title"
                        v-model="title"
                        placeholder="e.g. Charge sheet filed for cheating and forgery"
                        @blur="suggestSections(true)"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <Label>Applicable sections</Label>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 transition hover:underline disabled:opacity-50"
                            :disabled="suggesting || !title.trim()"
                            @click="suggestSections(false)"
                        >
                            <Loader2 v-if="suggesting" class="size-3 animate-spin" />
                            <Sparkles v-else class="size-3" />
                            {{ suggesting ? 'Suggesting…' : 'Suggest from title' }}
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 rounded-md border border-slate-200 bg-white p-2 shadow-sm focus-within:border-indigo-400 focus-within:ring-1 focus-within:ring-indigo-400">
                        <span
                            v-for="(s, i) in sections"
                            :key="i"
                            class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-700"
                        >
                            § {{ s }}
                            <button type="button" class="text-indigo-400 hover:text-indigo-600" @click="removeSection(i)"><X class="size-3" /></button>
                        </span>
                        <input
                            v-model="sectionInput"
                            class="min-w-[8rem] flex-1 border-0 p-0 text-sm focus:outline-none focus:ring-0"
                            placeholder="Type a section & press Enter…"
                            @keydown="onSectionKeydown"
                            @blur="commitInput"
                        />
                    </div>
                    <p class="mt-1 text-xs text-slate-400">e.g. 420, 406, 467 — press Enter or comma to add each, or use “Suggest from title”.</p>
                    <p v-if="suggestError" class="mt-1 text-xs text-rose-600">{{ suggestError }}</p>
                    <InputError :message="errors.sections" />
                </div>

                <div>
                    <Label for="e_desc">Notes <span class="text-slate-400">(optional)</span></Label>
                    <textarea id="e_desc" v-model="description" rows="3" :class="inputClass" class="!h-auto py-2" placeholder="What changed at this stage and why…" />
                    <InputError :message="errors.description" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="processing">{{ event ? 'Save update' : 'Add update' }}</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
