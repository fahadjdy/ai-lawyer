<script setup lang="ts">
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, BookOpen, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Section {
    number: string;
    title: string;
    summary: string;
}
interface Act {
    key: string;
    name: string;
    short: string;
    year: string;
    category: string;
    description: string;
    sections: Section[];
}

const props = defineProps<{ acts: Act[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Legal Notebook', href: '/legal-notebook' }];

const q = ref('');
const activeKey = ref(props.acts[0]?.key ?? '');
const activeAct = computed(() => props.acts.find((a) => a.key === activeKey.value) ?? props.acts[0]);

const searching = computed(() => q.value.trim().length > 0);
const totalSections = computed(() => props.acts.reduce((n, a) => n + a.sections.length, 0));

// Flat search across every act's sections (and act names).
const matches = computed(() => {
    const term = q.value.trim().toLowerCase();
    if (!term) return [] as { act: Act; sections: Section[] }[];
    const out: { act: Act; sections: Section[] }[] = [];
    for (const act of props.acts) {
        const actHit = act.name.toLowerCase().includes(term) || act.short.toLowerCase().includes(term);
        const secs = act.sections.filter(
            (s) =>
                actHit ||
                s.number.toLowerCase().includes(term) ||
                s.title.toLowerCase().includes(term) ||
                s.summary.toLowerCase().includes(term),
        );
        if (secs.length) out.push({ act, sections: secs });
    }
    return out;
});
const matchCount = computed(() => matches.value.reduce((n, m) => n + m.sections.length, 0));

const categoryColor: Record<string, string> = {
    Criminal: 'bg-rose-50 text-rose-700 ring-rose-200',
    Civil: 'bg-blue-50 text-blue-700 ring-blue-200',
    Constitutional: 'bg-violet-50 text-violet-700 ring-violet-200',
    Procedure: 'bg-amber-50 text-amber-800 ring-amber-200',
    Commercial: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    Special: 'bg-slate-100 text-slate-600 ring-slate-200',
};
const catClass = (c: string) => categoryColor[c] ?? categoryColor.Special;
</script>

<template>
    <Head title="Legal Notebook" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Legal Notebook" :description="`${acts.length} acts · ${totalSections} key sections at your fingertips.`" />

            <!-- Disclaimer -->
            <div class="flex items-start gap-2 rounded-lg border border-amber-100 bg-amber-50 p-3 text-xs text-amber-700">
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                <span>Quick reference only — concise paraphrases, not the bare act text or legal advice. Always verify against the official statute and the code applicable on the date of the offence.</span>
            </div>

            <!-- Search -->
            <div class="relative">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <Input v-model="q" placeholder="Search a section, offence or act — e.g. “420”, “bail”, “cheque”…" class="pl-9" />
            </div>

            <!-- Search results -->
            <div v-if="searching" class="space-y-5">
                <p class="text-sm text-slate-500">{{ matchCount }} result{{ matchCount === 1 ? '' : 's' }} for “{{ q }}”</p>
                <div v-for="m in matches" :key="m.act.key" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold text-slate-900">{{ m.act.name }}</h2>
                        <span :class="['rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset', catClass(m.act.category)]">{{ m.act.category }}</span>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <article v-for="s in m.sections" :key="s.number" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                            <div class="flex items-center gap-2">
                                <span class="whitespace-nowrap rounded-md bg-indigo-600 px-1.5 py-0.5 text-xs font-bold text-white">{{ s.number }}</span>
                                <span class="text-sm font-medium text-slate-800">{{ s.title }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ s.summary }}</p>
                        </article>
                    </div>
                </div>
                <EmptyState v-if="!matches.length" :icon="Search" title="No matches" description="Try a section number, an offence, or an act name." />
            </div>

            <!-- Browse by act -->
            <div v-else class="grid gap-6 lg:grid-cols-4">
                <!-- Desktop: act list -->
                <aside class="hidden lg:col-span-1 lg:block">
                    <nav class="sticky top-20 space-y-1">
                        <button
                            v-for="act in acts"
                            :key="act.key"
                            type="button"
                            class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition"
                            :class="activeKey === act.key ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-slate-600 hover:bg-slate-100'"
                            @click="activeKey = act.key"
                        >
                            <span class="truncate">{{ act.short }}</span>
                            <span class="shrink-0 text-xs text-slate-400">{{ act.sections.length }}</span>
                        </button>
                    </nav>
                </aside>

                <!-- Sections -->
                <div class="space-y-4 lg:col-span-3">
                    <!-- Mobile: act pills -->
                    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 lg:hidden">
                        <button
                            v-for="act in acts"
                            :key="act.key"
                            type="button"
                            class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition"
                            :class="activeKey === act.key ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-500'"
                            @click="activeKey = act.key"
                        >
                            {{ act.short }}
                        </button>
                    </div>

                    <!-- Act header -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <BookOpen class="size-4 text-indigo-500" />
                            <h2 class="text-base font-semibold text-slate-900">{{ activeAct.name }}</h2>
                            <span :class="['rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset', catClass(activeAct.category)]">{{ activeAct.category }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ activeAct.description }}</p>
                    </div>

                    <!-- Section cards -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <article
                            v-for="s in activeAct.sections"
                            :key="s.number"
                            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md"
                        >
                            <div class="flex items-center gap-2">
                                <span class="whitespace-nowrap rounded-md bg-indigo-600 px-2 py-0.5 text-xs font-bold text-white">{{ s.number }}</span>
                                <h3 class="text-sm font-semibold text-slate-800">{{ s.title }}</h3>
                            </div>
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ s.summary }}</p>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
