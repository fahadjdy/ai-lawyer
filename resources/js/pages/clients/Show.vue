<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ClientData {
    id: string;
    name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    pan: string | null;
    gstin: string | null;
    notes: string | null;
    type: EnumOption;
    cases: { id: string; case_number: string; title: string; status: { label: string; color: string } }[];
}

const props = defineProps<{ client: ClientData }>();
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Clients', href: '/clients' },
    { title: props.client.name, href: `/clients/${props.client.id}` },
]);
</script>

<template>
    <Head :title="client.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6">
            <PageHeader :title="client.name" :description="client.company ?? ''">
                <template #actions><StatusBadge :label="client.type.label" :color="client.type.color" /></template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-1">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-3 text-sm font-semibold text-slate-900">Contact</h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Email</dt><dd class="text-right text-slate-800">{{ client.email ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Phone</dt><dd class="text-right text-slate-800">{{ client.phone ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">City</dt><dd class="text-right text-slate-800">{{ client.city ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">State</dt><dd class="text-right text-slate-800">{{ client.state ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">PAN</dt><dd class="text-right text-slate-800">{{ client.pan ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">GSTIN</dt><dd class="text-right text-slate-800">{{ client.gstin ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-3 text-sm font-semibold text-slate-900">Cases ({{ client.cases.length }})</h2>
                        <ul v-if="client.cases.length" class="divide-y divide-slate-100">
                            <li v-for="c in client.cases" :key="c.id" class="py-2.5">
                                <Link :href="`/cases/${c.id}`" class="flex items-center justify-between gap-3 hover:opacity-80">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-800">{{ c.title }}</p>
                                        <p class="text-xs text-slate-500">{{ c.case_number }}</p>
                                    </div>
                                    <StatusBadge :label="c.status.label" :color="c.status.color" />
                                </Link>
                            </li>
                        </ul>
                        <EmptyState v-else title="No cases" description="This client has no cases yet." />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
