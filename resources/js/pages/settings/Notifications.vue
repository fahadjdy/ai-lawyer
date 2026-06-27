<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{ preferences: { email: boolean } }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notification settings', href: '/settings/notifications' }];

const form = useForm({ email: props.preferences.email });

const submit = () => form.put(route('notifications.preferences.update'), { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Notification settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Notifications" description="Choose how you'd like to be notified" />

                <form @submit.prevent="submit" class="space-y-6">
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4">
                        <input v-model="form.email" type="checkbox" class="mt-0.5 rounded border-slate-300" />
                        <span>
                            <span class="block text-sm font-medium text-slate-800">Email notifications</span>
                            <span class="block text-xs text-slate-500">Receive case assignments, hearing schedules and deadline reminders by email. In-app notifications are always on.</span>
                        </span>
                    </label>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>
                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-neutral-600">Saved.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
