<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, Info, X, XCircle } from 'lucide-vue-next';
import { storeToRefs } from 'pinia';
import { watch } from 'vue';
import { useToastStore } from '@/stores/toasts';
import type { SharedData } from '@/types';
import { cn } from '@/lib/utils';

const store = useToastStore();
const { items } = storeToRefs(store);
const page = usePage<SharedData>();

// Bridge server flash messages -> client toasts on every Inertia navigation.
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) store.success(flash.success);
        if (flash?.error) store.error(flash.error);
    },
    { deep: true, immediate: true },
);

const tone: Record<string, string> = {
    success: 'border-emerald-200 bg-white text-emerald-800',
    error: 'border-rose-200 bg-white text-rose-800',
    info: 'border-slate-200 bg-white text-slate-800',
};

const icon = { success: CheckCircle2, error: XCircle, info: Info };
</script>

<template>
    <div class="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2">
        <transition-group name="toast">
            <div
                v-for="t in items"
                :key="t.id"
                :class="cn('pointer-events-auto flex items-start gap-3 rounded-lg border px-4 py-3 shadow-lg', tone[t.variant])"
            >
                <component :is="icon[t.variant]" class="mt-0.5 size-5 shrink-0" />
                <p class="flex-1 text-sm font-medium">{{ t.message }}</p>
                <button class="text-slate-400 transition hover:text-slate-600" @click="store.dismiss(t.id)">
                    <X class="size-4" />
                </button>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
