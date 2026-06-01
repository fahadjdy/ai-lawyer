<script setup lang="ts" generic="T extends string | number | null">
import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/vue';
import { Check, ChevronsUpDown, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Option {
    value: T;
    label: string;
    hint?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: T;
        options: Option[];
        placeholder?: string;
        clearable?: boolean;
        id?: string;
    }>(),
    { placeholder: 'Select…', clearable: false },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: T): void }>();

const query = ref('');

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter(
        (o) => o.label.toLowerCase().includes(q) || (o.hint ?? '').toLowerCase().includes(q),
    );
});

const selectedLabel = (val: T): string => props.options.find((o) => o.value === val)?.label ?? '';

const hasValue = computed(() => props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '');

function clear(e: Event) {
    e.preventDefault();
    e.stopPropagation();
    emit('update:modelValue', null as T);
    query.value = '';
}
</script>

<template>
    <Combobox
        :model-value="modelValue"
        nullable
        as="div"
        class="relative"
        @update:model-value="(v: T) => emit('update:modelValue', v)"
    >
        <div
            class="flex h-9 w-full items-center rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm focus-within:border-indigo-400 focus-within:ring-1 focus-within:ring-indigo-400"
        >
            <ComboboxInput
                :id="id"
                class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                :display-value="(v) => selectedLabel(v as T)"
                :placeholder="placeholder"
                autocomplete="off"
                @change="query = ($event.target as HTMLInputElement).value"
            />
            <button
                v-if="clearable && hasValue"
                type="button"
                class="ml-1 shrink-0 text-slate-300 hover:text-slate-500"
                tabindex="-1"
                @click="clear"
            >
                <X class="size-4" />
            </button>
            <ComboboxButton class="ml-1 shrink-0 text-slate-400">
                <ChevronsUpDown class="size-4" />
            </ComboboxButton>
        </div>

        <ComboboxOptions
            class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg focus:outline-none"
        >
            <div v-if="!filtered.length" class="px-3 py-2 text-slate-400">No matches.</div>
            <ComboboxOption
                v-for="o in filtered"
                :key="String(o.value)"
                :value="o.value"
                v-slot="{ active, selected }"
                as="template"
            >
                <li
                    class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2"
                    :class="active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'"
                >
                    <span class="truncate">
                        {{ o.label }}
                        <span v-if="o.hint" class="text-xs text-slate-400">· {{ o.hint }}</span>
                    </span>
                    <Check v-if="selected" class="size-4 shrink-0 text-indigo-600" />
                </li>
            </ComboboxOption>
        </ComboboxOptions>
    </Combobox>
</template>
