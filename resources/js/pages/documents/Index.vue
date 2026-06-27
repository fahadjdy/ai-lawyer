<script setup lang="ts">
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import Pagination from '@/components/common/Pagination.vue';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import DocumentEditDialog from '@/components/documents/DocumentEditDialog.vue';
import DocumentUploadDialog from '@/components/documents/DocumentUploadDialog.vue';
import DocumentVersionsDialog from '@/components/documents/DocumentVersionsDialog.vue';
import NewFolderDialog from '@/components/documents/NewFolderDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { useFilters } from '@/composables/useFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Eye, FileText, FolderPlus, History, MoreHorizontal, Search, UploadCloud } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DocRow {
    id: string;
    name: string;
    original_name: string;
    extension: string | null;
    mime_type: string | null;
    size: string;
    version: number;
    versions_count: number;
    case: { id: string; case_number: string } | null;
    case_id: number | null;
    folder: { id: string; name: string } | null;
    folder_id: number | null;
    uploaded_by: string | null;
    created_at: string;
}

const props = defineProps<{
    documents: Paginated<DocRow>;
    filters: { search?: string; folder?: string };
    folders: { id: number; uuid: string; name: string }[];
    options: { cases: { id: number; name: string }[] };
}>();

const { can } = usePermissions();
const canManage = computed(() => can('documents.manage'));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Documents', href: '/documents' }];
const { filters } = useFilters('documents.index', { search: props.filters.search ?? '', folder: props.filters.folder ?? '' });

const dialogOptions = computed(() => ({ cases: props.options.cases, folders: props.folders }));
const folderFilterOptions = computed(() => [
    { value: '', label: 'All folders' },
    ...props.folders.map((f) => ({ value: f.uuid, label: f.name })),
]);

// ---- Upload (create + drag & drop) ----
const uploadOpen = ref(false);
const uploadMode = ref<'create' | 'version'>('create');
const versionTarget = ref<DocRow | null>(null);
const presetFiles = ref<File[]>([]);
const dragging = ref(false);

function openUpload() {
    uploadMode.value = 'create';
    versionTarget.value = null;
    presetFiles.value = [];
    uploadOpen.value = true;
}
function openVersion(row: DocRow) {
    uploadMode.value = 'version';
    versionTarget.value = row;
    presetFiles.value = [];
    uploadOpen.value = true;
}
function onDrop(e: DragEvent) {
    dragging.value = false;
    if (!canManage.value) return;
    const files = Array.from(e.dataTransfer?.files ?? []);
    if (files.length === 0) return;
    uploadMode.value = 'create';
    versionTarget.value = null;
    presetFiles.value = files;
    uploadOpen.value = true;
}

// ---- Version history ----
const versionsOpen = ref(false);
const versionsTarget = ref<DocRow | null>(null);
function openVersions(row: DocRow) {
    versionsTarget.value = row;
    versionsOpen.value = true;
}

function downloadDoc(row: DocRow) {
    window.location.href = `/documents/${row.id}/download`;
}

function previewDoc(row: DocRow) {
    window.open(`/documents/${row.id}/preview`, '_blank');
}

// ---- Edit (rename / move) ----
const editOpen = ref(false);
const editing = ref<DocRow | null>(null);
function openEdit(row: DocRow) {
    editing.value = row;
    editOpen.value = true;
}

// ---- New folder ----
const folderOpen = ref(false);

// ---- Delete ----
const confirmOpen = ref(false);
const deleting = ref<DocRow | null>(null);
function askDelete(row: DocRow) {
    deleting.value = row;
    confirmOpen.value = true;
}
function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/documents/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false;
            deleting.value = null;
        },
    });
}

const columns: Column[] = [
    { key: 'name', label: 'Name', primary: true },
    { key: 'case', label: 'Case' },
    { key: 'folder', label: 'Folder' },
    { key: 'size', label: 'Size' },
    { key: 'version', label: 'Ver.', align: 'center' },
    { key: 'uploaded_by', label: 'Uploaded by' },
    { key: 'created_at', label: 'Date' },
    { key: 'actions', label: '', align: 'right', hideLabelOnMobile: true },
];
</script>

