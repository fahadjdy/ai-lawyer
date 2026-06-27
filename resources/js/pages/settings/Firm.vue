<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Firm {
    name: string;
    email: string | null;
    phone: string | null;
    registration_no: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    website: string | null;
}

const props = defineProps<{ firm: Firm }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Firm settings', href: '/settings/firm' }];

const form = useForm({
    name: props.firm.name ?? '',
    email: props.firm.email ?? '',
    phone: props.firm.phone ?? '',
    registration_no: props.firm.registration_no ?? '',
    address: props.firm.address ?? '',
    city: props.firm.city ?? '',
    state: props.firm.state ?? '',
    website: props.firm.website ?? '',
});

const submit = () => form.put(route('firm.update'), { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Firm settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Firm profile" description="Your practice's name, contact details & branding" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Firm name</Label>
                        <Input id="name" class="mt-1 block w-full" v-model="form.name" required placeholder="Practice name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="email">Firm email</Label>
                            <Input id="email" type="email" v-model="form.email" placeholder="firm@example.com" />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input id="phone" v-model="form.phone" placeholder="+91 …" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="registration_no">Bar / registration no.</Label>
                            <Input id="registration_no" v-model="form.registration_no" />
                            <InputError class="mt-2" :message="form.errors.registration_no" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="website">Website</Label>
                            <Input id="website" v-model="form.website" placeholder="https://…" />
                            <InputError class="mt-2" :message="form.errors.website" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="address">Address</Label>
                        <Input id="address" v-model="form.address" placeholder="Street address" />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="city">City</Label>
                            <Input id="city" v-model="form.city" />
                            <InputError class="mt-2" :message="form.errors.city" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="state">State</Label>
                            <Input id="state" v-model="form.state" />
                            <InputError class="mt-2" :message="form.errors.state" />
                        </div>
                    </div>

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
