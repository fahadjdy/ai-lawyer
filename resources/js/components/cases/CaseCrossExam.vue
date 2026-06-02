<script setup lang="ts">
import EmptyState from '@/components/common/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { postJson } from '@/lib/http';
import { AlertTriangle, Gavel, Lightbulb, Loader2, ShieldQuestion, Sparkles, Swords } from 'lucide-vue-next';
import { computed, ref, type Component } from 'vue';

interface CrossQuestion {
    question: string;
    category: string;
    strategy: string;
}
interface CrossExamResult {
    opponent: CrossQuestion[];
    judge: CrossQuestion[];
    disclaimer: string;
}

const props = withDefaults(
    defineProps<{
        caseId: string;
        // False when the case has too little description to anticipate from.
        canGenerate?: boolean;
        // Cached result + staleness, served from the case detail page.
        stored?: CrossExamResult | null;
        initialStale?: boolean;
        generatedAt?: string | null;
    }>(),
    { canGenerate: false, stored: null, initialStale: false, generatedAt: null },
);

const loading = ref(false);
const error = ref<string | null>(null);
const result = ref<CrossExamResult | null>(props.stored ?? null);
// Stored questions no longer match the case (e.g. tracking moved on) → prompt a regenerate.
const stale = ref<boolean>(props.initialStale ?? false);
const savedAt = ref<string | null>(props.generatedAt ?? null);
const justSaved = ref(false);

type TabKey = 'opponent' | 'judge';
const tabs: { key: TabKey; label: string; Icon: Component }[] = [
    { key: 'opponent', label: 'Opponent', Icon: Swords },
    { key: 'judge', label: 'Judge', Icon: Gavel },
];
const activeTab = ref<TabKey>('opponent');
const activeIcon = computed<Component>(() => (activeTab.value === 'opponent' ? Swords : Gavel));

const activeList = computed<CrossQuestion[]>(() => (result.value ? result.value[activeTab.value] : []));
const counts = computed(() => ({
    opponent: result.value?.opponent.length ?? 0,
    judge: result.value?.judge.length ?? 0,
}));

// "Saved" caption so the lawyer knows the questions are cached, not regenerated each visit.
const savedLabel = computed<string | null>(() => {
    if (!result.value) return null;
    if (justSaved.value) return 'Saved · just now';
    return savedAt.value ? `Saved · ${formatDate(savedAt.value)}` : 'Saved';
});

async function run() {
    if (!props.canGenerate || loading.value) return;
    loading.value = true;
    error.value = null;
    try {
        const { ok, data } = await postJson<{ result?: CrossExamResult; message?: string }>(`/cases/${props.caseId}/cross-questions`, {});
        if (!ok) {
            error.value = data?.message ?? 'Could not generate questions. Please try again.';
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
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <!-- Header -->
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <ShieldQuestion class="size-4 text-slate-400" />
                <h2 class="text-sm font-semibold text-slate-900">Cross-examination prep</h2>
            </div>
            <Button size="sm" :variant="result ? 'outline' : 'default'" :disabled="!canGenerate || loading" @click="run">
                <Loader2 v-if="loading" class="size-4 animate-spin" />
                <Sparkles v-else class="size-4" />
                {{ loading ? 'Anticipating…' : result ? 'Regenerate' : 'Anticipate questions' }}
            </Button>
        </div>
        <div class="mb-4">
            <p class="text-xs text-slate-500">Likely questions from the opposing counsel and the bench, with a prep note for each.</p>
            <p v-if="savedLabel" class="mt-0.5 text-[11px] text-slate-400">{{ savedLabel }}</p>
        </div>

        <!-- Error -->
        <div v-if="error" class="flex items-start gap-2 rounded-lg border border-rose-100 bg-rose-50 p-3 text-xs text-rose-700">
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <span>{{ error }}</span>
        </div>

        <!-- Loading skeleton -->
        <div v-else-if="loading" class="space-y-3">
            <div v-for="i in 3" :key="i" class="rounded-lg border border-slate-100 p-3">
                <div class="h-3 w-3/4 animate-pulse rounded bg-slate-200" />
                <div class="mt-2 h-3 w-1/2 animate-pulse rounded bg-slate-100" />
            </div>
        </div>

        <!-- Result -->
        <template v-else-if="result">
            <!-- Stale alert: the case moved on since these were generated -->
            <div
                v-if="stale"
                class="mb-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-[11px] leading-snug text-amber-700"
            >
                <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                <span
                    >This case has changed since these were generated.
                    <button type="button" class="font-semibold underline hover:no-underline" @click="run">Regenerate</button> to refresh.</span
                >
            </div>

            <!-- Tabs -->
            <div class="inline-flex gap-1 rounded-lg bg-slate-100 p-1">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    @click="activeTab = t.key"
                    :class="[
                        'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        activeTab === t.key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700',
                    ]"
                >
                    <component :is="t.Icon" class="size-4" />
                    {{ t.label }}
                    <span class="rounded-full bg-slate-200/80 px-1.5 text-[11px] tabular-nums text-slate-600">{{ counts[t.key] }}</span>
                </button>
            </div>

            <!-- Question list -->
            <ul v-if="activeList.length" class="mt-4 space-y-2.5">
                <li v-for="(q, i) in activeList" :key="i" class="rounded-lg border border-slate-200 p-3">
                    <div class="flex items-start gap-2.5">
                        <span
                            class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md"
                            :class="activeTab === 'opponent' ? 'bg-rose-50 text-rose-500' : 'bg-indigo-50 text-indigo-500'"
                        >
                            <component :is="activeIcon" class="size-3.5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-slate-800">{{ q.question }}</p>
                                <span v-if="q.category" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">{{
                                    q.category
                                }}</span>
                            </div>
                            <p v-if="q.strategy" class="mt-1.5 flex items-start gap-1.5 text-xs leading-relaxed text-slate-500">
                                <Lightbulb class="mt-0.5 size-3.5 shrink-0 text-amber-400" />
                                <span>{{ q.strategy }}</span>
                            </p>
                        </div>
                    </div>
                </li>
            </ul>
            <p v-else class="mt-4 text-sm text-slate-400">No likely questions identified for this side.</p>

            <!-- Disclaimer -->
            <p v-if="result.disclaimer" class="mt-4 flex items-start gap-1.5 rounded-lg bg-amber-50 p-2.5 text-[11px] leading-snug text-amber-700">
                <AlertTriangle class="mt-0.5 size-3 shrink-0" />
                <span>{{ result.disclaimer }}</span>
            </p>
        </template>

        <!-- Idle -->
        <EmptyState
            v-else
            :icon="ShieldQuestion"
            title="Prepare for cross-examination"
            :description="
                canGenerate
                    ? 'Generate the questions the opponent and judge are likely to ask, each with a prep note.'
                    : 'Add a case description first so the assistant has enough to work with.'
            "
        >
            <template v-if="canGenerate" #action>
                <Button @click="run"><Sparkles class="size-4" /> Anticipate questions</Button>
            </template>
        </EmptyState>
    </div>
</template>
