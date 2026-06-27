<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Monitor, Smartphone } from 'lucide-vue-next';
import { ref } from 'vue';

interface Session {
    id: string;
    ip: string | null;
    browser: string;
    platform: string;
    last_active: string;
    is_current: boolean;
}

defineProps<{ sessions: Session[]; supported: boolean }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Browser sessions', href: '/settings/sessions' }];

const confirming = ref(false);
const form = useForm({ password: '' });

function logoutOthers() {
    form.delete(route('sessions.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Browser sessions" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Browser sessions" description="Devices that are currently signed in to your account" />

                <p v-if="!supported" class="text-sm text-slate-500">Session listing requires the database session driver.</p>

                <ul v-else class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                    <li v-for="s in sessions" :key="s.id" class="flex items-center gap-3 p-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <component :is="s.platform === 'Android' || s.platform === 'iOS' ? Smartphone : Monitor" class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-800">{{ s.browser }} · {{ s.platform }}</p>
                            <p class="text-xs text-slate-500">{{ s.ip ?? 'Unknown IP' }} · {{ s.last_active }}</p>
                        </div>
                        <span v-if="s.is_current" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-600">This device</span>
                    </li>
                </ul>

                <div v-if="supported">
                    <Button v-if="!confirming" variant="outline" @click="confirming = true">Log out other sessions</Button>

                    <div v-else class="space-y-3 rounded-lg border border-slate-200 p-4">
                        <p class="text-sm text-slate-600">Enter your password to confirm signing out of all your other browser sessions.</p>
                        <div class="grid gap-2">
                            <Label for="pw">Password</Label>
                            <Input id="pw" v-model="form.password" type="password" placeholder="Current password" autocomplete="current-password" />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div class="flex items-center gap-3">
                            <Button variant="destructive" :disabled="form.processing" @click="logoutOthers">Log out other sessions</Button>
                            <Button variant="outline" @click="confirming = false; form.reset()">Cancel</Button>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
