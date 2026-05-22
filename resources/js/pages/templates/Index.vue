<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { useFilters } from '@/composables/useFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Plus, Printer, Search } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

interface Template {
    id: string;
    title: string;
    category: string | null;
    description: string | null;
    is_global: boolean;
    variables: string[];
}
interface Section {
    uuid: string;
    act_name: string;
    section_number: string;
    title: string;
    category: string | null;
}

const props = defineProps<{
    templates: Template[];
    categories: string[];
    sections: Section[];
    filters: { category?: string; search?: string };
}>();

const { can } = usePermissions();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Legal Library', href: '/templates' }];
const { filters } = useFilters('templates.index', { search: props.filters.search ?? '', category: props.filters.category ?? '' });

const setCategory = (c: string) => {
    filters.category = filters.category === c ? '' : c;
};
</script>

<template>
    <Head title="Legal Library" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6">
            <PageHeader title="Legal Library" description="Printable, editable & customizable legal documents — fill the fields and print.">
                <template #actions>
                    <Button v-if="can('templates.manage')" as-child>
                        <Link href="/templates/create"><Plus class="mr-1.5 size-4" /> New Document</Link>
                    </Button>
                </template>
            </PageHeader>

            <!-- Search + category chips -->
            <div class="space-y-3">
                <div class="relative max-w-md">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <Input v-model="filters.search" placeholder="Search documents…" class="pl-9" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="c in categories"
                        :key="c"
                        :class="cn(
                            'rounded-full border px-3 py-1 text-xs font-medium transition',
                            filters.category === c ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                        )"
                        @click="setCategory(c)"
                    >
                        {{ c }}
                    </button>
                </div>
            </div>

            <!-- Document cards -->
            <div v-if="templates.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="t in templates"
                    :key="t.id"
                    :href="`/templates/${t.id}/edit`"
                    class="group flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md"
                >
                    <div class="flex items-start justify-between">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><FileText class="size-5" /></span>
                        <span v-if="t.is_global" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-500">Library</span>
                        <span v-else class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-600">My firm</span>
                    </div>
                    <h3 class="mt-3 text-sm font-semibold text-slate-900 group-hover:text-indigo-700">{{ t.title }}</h3>
                    <p class="text-xs font-medium text-slate-400">{{ t.category }}</p>
                    <p class="mt-1 line-clamp-2 flex-1 text-xs text-slate-500">{{ t.description }}</p>
                    <div class="mt-3 flex items-center gap-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
                        <span>{{ t.variables.length }} fields</span>
                        <span class="inline-flex items-center gap-1 text-indigo-500"><Printer class="size-3.5" /> Printable</span>
                    </div>
                </Link>
            </div>
            <EmptyState v-else title="No documents" description="Adjust your search or create a new document." />

            <!-- Statute reference -->
            <div>
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Statute reference</h2>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <ul class="divide-y divide-slate-100">
                        <li v-for="s in sections" :key="s.uuid" class="flex items-center justify-between gap-4 px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ s.act_name }} — §{{ s.section_number }}</p>
                                <p class="text-xs text-slate-500">{{ s.title }}</p>
                            </div>
                            <span v-if="s.category" class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ s.category }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
