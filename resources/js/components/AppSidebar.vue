<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import type { NavGroup } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Briefcase,
    CalendarDays,
    FileText,
    FolderOpen,
    Gavel,
    LayoutGrid,
    ListChecks,
    ScrollText,
    ShieldCheck,
    Users,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const { can } = usePermissions();

// Grouped, permission-gated navigation. Items without a `permission` are always
// visible; gated items are hidden when the user lacks the ability.
const groups: NavGroup[] = [
    {
        label: 'Workspace',
        items: [{ title: 'Dashboard', href: '/dashboard', icon: LayoutGrid }],
    },
    {
        label: 'Matters',
        items: [
            { title: 'Cases', href: '/cases', icon: Briefcase, permission: 'cases.view' },
            { title: 'Clients', href: '/clients', icon: Users, permission: 'clients.view' },
            { title: 'Hearings', href: '/hearings', icon: CalendarDays, permission: 'hearings.view' },
            { title: 'Tasks', href: '/tasks', icon: ListChecks, permission: 'tasks.view' },
        ],
    },
    {
        label: 'Records',
        items: [
            { title: 'Documents', href: '/documents', icon: FolderOpen, permission: 'documents.view' },
            { title: 'Evidence', href: '/evidence', icon: Gavel, permission: 'evidence.view' },
            { title: 'Legal Library', href: '/templates', icon: ScrollText, permission: 'templates.view' },
        ],
    },
    {
        label: 'Firm',
        items: [
            { title: 'Team', href: '/team', icon: UsersRound, permission: 'team.manage' },
            { title: 'Activity Log', href: '/activity', icon: ShieldCheck, permission: 'audit.view' },
        ],
    },
];

const visibleGroups = computed(() =>
    groups
        .map((g) => ({ ...g, items: g.items.filter((i) => !i.permission || can(i.permission)) }))
        .filter((g) => g.items.length > 0),
);

const isActive = (href: string) => page.url.startsWith(href);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup v-for="group in visibleGroups" :key="group.label" class="px-2 py-0">
                <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in group.items" :key="item.href">
                        <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.title">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
