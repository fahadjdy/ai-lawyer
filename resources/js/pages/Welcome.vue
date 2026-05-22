<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Briefcase,
    CalendarDays,
    CheckCircle2,
    FileText,
    Gavel,
    ListChecks,
    Scale,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';

const page = usePage();

const features = [
    { icon: Briefcase, title: 'Case Management', desc: 'Track every matter with timelines, hearings, documents, evidence and notes in one organised place.' },
    { icon: CalendarDays, title: 'Hearing Calendar', desc: 'Never miss a date — agenda views, reminders and judge/court details for every hearing.' },
    { icon: Users, title: 'Client Records', desc: 'Maintain complete client profiles, contacts and their associated cases at a glance.' },
    { icon: FileText, title: 'Legal Documents', desc: 'Printable, editable & customizable templates — vakalatnama, notices, applications and more.' },
    { icon: ListChecks, title: 'Tasks & Workflow', desc: 'Assign work across your team with priorities, due dates and a Kanban board.' },
    { icon: ShieldCheck, title: 'Security & Audit', desc: 'Role-based access, multi-tenant isolation and a complete audit trail of every action.' },
];

const modules = ['Cases', 'Clients', 'Hearings', 'Tasks', 'Documents', 'Evidence', 'Team', 'Activity Log', 'Legal Library'];
</script>

<template>
    <Head title="LexCase — Legal Case Management Platform">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-white text-slate-900" style="font-family: Inter, system-ui, sans-serif">
        <!-- Nav -->
        <header class="sticky top-0 z-40 border-b border-slate-100 bg-white/80 backdrop-blur">
            <nav class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-white"><Scale class="size-5" /></span>
                    <span class="text-lg font-semibold tracking-tight">LexCase</span>
                </div>
                <div class="flex items-center gap-3">
                    <Link v-if="page.props.auth.user" :href="route('dashboard')"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                        Go to Dashboard
                    </Link>
                    <template v-else>
                        <Link :href="route('login')" class="px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Log in</Link>
                        <Link :href="route('register')"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                            Get started
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-x-0 top-0 -z-10 h-[480px] bg-gradient-to-b from-indigo-50 to-white" />
            <div class="mx-auto max-w-3xl px-6 py-20 text-center sm:py-28">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-100 bg-white px-3 py-1 text-xs font-medium text-indigo-600 shadow-sm">
                    <Gavel class="size-3.5" /> Built for Indian advocates &amp; law firms
                </span>
                <h1 class="mt-6 text-4xl font-semibold tracking-tight sm:text-5xl">
                    The modern operating system for your <span class="text-indigo-600">legal practice</span>.
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-600">
                    Manage cases, clients, hearings, documents and your entire team in one secure, beautifully designed platform.
                </p>
                <div class="mt-8 flex items-center justify-center gap-3">
                    <Link :href="page.props.auth.user ? route('dashboard') : route('login')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                        {{ page.props.auth.user ? 'Open dashboard' : 'Start now' }} <ArrowRight class="size-4" />
                    </Link>
                    <Link :href="route('login')" class="rounded-lg border border-slate-200 px-6 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Live demo
                    </Link>
                </div>
                <p class="mt-3 text-xs text-slate-400">Demo login: admin@lexcase.test / password</p>
            </div>
        </section>

        <!-- Features -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-semibold tracking-tight">Everything your firm needs</h2>
                <p class="mt-3 text-slate-600">A complete legal workflow — no spreadsheets, no scattered files.</p>
            </div>
            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="f in features" :key="f.title" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"><component :is="f.icon" class="size-5" /></span>
                    <h3 class="mt-4 text-base font-semibold">{{ f.title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ f.desc }}</p>
                </div>
            </div>
        </section>

        <!-- Modules strip -->
        <section class="bg-slate-50 py-16">
            <div class="mx-auto max-w-6xl px-6 text-center">
                <h2 class="text-2xl font-semibold tracking-tight">One platform, every module</h2>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <span v-for="m in modules" :key="m"
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm">
                        <CheckCircle2 class="size-4 text-emerald-500" /> {{ m }}
                    </span>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="mx-auto max-w-6xl px-6 py-20">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-700 via-indigo-800 to-slate-900 px-8 py-14 text-center text-white">
                <div class="absolute -right-20 -top-20 size-72 rounded-full bg-white/5" />
                <h2 class="relative text-3xl font-semibold tracking-tight">Run your practice the modern way.</h2>
                <p class="relative mx-auto mt-3 max-w-xl text-indigo-200">Join firms managing their matters end-to-end with LexCase.</p>
                <Link :href="route('login')"
                    class="relative mt-8 inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50">
                    Get started <ArrowRight class="size-4" />
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-slate-100">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-8 text-sm text-slate-500 sm:flex-row">
                <div class="flex items-center gap-2">
                    <span class="flex size-6 items-center justify-center rounded bg-indigo-600 text-white"><Scale class="size-3.5" /></span>
                    <span class="font-medium text-slate-700">LexCase</span>
                </div>
                <p>© {{ new Date().getFullYear() }} LexCase. Legal Case Management Platform.</p>
            </div>
        </footer>
    </div>
</template>
