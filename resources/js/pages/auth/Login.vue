<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarCheck, FileText, LoaderCircle, Scale, ShieldCheck } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

// Convenience for evaluating the demo environment.
const useDemo = () => {
    form.email = 'admin@lexcase.test';
    form.password = 'password';
};

const highlights = [
    { icon: Scale, text: 'Every case, hearing & client in one place' },
    { icon: CalendarCheck, text: 'Never miss a hearing or deadline' },
    { icon: FileText, text: 'Printable, ready-to-file legal documents' },
    { icon: ShieldCheck, text: 'Role-based access & full audit trail' },
];
</script>

<template>
    <Head title="Log in" />
    <div class="grid min-h-screen lg:grid-cols-2">
        <!-- Brand panel -->
        <div class="relative hidden flex-col justify-between overflow-hidden bg-indigo-700 p-12 text-white lg:flex">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-700 via-indigo-800 to-slate-900" />
            <div class="absolute -right-24 -top-24 size-96 rounded-full bg-white/5" />
            <div class="absolute -bottom-32 -left-20 size-96 rounded-full bg-white/5" />

            <Link :href="route('home')" class="relative z-10 flex items-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/20">
                    <Scale class="size-5" />
                </span>
                <span class="text-lg font-semibold tracking-tight">LexCase</span>
            </Link>

            <div class="relative z-10 max-w-md">
                <h1 class="text-3xl font-semibold leading-tight tracking-tight">The operating system for modern law firms.</h1>
                <p class="mt-3 text-indigo-200">Manage cases, clients, hearings, documents and your team — beautifully, securely, and in one place.</p>
                <ul class="mt-8 space-y-3">
                    <li v-for="h in highlights" :key="h.text" class="flex items-center gap-3">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15">
                            <component :is="h.icon" class="size-4" />
                        </span>
                        <span class="text-sm text-indigo-50">{{ h.text }}</span>
                    </li>
                </ul>
            </div>

            <p class="relative z-10 text-xs text-indigo-300">© {{ new Date().getFullYear() }} LexCase. Built for advocates &amp; law firms.</p>
        </div>

        <!-- Form panel -->
        <div class="flex items-center justify-center bg-white px-6 py-12 sm:px-12">
            <div class="w-full max-w-sm">
                <div class="mb-8 lg:hidden">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-indigo-600 text-white"><Scale class="size-5" /></span>
                </div>

                <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Welcome back</h2>
                <p class="mt-1 text-sm text-slate-500">Sign in to your firm's workspace.</p>

                <div v-if="status" class="mt-4 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                    {{ status }}
                </div>

                <form class="mt-7 space-y-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input id="email" v-model="form.email" type="email" required autofocus autocomplete="email" placeholder="you@firm.com" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label for="password">Password</Label>
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-medium text-indigo-600 hover:underline">
                                Forgot password?
                            </Link>
                        </div>
                        <Input id="password" v-model="form.password" type="password" required autocomplete="current-password" placeholder="••••••••" />
                        <InputError :message="form.errors.password" />
                    </div>

                    <Label for="remember" class="flex items-center gap-2.5 text-sm text-slate-600">
                        <Checkbox id="remember" v-model:checked="form.remember" />
                        <span>Remember me for 30 days</span>
                    </Label>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        Sign in
                    </Button>
                </form>

                <!-- Demo helper -->
                <button
                    type="button"
                    class="mt-4 w-full rounded-lg border border-dashed border-slate-200 px-4 py-2.5 text-xs text-slate-500 transition hover:border-indigo-200 hover:bg-indigo-50/40 hover:text-indigo-600"
                    @click="useDemo"
                >
                    Try the demo · fill <span class="font-medium">admin@lexcase.test</span>
                </button>

                <p class="mt-6 text-center text-sm text-slate-500">
                    Don't have an account?
                    <Link :href="route('register')" class="font-medium text-indigo-600 hover:underline">Create one</Link>
                </p>
            </div>
        </div>
    </div>
</template>
