<script setup lang="ts">
import PageHeader from '@/components/common/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToastStore } from '@/stores/toasts';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, FileText, Printer, Save } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface TemplateData {
    id: string | null;
    title: string;
    category: string | null;
    description: string | null;
    body: string;
    variables: string[];
    is_global: boolean;
    editable: boolean;
}

const props = defineProps<{ template: TemplateData; mode: 'create' | 'edit' | 'view' }>();
const toasts = useToastStore();

const title = ref(props.template.title);
const category = ref(props.template.category ?? '');
const description = ref(props.template.description ?? '');
const body = ref(props.template.body);
const activeTab = ref<'fill' | 'content'>('fill');
const processing = ref(false);

// Merge-field values keyed by placeholder name, filled by the user.
const fieldValues = reactive<Record<string, string>>({});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Legal Library', href: '/templates' },
    { title: props.template.title, href: '#' },
];

// Auto-detect {{placeholder}} merge fields live from the body.
const variables = computed<string[]>(() => {
    const matches = [...body.value.matchAll(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi)];
    return [...new Set(matches.map((m) => m[1]))];
});

const humanize = (key: string) => key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

// Substitute filled values; show unfilled placeholders highlighted in preview.
const renderedPreview = computed(() =>
    body.value.replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (_, key: string) => {
        const v = fieldValues[key];
        return v ? escapeHtml(v) : `<mark class="rounded bg-amber-100 px-1 text-amber-800">${humanize(key)}</mark>`;
    }),
);

function escapeHtml(s: string): string {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

// Print-ready HTML: filled values, blank underline for anything left empty.
function renderForPrint(): string {
    return body.value.replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (_, key: string) =>
        fieldValues[key] ? escapeHtml(fieldValues[key]) : '__________',
    );
}

function print() {
    const win = window.open('', '_blank', 'width=900,height=1000');
    if (!win) {
        toasts.error('Please allow pop-ups to print.');
        return;
    }
    win.document.write(`<!DOCTYPE html><html><head><title>${escapeHtml(title.value)}</title>
        <style>
            @page { size: A4; margin: 22mm 20mm; }
            body { font-family: 'Georgia','Times New Roman',serif; color:#1a1a1a; line-height:1.7; font-size:12.5pt; }
            h2,h3 { margin: 0 0 12px; }
            p { margin: 0 0 12px; text-align: justify; }
            table { width:100%; border-collapse:collapse; }
            mark { background: none; }
        </style></head><body>${renderForPrint()}</body></html>`);
    win.document.close();
    win.focus();
    setTimeout(() => {
        win.print();
        win.close();
    }, 300);
}

function save() {
    processing.value = true;
    const payload = { title: title.value, category: category.value, description: description.value, body: body.value };
    const opts = {
        onSuccess: () => toasts.success('Saved.'),
        onError: () => toasts.error('Could not save. Check the form.'),
        onFinish: () => (processing.value = false),
    };
    if (props.mode === 'create') {
        router.post('/templates', payload, opts);
    } else {
        router.put(`/templates/${props.template.id}`, payload, opts);
    }
}

function duplicate() {
    router.post(`/templates/${props.template.id}/duplicate`);
}
</script>

<template>
    <Head :title="template.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader :title="mode === 'create' ? 'New document' : template.title"
                :description="template.is_global ? 'Shared library document — save a copy to customize.' : 'Fill the fields, then print. Switch to “Edit content” to customize.'">
                <template #actions>
                    <Button variant="outline" as-child><Link href="/templates">Back</Link></Button>
                    <Button v-if="!template.editable && template.id" variant="outline" @click="duplicate">
                        <Copy class="mr-1.5 size-4" /> Save a copy to edit
                    </Button>
                    <Button v-if="template.editable" variant="outline" :disabled="processing" @click="save">
                        <Save class="mr-1.5 size-4" /> Save
                    </Button>
                    <Button @click="print"><Printer class="mr-1.5 size-4" /> Print</Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left: fields / metadata -->
                <div class="space-y-4">
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex border-b border-slate-200 text-sm">
                            <button
                                class="flex-1 px-4 py-2.5 font-medium transition"
                                :class="activeTab === 'fill' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'"
                                @click="activeTab = 'fill'"
                            >
                                Merge fields
                            </button>
                            <button
                                v-if="template.editable"
                                class="flex-1 px-4 py-2.5 font-medium transition"
                                :class="activeTab === 'content' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'"
                                @click="activeTab = 'content'"
                            >
                                Edit content
                            </button>
                        </div>

                        <!-- Merge fields -->
                        <div v-show="activeTab === 'fill'" class="space-y-3 p-4">
                            <p v-if="!variables.length" class="text-sm text-slate-500">This document has no merge fields.</p>
                            <div v-for="v in variables" :key="v">
                                <Label :for="`f-${v}`" class="text-xs">{{ humanize(v) }}</Label>
                                <Input :id="`f-${v}`" v-model="fieldValues[v]" :placeholder="humanize(v)" />
                            </div>
                        </div>

                        <!-- Edit content -->
                        <div v-show="activeTab === 'content'" class="space-y-3 p-4">
                            <div>
                                <Label for="t-title" class="text-xs">Title</Label>
                                <Input id="t-title" v-model="title" />
                            </div>
                            <div>
                                <Label for="t-cat" class="text-xs">Category</Label>
                                <Input id="t-cat" v-model="category" />
                            </div>
                            <div>
                                <Label for="t-body" class="text-xs">Content (HTML · use &#123;&#123;field&#125;&#125; for merge fields)</Label>
                                <textarea id="t-body" v-model="body" rows="16"
                                    class="w-full rounded-md border border-slate-200 bg-white p-3 font-mono text-xs leading-relaxed text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: live A4 preview -->
                <div class="lg:col-span-2">
                    <div class="mb-2 flex items-center gap-2 text-xs text-slate-400">
                        <FileText class="size-3.5" /> Live preview
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-100 p-4 shadow-inner sm:p-8">
                        <article
                            class="prose-legal mx-auto max-w-[210mm] rounded-sm bg-white p-8 shadow-md sm:p-12"
                            v-html="renderedPreview"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.prose-legal {
    font-family: Georgia, 'Times New Roman', serif;
    line-height: 1.75;
    color: #1a1a1a;
}
.prose-legal :deep(h2),
.prose-legal :deep(h3) {
    font-weight: 700;
    margin: 0 0 0.75rem;
}
.prose-legal :deep(p) {
    margin: 0 0 0.75rem;
    text-align: justify;
}
.prose-legal :deep(table) {
    width: 100%;
}
</style>
