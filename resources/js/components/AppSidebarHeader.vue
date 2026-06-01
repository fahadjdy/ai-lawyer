<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCommandPalette } from '@/composables/useCommandPalette';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, Search } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const { openPalette } = useCommandPalette();
const page = usePage<SharedData>();

const unread = computed(() => page.props.auth.unread_notifications ?? 0);

// Show the platform-appropriate shortcut hint (⌘K on macOS, Ctrl K elsewhere).
const shortcut = computed(() => {
    const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
    return isMac ? '⌘K' : 'Ctrl K';
});
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-white/95 px-4 backdrop-blur transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 sm:px-6"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <template v-if="index === breadcrumbs.length - 1">
                                    <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                                </template>
                                <template v-else>
                                    <BreadcrumbLink :href="item.href">
                                        {{ item.title }}
                                    </BreadcrumbLink>
                                </template>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
        </div>

        <!-- Right side: global search + notifications -->
        <div class="ml-auto flex items-center gap-2">
            <!-- Desktop: search field-style trigger -->
            <button
                type="button"
                class="hidden h-9 w-56 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-400 transition hover:border-slate-300 hover:bg-white lg:flex"
                @click="openPalette"
            >
                <Search class="size-4 shrink-0" />
                <span class="flex-1 text-left">Search…</span>
                <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-400">
                    {{ shortcut }}
                </kbd>
            </button>

            <!-- Mobile / tablet: compact search icon -->
            <button
                type="button"
                class="flex size-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 lg:hidden"
                aria-label="Search"
                @click="openPalette"
            >
                <Search class="size-5" />
            </button>

            <!-- Notifications -->
            <Link
                href="/notifications"
                class="relative flex size-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                aria-label="Notifications"
            >
                <Bell class="size-5" />
                <span
                    v-if="unread > 0"
                    class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold leading-4 text-white"
                >
                    {{ unread > 9 ? '9+' : unread }}
                </span>
            </Link>
        </div>
    </header>
</template>
