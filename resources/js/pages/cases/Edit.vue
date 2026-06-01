<script setup lang="ts">
import CaseForm from '@/components/cases/CaseForm.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, EnumOption } from '@/types';
import type { CaseFormValues } from '@/validation/caseSchema';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    caseUuid: string;
    case: Partial<CaseFormValues>;
    options: {
        statuses: EnumOption[];
        priorities: EnumOption[];
        types: EnumOption[];
        clients: { id: number; name: string }[];
        lawyers: { id: number; name: string; designation: string | null }[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cases', href: '/cases' },
    { title: 'Edit', href: `/cases/${props.caseUuid}/edit` },
];
</script>

<template>
    <Head title="Edit Case" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
            <PageHeader title="Edit case" description="Update matter details." />
            <CaseForm :options="options" :initial="case" :submit-url="`/cases/${caseUuid}`" method="put" submit-label="Save changes" />
        </div>
    </AppLayout>
</template>
