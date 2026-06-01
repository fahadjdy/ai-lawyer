<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { Link, usePage } from '@inertiajs/vue3';
import { Briefcase, LayoutGrid, ListChecks, Menu, Users, type LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Mobile-only bottom tab bar. On phones the sidebar is hidden behind a
 * hamburger, so this surfaces the primary destinations one tap away — the
 * native-app pattern users expect. "More" opens the full sidebar sheet.
 */
const page = usePage();
const { can } = usePermissions();
const { setOpenMobile } = useSidebar();

interface Tab {
    label: string;
    href: string;
    icon: LucideIcon;
    permission?: string;
}

const tabs = computed<Tab[]>(() =>
    (
        [
            { label: 'Home', href: '/dashboard', icon: LayoutGrid },
            { label: 'Cases', href: '/cases', icon: Briefcase, permission: 'cases.view' },
            { label: 'Clients', href: '/clients', icon: Users, permission: 'clients.view' },
            { label: 'Tasks', href: '/tasks', icon: ListChecks, permission: 'tasks.view' },
        ] as Tab[]
    ).filter((t) => !t.permission || can(t.permission)),
);

const isActive = (href: string) => page.url.startsWith(href);
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur md:hidden"
        style="padding-bottom: env(safe-area-inset-bottom)"
    >
        <div class="flex">
            <Link
                v-for="tab in tabs"
                :key="tab.href"
                :href="tab.href"
                class="relative flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium transition-colors"
                :class="isActive(tab.href) ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600'"
            >
                <span v-if="isActive(tab.href)" class="absolute top-0 h-0.5 w-8 rounded-full bg-indigo-600" />
                <component :is="tab.icon" class="size-5" />
                {{ tab.label }}
            </Link>
            <button
                type="button"
                class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium text-slate-400 transition-colors hover:text-slate-600"
                @click="setOpenMobile(true)"
            >
                <Menu class="size-5" />
                More
            </button>
        </div>
    </nav>
</template>
