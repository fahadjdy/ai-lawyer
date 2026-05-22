<script setup lang="ts" generic="T extends Record<string, any>">
import { FileSearch } from 'lucide-vue-next';
import EmptyState from './EmptyState.vue';

export interface Column {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    class?: string;
}

/**
 * Reusable, slot-driven data table. Consumers declare columns and provide a
 * `cell-<key>` slot per column for custom rendering; falls back to the raw
 * value otherwise. Row click is emitted for navigable tables.
 */
defineProps<{
    columns: Column[];
    rows: T[];
    rowKey?: keyof T;
    clickable?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
}>();

const emit = defineEmits<{ (e: 'row-click', row: T): void }>();
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/80">
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            scope="col"
                            class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500"
                            :class="[col.class, col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : '']"
                        >
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="(row, i) in rows"
                        :key="rowKey ? (row[rowKey] as string) : i"
                        class="transition-colors"
                        :class="clickable ? 'cursor-pointer hover:bg-slate-50' : ''"
                        @click="clickable && emit('row-click', row)"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            class="px-4 py-3 align-middle text-slate-700"
                            :class="col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : ''"
                        >
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                {{ row[col.key] ?? '—' }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="rows.length === 0" class="p-6">
            <EmptyState
                :icon="FileSearch"
                :title="emptyTitle ?? 'No results'"
                :description="emptyDescription ?? 'Try adjusting your filters or search.'"
            />
        </div>
    </div>
</template>