<template>
    <Head title="Documents" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 sm:p-6">
            <PageHeader title="Documents" description="Secure document repository with versioning.">
                <template v-if="canManage" #actions>
                    <Button variant="outline" @click="folderOpen = true"><FolderPlus class="size-4" /> New folder</Button>
                    <Button @click="openUpload"><UploadCloud class="size-4" /> Upload</Button>
                </template>
            </PageHeader>

            <!-- Drag & drop upload zone -->
            <div
                v-if="canManage"
                class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-dashed p-5 text-sm transition"
                :class="dragging ? 'border-indigo-400 bg-indigo-50/60 text-indigo-600' : 'border-slate-300 bg-white text-slate-500 hover:border-indigo-300'"
                @click="openUpload"
                @dragover.prevent="dragging = true"
                @dragenter.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <div class="flex items-center gap-3">
                    <UploadCloud class="size-5" :class="dragging ? 'text-indigo-500' : 'text-slate-400'" />
                    <span>Drag &amp; drop a file here, or click to browse. Supports PDF, DOCX, images, audio &amp; video.</span>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative sm:max-w-md sm:flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <Input v-model="filters.search" placeholder="Search documents…" class="pl-9" />
                </div>
                <div class="sm:w-56">
                    <SearchableSelect v-model="filters.folder" :options="folderFilterOptions" placeholder="All folders" />
                </div>
            </div>

            <DataTable :columns="columns" :rows="documents.data" row-key="id" empty-title="No documents">
                <template #cell-name="{ row }">
                    <span class="inline-flex items-center gap-2">
                        <FileText class="size-4 text-slate-400" />
                        <span class="font-medium text-slate-900">{{ row.name }}</span>
                        <span v-if="row.extension" class="rounded bg-slate-100 px-1.5 text-[10px] uppercase text-slate-500">{{ row.extension }}</span>
                    </span>
                </template>
                <template #cell-case="{ row }">
                    <Link v-if="row.case" :href="`/cases/${row.case.id}`" class="text-indigo-600 hover:underline">{{ row.case.case_number }}</Link>
                    <span v-else>—</span>
                </template>
                <template #cell-folder="{ row }">{{ row.folder?.name ?? '—' }}</template>
                <template #cell-version="{ row }">
                    <span class="inline-flex items-center gap-1">
                        v{{ row.version }}
                        <span v-if="row.versions_count > 0" class="rounded-full bg-slate-100 px-1.5 text-[10px] text-slate-500">{{ row.versions_count + 1 }}</span>
                    </span>
                </template>
                <template #cell-created_at="{ row }">{{ formatDate(row.created_at) }}</template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end" @click.stop>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Document actions">
                                    <MoreHorizontal class="size-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-44">
                                <DropdownMenuItem @select="previewDoc(row)"><Eye class="size-4" /> Preview</DropdownMenuItem>
                                <DropdownMenuItem @select="downloadDoc(row)"><Download class="size-4" /> Download</DropdownMenuItem>
                                <DropdownMenuItem v-if="row.versions_count > 0" @select="openVersions(row)"><History class="size-4" /> Version history</DropdownMenuItem>
                                <template v-if="canManage">
                                    <DropdownMenuItem @select="openVersion(row)">Upload new version</DropdownMenuItem>
                                    <DropdownMenuItem @select="openEdit(row)">Rename / move</DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="text-rose-600 focus:text-rose-700" @select="askDelete(row)">Delete</DropdownMenuItem>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </template>
            </DataTable>

            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ documents.total ?? documents.data.length }} documents</p>
                <Pagination :links="documents.links" />
            </div>
        </div>

        <DocumentUploadDialog
            v-model:open="uploadOpen"
            :mode="uploadMode"
            :target="versionTarget"
            :preset-files="presetFiles"
            :options="dialogOptions"
        />
        <DocumentVersionsDialog v-model:open="versionsOpen" :target="versionsTarget" />
        <DocumentEditDialog v-model:open="editOpen" :document="editing" :options="dialogOptions" />
        <NewFolderDialog v-model:open="folderOpen" :options="{ cases: options.cases }" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete document?"
            :description="`“${deleting?.name ?? ''}” will be removed. Earlier versions stay intact.`"
            confirm-label="Delete document"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
