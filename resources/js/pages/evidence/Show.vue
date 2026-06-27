<script setup lang="ts">
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import CustodyDialog from '@/components/evidence/CustodyDialog.vue';
import EvidenceForm from '@/components/evidence/EvidenceForm.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, History, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CustodyEntry {
    action: string;
    handler: string;
    note: string | null;
    occurred_at: string | null;
    logged_by: string | null;
    logged_at: string | null;
}

interface EvidenceData {
    id: string;
    reference_number: string | null;
    title: string;
    description: string | null;
    type: EnumOption;
    status: EnumOption;
    collected_at: string | null;
    collected_by: string | null;
    case: { id: string; case_number: string; title: string } | null;
    case_id: number | null;
    document_id: number | null;
    document: { id: string; name: string; extension: string | null } | null;
    created_by: string | null;
    chain_of_custody: CustodyEntry[];
    created_at: string | null;
}

const props = defineProps<{
    evidence: EvidenceData;
    options: {
        statuses: EnumOption[];
        types: EnumOption[];
        cases: { id: number; name: string }[];
        documents: { id: number; name: string }[];
    };
}>();

const { can } = usePermissions();
const canManage = computed(() => can('evidence.manage'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Evidence', href: '/evidence' },
    { title: props.evidence.title, href: `/evidence/${props.evidence.id}` },
]);

// EvidenceForm expects a row-like shape; the show payload already matches it.
const editRow = computed(() => ({
    id: props.evidence.id,
    reference_number: props.evidence.reference_number,
    title: props.evidence.title,
    description: props.evidence.description,
    type: props.evidence.type,
    status: props.evidence.status,
    collected_at: props.evidence.collected_at,
    collected_by: props.evidence.collected_by,
    case_id: props.evidence.case_id,
    document_id: props.evidence.document_id,
}));

const editOpen = ref(false);
const custodyOpen = ref(false);
const confirmOpen = ref(false);

function confirmDelete() {
    router.delete(`/evidence/${props.evidence.id}`, { onFinish: () => (confirmOpen.value = false) });
}
</script>

<template>
    <Head :title="evidence.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6">
            <PageHeader :title="evidence.title" :description="evidence.reference_number ? `Exhibit ${evidence.reference_number}` : ''">
                <template #actions>
                    <StatusBadge :label="evidence.status.label" :color="evidence.status.color" />
                    <template v-if="canManage">
                        <Button variant="outline" @click="editOpen = true"><Pencil class="size-4" /> Edit</Button>
                        <Button variant="outline" class="text-rose-600 hover:text-rose-700" @click="confirmOpen = true"><Trash2 class="size-4" /> Delete</Button>
                    </template>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Details -->
                <div class="space-y-6 lg:col-span-1">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-3 text-sm font-semibold text-slate-900">Details</h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Type</dt><dd class="text-right text-slate-800">{{ evidence.type.label }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Reference</dt><dd class="text-right text-slate-800">{{ evidence.reference_number ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-slate-500">Case</dt>
                                <dd class="text-right">
                                    <Link v-if="evidence.case" :href="`/cases/${evidence.case.id}`" class="text-indigo-600 hover:underline">{{ evidence.case.case_number }}</Link>
                                    <span v-else class="text-slate-800">—</span>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Collected at</dt><dd class="text-right text-slate-800">{{ formatDate(evidence.collected_at, true) }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Collected by</dt><dd class="text-right text-slate-800">{{ evidence.collected_by ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Recorded by</dt><dd class="text-right text-slate-800">{{ evidence.created_by ?? '—' }}</dd></div>
                        </dl>

                        <div v-if="evidence.document" class="mt-4 border-t border-slate-100 pt-4">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Linked document</h3>
                            <a
                                :href="`/documents/${evidence.document.id}/download`"
                                class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:underline"
                            >
                                <Download class="size-4" />
                                {{ evidence.document.name }}<span v-if="evidence.document.extension" class="text-slate-400">.{{ evidence.document.extension }}</span>
                            </a>
                        </div>
                    </div>

                    <div v-if="evidence.description" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-2 text-sm font-semibold text-slate-900">Description</h2>
                        <p class="whitespace-pre-wrap text-sm text-slate-600">{{ evidence.description }}</p>
                    </div>
                </div>

                <!-- Chain of custody -->
                <div class="lg:col-span-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900"><ShieldCheck class="size-4 text-slate-400" /> Chain of custody</h2>
                            <Button v-if="canManage" size="sm" variant="outline" @click="custodyOpen = true"><Plus class="size-4" /> Add entry</Button>
                        </div>

                        <ol v-if="evidence.chain_of_custody.length" class="relative space-y-5 border-l border-slate-200 pl-5">
                            <li v-for="(entry, i) in evidence.chain_of_custody" :key="i" class="relative">
                                <span class="absolute -left-[1.6rem] top-0.5 flex size-6 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 ring-4 ring-white">
                                    <History class="size-3.5" />
                                </span>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="text-sm font-medium text-slate-900">{{ entry.action }}</span>
                                    <span class="text-xs text-slate-400">·</span>
                                    <span class="text-sm text-slate-600">{{ entry.handler }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ formatDate(entry.occurred_at, true) }}
                                    <span v-if="entry.logged_by"> · logged by {{ entry.logged_by }}</span>
                                </p>
                                <p v-if="entry.note" class="mt-1 text-sm text-slate-600">{{ entry.note }}</p>
                            </li>
                        </ol>
                        <EmptyState v-else :icon="ShieldCheck" title="No custody records" description="Add the first hand-off to start the audit trail." />
                    </div>
                </div>
            </div>
        </div>

        <EvidenceForm v-model:open="editOpen" :evidence="editRow" :options="options" />
        <CustodyDialog v-model:open="custodyOpen" :evidence-id="evidence.id" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete evidence?"
            :description="`“${evidence.title}” will be removed from the register.`"
            confirm-label="Delete evidence"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
