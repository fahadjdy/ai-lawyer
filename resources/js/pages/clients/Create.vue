<script setup lang="ts">
import ClientForm from '@/components/clients/ClientForm.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, EnumOption } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    options: { types: EnumOption[] };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clients', href: '/clients' },
    { title: 'New', href: '/clients/create' },
];
</script>

<template>
    <Head title="New Client" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-5 p-4 sm:p-6">
            <PageHeader title="New client" description="Add a person or organization your firm represents." />
            <ClientForm :options="options" submit-url="/clients" method="post" submit-label="Create client" />
        </div>
    </AppLayout>
</template>
