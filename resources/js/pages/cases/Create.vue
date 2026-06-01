<script setup lang="ts">
import CaseForm from '@/components/cases/CaseForm.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
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
    { title: 'New', href: '/cases/create' },
];
</script>

<template>
    <Head title="New Case" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
            <PageHeader title="New case" description="Create a new matter for your firm." />
            <CaseForm :options="options" submit-url="/cases" method="post" submit-label="Create case" />
        </div>
    </AppLayout>
</template>
