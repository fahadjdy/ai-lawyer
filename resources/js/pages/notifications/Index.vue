<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import Pagination from '@/components/common/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Bell, CheckCheck } from 'lucide-vue-next';

interface Notification {
    id: string;
    data: { message?: string; title?: string; case_number?: string };
    read_at: string | null;
    created_at: string;
}

defineProps<{ notifications: Paginated<Notification> }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/notifications' }];

const markAll = () => router.put('/notifications/read-all', {}, { preserveScroll: true });
const markOne = (id: string) => router.put(`/notifications/${id}/read`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Notifications" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl space-y-5 p-4 sm:p-6">
            <PageHeader title="Notifications">
                <template #actions>
                    <Button variant="outline" @click="markAll"><CheckCheck class="mr-1.5 size-4" /> Mark all read</Button>
                </template>
            </PageHeader>

            <div v-if="notifications.data.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="n in notifications.data"
                        :key="n.id"
                        class="flex items-start gap-3 px-4 py-3.5"
                        :class="n.read_at ? 'bg-white' : 'bg-indigo-50/40'"
                        @click="!n.read_at && markOne(n.id)"
                    >
                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600"><Bell class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-800">{{ n.data.message ?? n.data.title ?? 'Notification' }}</p>
                            <p class="text-xs text-slate-400">{{ formatDate(n.created_at, true) }}</p>
                        </div>
                        <span v-if="!n.read_at" class="mt-1.5 size-2 shrink-0 rounded-full bg-indigo-500" />
                    </li>
                </ul>
            </div>
            <EmptyState v-else :icon="Bell" title="No notifications" description="You're all caught up." />

            <div class="flex items-center justify-end"><Pagination :links="notifications.links" /></div>
        </div>
    </AppLayout>
</template>
