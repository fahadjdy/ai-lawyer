<script setup lang="ts">
import ClientForm from '@/components/clients/ClientForm.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, EnumOption } from '@/types';
import type { ClientFormValues } from '@/validation/clientSchema';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    clientUuid: string;
    client: Partial<ClientFormValues>;
    options: { types: EnumOption[] };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clients', href: '/clients' },
    { title: 'Edit', href: `/clients/${props.clientUuid}/edit` },
];
</script>

<template>
    <Head title="Edit Client" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-5 p-4 sm:p-6">
            <PageHeader title="Edit client" description="Update client details." />
            <ClientForm :options="options" :initial="client" :submit-url="`/clients/${clientUuid}`" method="put" submit-label="Save changes" />
        </div>
    </AppLayout>
</template>
