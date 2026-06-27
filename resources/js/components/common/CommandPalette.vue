<script setup lang="ts">
import StatusBadge from '@/components/common/StatusBadge.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useCommandPalette } from '@/composables/useCommandPalette';
import type { SearchResult } from '@/types';
import { router } from '@inertiajs/vue3';
import {
    Briefcase,
    CalendarDays,
    CheckSquare,
    CornerDownLeft,
    FileText,
    FolderOpen,
    Gavel,
    LayoutGrid,
    ListChecks,
    Loader2,
    ScrollText,
    Search,
    ShieldCheck,
    User,
    Users,
    UsersRound,
    type LucideIcon,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const { open, closePalette } = useCommandPalette();
const { can } = usePermissions();

const query = ref('');
const results = ref<SearchResult[]>([]);
const loading = ref(false);
const activeIndex = ref(0);
const inputEl = ref<HTMLInputElement | null>(null);
const listEl = ref<HTMLElement | null>(null);

const iconMap: Record<SearchResult['icon'], LucideIcon> = {
    briefcase: Briefcase,
    user: User,
    calendar: CalendarDays,
    check: CheckSquare,
    file: FileText,
    gavel: Gavel,
};

// Quick navigation shown when no query is typed — permission-gated jumps that
// mirror the sidebar so the palette is useful even before searching.
interface QuickLink {
    label: string;
    href: string;
    icon: LucideIcon;
    permission?: string;
}

const quickLinks = computed<QuickLink[]>(() =>
    (
        [
            { label: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
            { label: 'Cases', href: '/cases', icon: Briefcase, permission: 'cases.view' },
            { label: 'Clients', href: '/clients', icon: Users, permission: 'clients.view' },
            { label: 'Hearings', href: '/hearings', icon: CalendarDays, permission: 'hearings.view' },
            { label: 'Tasks', href: '/tasks', icon: ListChecks, permission: 'tasks.view' },
            { label: 'Documents', href: '/documents', icon: FolderOpen, permission: 'documents.view' },
            { label: 'Evidence', href: '/evidence', icon: Gavel, permission: 'evidence.view' },
            { label: 'Legal Library', href: '/templates', icon: ScrollText, permission: 'templates.view' },
            { label: 'Team', href: '/team', icon: UsersRound, permission: 'team.manage' },
            { label: 'Activity Log', href: '/activity', icon: ShieldCheck, permission: 'audit.view' },
        ] as QuickLink[]
    ).filter((l) => !l.permission || can(l.permission)),
);

const hasQuery = computed(() => query.value.trim().length > 0);

// The flat, selectable list the arrow keys traverse — results when searching,
// quick links otherwise.
const flatNav = computed<string[]>(() =>
    hasQuery.value ? results.value.map((r) => r.url) : quickLinks.value.map((l) => l.href),
);

// Group results by their `group` label, preserving the backend's ordering and
// tracking each row's global index so keyboard highlighting lines up.
const grouped = computed(() => {
    const order: SearchResult['group'][] = ['Cases', 'Clients', 'Hearings', 'Tasks'];
    const byGroup = new Map<string, { result: SearchResult; index: number }[]>();
    results.value.forEach((result, index) => {
        const bucket = byGroup.get(result.group) ?? [];
        bucket.push({ result, index });
        byGroup.set(result.group, bucket);
    });
    return order.filter((g) => byGroup.has(g)).map((g) => ({ label: g, items: byGroup.get(g)! }));
});

let controller: AbortController | null = null;
let debounce: ReturnType<typeof setTimeout> | null = null;

async function runSearch(term: string): Promise<void> {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    try {
        const res = await fetch(`/search?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        });
        const data = await res.json();
        results.value = data.results ?? [];
        activeIndex.value = 0;
    } catch (e) {
        if ((e as Error).name !== 'AbortError') results.value = [];
    } finally {
        loading.value = false;
    }
}

watch(query, (value) => {
    activeIndex.value = 0;
    if (debounce) clearTimeout(debounce);
    const term = value.trim();
    if (term.length < 2) {
        results.value = [];
        loading.value = false;
        controller?.abort();
        return;
    }
    debounce = setTimeout(() => runSearch(term), 180);
});

// Reset and focus whenever the palette opens.
watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        results.value = [];
        activeIndex.value = 0;
        nextTick(() => inputEl.value?.focus());
    }
});

function go(url: string): void {
    closePalette();
    router.visit(url);
}

function move(delta: number): void {
    const count = flatNav.value.length;
    if (!count) return;
    activeIndex.value = (activeIndex.value + delta + count) % count;
    nextTick(() => {
        listEl.value
            ?.querySelector<HTMLElement>(`[data-index="${activeIndex.value}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    });
}

function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        move(1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        move(-1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const url = flatNav.value[activeIndex.value];
        if (url) go(url);
    } else if (e.key === 'Escape') {
        e.preventDefault();
        closePalette();
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/40 p-4 backdrop-blur-sm sm:pt-[12vh]"
                @click.self="closePalette"
            >
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 translate-y-2 scale-[0.98]"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                >
                    <div
                        v-if="open"
                        class="flex max-h-[80vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-black/5"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Search"
                    >
                        <!-- Search input -->
                        <div class="flex items-center gap-3 border-b border-slate-100 px-4">
                            <Search class="size-5 shrink-0 text-slate-400" />
                            <input
                                ref="inputEl"
                                v-model="query"
                                type="text"
                                placeholder="Search cases, clients, hearings, tasks…"
                                class="h-14 w-full border-0 bg-transparent text-base text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                                autocomplete="off"
                                spellcheck="false"
                                @keydown="onKeydown"
                            />
                            <Loader2 v-if="loading" class="size-4 shrink-0 animate-spin text-slate-400" />
                            <kbd
                                class="hidden shrink-0 rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-400 sm:inline-block"
                            >
                                ESC
                            </kbd>
                        </div>

                        <!-- Results / quick nav -->
                        <div ref="listEl" class="flex-1 overflow-y-auto overscroll-contain p-2">
                            <!-- Search results, grouped -->
                            <template v-if="hasQuery">
                                <div v-if="grouped.length" class="space-y-3">
                                    <div v-for="section in grouped" :key="section.label">
                                        <p class="px-2 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                            {{ section.label }}
                                        </p>
                                        <button
                                            v-for="entry in section.items"
                                            :key="entry.result.id"
                                            :data-index="entry.index"
                                            type="button"
                                            class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left transition"
                                            :class="
                                                activeIndex === entry.index
                                                    ? 'bg-indigo-50 ring-1 ring-inset ring-indigo-100'
                                                    : 'hover:bg-slate-50'
                                            "
                                            @click="go(entry.result.url)"
                                            @mousemove="activeIndex = entry.index"
                                        >
                                            <span
                                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
                                                :class="activeIndex === entry.index ? 'bg-white text-indigo-600' : ''"
                                            >
                                                <component :is="iconMap[entry.result.icon]" class="size-4.5" />
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-medium text-slate-800">{{
                                                    entry.result.title
                                                }}</span>
                                                <span class="block truncate text-xs text-slate-400">{{
                                                    entry.result.subtitle
                                                }}</span>
                                            </span>
                                            <StatusBadge
                                                v-if="entry.result.badge"
                                                :label="entry.result.badge"
                                                :color="entry.result.color ?? 'slate'"
                                                :dot="false"
                                            />
                                            <CornerDownLeft
                                                v-if="activeIndex === entry.index"
                                                class="size-4 shrink-0 text-indigo-400"
                                            />
                                        </button>
                                    </div>
                                </div>

                                <!-- No matches -->
                                <div v-else-if="!loading" class="px-3 py-12 text-center">
                                    <Search class="mx-auto size-6 text-slate-300" />
                                    <p class="mt-3 text-sm font-medium text-slate-600">No results for "{{ query }}"</p>
                                    <p class="mt-1 text-xs text-slate-400">Try a case number, client name, or keyword.</p>
                                </div>
                            </template>

                            <!-- Quick navigation (empty query) -->
                            <template v-else>
                                <p class="px-2 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                    Jump to
                                </p>
                                <button
                                    v-for="(link, i) in quickLinks"
                                    :key="link.href"
                                    :data-index="i"
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left transition"
                                    :class="activeIndex === i ? 'bg-indigo-50 ring-1 ring-inset ring-indigo-100' : 'hover:bg-slate-50'"
                                    @click="go(link.href)"
                                    @mousemove="activeIndex = i"
                                >
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
                                        :class="activeIndex === i ? 'bg-white text-indigo-600' : ''"
                                    >
                                        <component :is="link.icon" class="size-4.5" />
                                    </span>
                                    <span class="flex-1 text-sm font-medium text-slate-700">{{ link.label }}</span>
                                    <CornerDownLeft v-if="activeIndex === i" class="size-4 shrink-0 text-indigo-400" />
                                </button>
                            </template>
                        </div>

                        <!-- Footer hints -->
                        <div
                            class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/60 px-4 py-2.5 text-[11px] text-slate-400"
                        >
                            <span class="flex items-center gap-3">
                                <span class="flex items-center gap-1">
                                    <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-sans">↑</kbd>
                                    <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-sans">↓</kbd>
                                    navigate
                                </span>
                                <span class="hidden items-center gap-1 sm:flex">
                                    <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-sans">↵</kbd>
                                    open
                                </span>
                            </span>
                            <span class="font-medium text-slate-500">LexCase</span>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
