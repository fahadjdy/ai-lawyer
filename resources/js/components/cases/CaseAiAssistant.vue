<script setup lang="ts">
import StatusBadge from '@/components/common/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { postJson } from '@/lib/http';
import { AlertTriangle, Check, GitBranch, Loader2, Scale, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface HistoryEntry {
    stage?: string;
    title?: string;
    sections?: string[];
    notes?: string | null;
}

interface IpcSection {
    section: string;
    title: string;
    reason: string;
}
interface AiResult {
    summary: string;
    key_facts: string[];
    ipc_sections: IpcSection[];
    suggested_priority: string | null;
    disclaimer: string;
}

const props = withDefaults(
    defineProps<{
        fields: {
            title?: string | null;
            description?: string | null;
            case_type?: string | null;
            opposing_party?: string | null;
            court_name?: string | null;
        };
        // On the read-only case detail page there's no form to apply results to.
        showApply?: boolean;
        // Case tracking timeline (oldest-first) so re-analysis is history-aware.
        history?: HistoryEntry[];
        // Persisted mode (case detail page): when a caseId is given the result is
        // saved server-side, shown again on return, and flagged stale once the case
        // — notably its tracking — has changed.
        caseId?: string | null;
        stored?: AiResult | null;
        initialStale?: boolean;
        generatedAt?: string | null;
    }>(),
    { showApply: true, history: () => [], caseId: null, stored: null, initialStale: false, generatedAt: null },
);

const emit = defineEmits<{
    (e: 'apply-summary', value: string): void;
    (e: 'apply-priority', value: string): void;
}>();

const loading = ref(false);
const error = ref<string | null>(null);
const result = ref<AiResult | null>(props.stored ?? null);
// Stored result no longer matches the case (e.g. tracking moved on) → prompt a regenerate.
const stale = ref<boolean>(props.initialStale ?? false);
const savedAt = ref<string | null>(props.generatedAt ?? null);
const justSaved = ref(false);

const canRun = computed(() => (props.fields.description ?? '').trim().length >= 20);

const priorityColor: Record<string, string> = { low: 'slate', medium: 'blue', high: 'amber', urgent: 'rose' };

const hasHistory = computed(() => (props.history?.length ?? 0) > 0);

// "Saved" caption shown in persisted mode so the lawyer knows it's cached.
const savedLabel = computed<string | null>(() => {
    if (!props.caseId || !result.value) return null;
    if (justSaved.value) return 'Saved · just now';
    return savedAt.value ? `Saved · ${formatDate(savedAt.value)}` : 'Saved';
});

async function run() {
    if (!canRun.value || loading.value) return;
    loading.value = true;
    error.value = null;
    result.value = null;
    try {
        // Persisted mode hits a case-bound endpoint that reads the saved facts/history
        // itself and caches the result; draft mode posts the in-progress form fields.
        const url = props.caseId ? `/cases/${props.caseId}/analyze` : '/cases/ai/analyze';
        const body = props.caseId ? {} : { ...props.fields, history: props.history };
        const { ok, data } = await postJson<{ result?: AiResult; message?: string }>(url, body);
        if (!ok) {
            error.value = data?.message ?? 'Could not analyze the case. Please try again.';
            return;
        }
        result.value = data.result ?? null;
        stale.value = false;
        justSaved.value = true;
    } catch {
        error.value = 'Network error — please try again.';
    } finally {
        loading.value = false;
    }
}

function useSummary() {
    if (result.value?.summary) emit('apply-summary', result.value.summary);
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-indigo-100 bg-gradient-to-b from-indigo-50/70 to-white shadow-sm">
        <div class="border-b border-indigo-100/70 p-4">
            <div class="flex items-center gap-2">
                <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
                    <Sparkles class="size-4" />
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">AI Case Assistant</h3>
                    <p class="text-xs text-slate-500">Summarize &amp; suggest IPC sections</p>
                </div>
            </div>

            <Button type="button" class="mt-3 w-full" :disabled="!canRun || loading" @click="run">
                <Loader2 v-if="loading" class="size-4 animate-spin" />
                <Sparkles v-else class="size-4" />
                {{ loading ? 'Analyzing…' : result ? 'Re-analyze' : 'Summarize & suggest IPC' }}
            </Button>
            <p v-if="!canRun" class="mt-2 text-center text-xs text-slate-400">Add a case description to enable analysis.</p>
            <p v-else-if="hasHistory" class="mt-2 flex items-center justify-center gap-1 text-center text-xs text-indigo-500">
                <GitBranch class="size-3" /> Considers this case’s tracking history
            </p>
            <p v-if="savedLabel" class="mt-2 text-center text-[11px] text-slate-400">{{ savedLabel }}</p>
        </div>

        <div class="space-y-4 p-4">
            <!-- Error -->
            <div v-if="error" class="flex items-start gap-2 rounded-lg border border-rose-100 bg-rose-50 p-3 text-xs text-rose-700">
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                <span>{{ error }}</span>
            </div>

            <!-- Loading skeleton -->
            <div v-else-if="loading" class="space-y-2">
                <div class="h-3 w-3/4 animate-pulse rounded bg-slate-200" />
                <div class="h-3 w-full animate-pulse rounded bg-slate-200" />
                <div class="h-3 w-5/6 animate-pulse rounded bg-slate-200" />
                <div class="mt-3 h-16 animate-pulse rounded-lg bg-slate-100" />
            </div>

            <!-- Result -->
            <template v-else-if="result">
                <!-- Stale alert: the case moved on since this was generated -->
                <div
                    v-if="stale"
                    class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-[11px] leading-snug text-amber-700"
                >
                    <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                    <span
                        >This case has changed since these suggestions were generated.
                        <button type="button" class="font-semibold underline hover:no-underline" @click="run">Regenerate</button> to refresh.</span
                    >
                </div>

                <!-- Summary -->
                <section v-if="result.summary">
                    <div class="mb-1.5 flex items-center justify-between">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Summary</h4>
                        <button
                            v-if="showApply"
                            type="button"
                            class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline"
                            @click="useSummary"
                        >
                            <Check class="size-3" /> Use as description
                        </button>
                    </div>
                    <p class="text-sm leading-relaxed text-slate-700">{{ result.summary }}</p>
                </section>

                <!-- Suggested priority -->
                <section v-if="result.suggested_priority" class="flex items-center gap-2">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Priority</h4>
                    <StatusBadge :label="result.suggested_priority" :color="priorityColor[result.suggested_priority] ?? 'slate'" />
                    <button
                        v-if="showApply"
                        type="button"
                        class="text-xs font-medium text-indigo-600 hover:underline"
                        @click="emit('apply-priority', result.suggested_priority)"
                    >
                        Apply
                    </button>
                </section>

                <!-- Key facts -->
                <section v-if="result.key_facts.length">
                    <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Key facts</h4>
                    <ul class="space-y-1">
                        <li v-for="(f, i) in result.key_facts" :key="i" class="flex gap-2 text-sm text-slate-600">
                            <span class="mt-1.5 size-1 shrink-0 rounded-full bg-slate-300" />{{ f }}
                        </li>
                    </ul>
                </section>

                <!-- IPC sections -->
                <section>
                    <h4 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <Scale class="size-3.5" /> Possible IPC sections
                    </h4>
                    <div v-if="result.ipc_sections.length" class="space-y-2">
                        <div v-for="s in result.ipc_sections" :key="s.section" class="rounded-lg border border-slate-200 bg-white p-3">
                            <div class="flex items-center gap-2">
                                <span class="rounded-md bg-indigo-600 px-1.5 py-0.5 text-xs font-bold text-white">§ {{ s.section }}</span>
                                <span class="text-sm font-medium text-slate-800">{{ s.title }}</span>
                            </div>
                            <p v-if="s.reason" class="mt-1 text-xs text-slate-500">{{ s.reason }}</p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-400">No specific sections suggested from the given facts.</p>
                </section>

                <!-- Disclaimer -->
                <p v-if="result.disclaimer" class="flex items-start gap-1.5 rounded-lg bg-amber-50 p-2.5 text-[11px] leading-snug text-amber-700">
                    <AlertTriangle class="mt-0.5 size-3 shrink-0" />
                    <span>{{ result.disclaimer }}</span>
                </p>
            </template>

            <!-- Idle hint -->
            <p v-else class="text-center text-xs text-slate-400">
                Enter the case facts on the left, then run the assistant to get a structured summary and suggested IPC sections.
            </p>
        </div>
    </div>
</template>
