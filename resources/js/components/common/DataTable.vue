<script setup lang="ts" generic="T extends Record<string, any>">
import { FileSearch } from 'lucide-vue-next';
import EmptyState from './EmptyState.vue';

export interface Column {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    class?: string;
    /** Hide this column's label in the stacked mobile card view (e.g. actions). */
    hideLabelOnMobile?: boolean;
    /** Render this column as the card title on mobile (defaults to the first column). */
    primary?: boolean;
}

/**
 * Reusable, slot-driven data table. Consumers declare columns and provide a
 * `cell-<key>` slot per column for custom rendering; falls back to the raw
 * value otherwise. Row click is emitted for navigable tables.
 *
 * Responsive: renders a real table on `md+` and a stacked card list on small
 * screens, reusing the exact same `cell-<key>` slots so there's one source of
 * truth for cell rendering.
 */
const props = defineProps<{
    columns: Column[];
    rows: T[];
    rowKey?: keyof T;
    clickable?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
}>();

const emit = defineEmits<{ (e: 'row-click', row: T): void }>();

// The column shown as the heading of each mobile card.
const primaryKey = () => (props.columns.find((c) => c.primary) ?? props.columns[0])?.key;
const secondaryColumns = () => props.columns.filter((c) => c.key !== primaryKey());
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <!-- Desktop: table -->
        <div class="hidden overflow-x-auto md:block">
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

        <!-- Mobile: stacked cards -->
        <div class="divide-y divide-slate-100 md:hidden">
            <div
                v-for="(row, i) in rows"
                :key="rowKey ? (row[rowKey] as string) : i"
                class="p-4 transition-colors"
                :class="clickable ? 'cursor-pointer active:bg-slate-50' : ''"
                @click="clickable && emit('row-click', row)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <slot :name="`cell-${primaryKey()}`" :row="row" :value="row[primaryKey()!]">
                            <span class="font-medium text-slate-900">{{ row[primaryKey()!] ?? '—' }}</span>
                        </slot>
                    </div>
                </div>
                <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1.5 sm:grid-cols-2">
                    <div
                        v-for="col in secondaryColumns()"
                        :key="col.key"
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <dt v-if="!col.hideLabelOnMobile" class="shrink-0 text-xs text-slate-400">{{ col.label }}</dt>
                        <dd :class="['min-w-0 truncate text-slate-700', col.hideLabelOnMobile ? 'ml-auto' : '']" @click.stop>
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                {{ row[col.key] ?? '—' }}
                            </slot>
                        </dd>
                    </div>
                </dl>
            </div>
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
