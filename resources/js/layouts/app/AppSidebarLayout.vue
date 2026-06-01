<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import MobileBottomNav from '@/components/MobileBottomNav.vue';
import CommandPalette from '@/components/common/CommandPalette.vue';
import Toaster from '@/components/common/Toaster.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <!-- Extra bottom padding on mobile so content clears the bottom tab bar. -->
            <div class="min-h-[calc(100svh-4rem)] bg-slate-50/60 pb-20 md:pb-0">
                <slot />
            </div>
        </AppContent>
        <MobileBottomNav />
    </AppShell>
    <CommandPalette />
    <Toaster />
</template>
