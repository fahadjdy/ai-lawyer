<script setup lang="ts">
import MarkdownMessage from '@/components/chat/MarkdownMessage.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import { postJson } from '@/lib/http';
import { streamSSE } from '@/lib/sse';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Briefcase,
    Check,
    CheckCheck,
    Copy,
    Loader2,
    MessageSquarePlus,
    MoreHorizontal,
    Pencil,
    RotateCcw,
    Scale,
    Send,
    Sparkles,
    Square,
    ThumbsDown,
    ThumbsUp,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

interface Citation {
    type: string;
    label: string;
    title: string;
    url: string;
}
interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    citations: Citation[];
    rating: number | null;
    created_at?: string | null;
    streaming?: boolean;
    status?: string;
}
interface CaseRef {
    uuid: string;
    case_number: string | null;
    title: string;
}
interface SessionRow {
    uuid: string;
    title: string;
    last_message_at: string | null;
    case: CaseRef | null;
}
interface ActiveSession extends SessionRow {
    messages: Message[];
}
interface CaseRow {
    uuid: string;
    case_number: string | null;
    title: string;
}

const props = defineProps<{
    sessions: SessionRow[];
    activeSession: ActiveSession | null;
    cases: CaseRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Assistant', href: '/assistant' }];

const page = usePage<SharedData>();
const userInitials = computed(() => page.props.auth.user?.initials ?? 'You');

const sessionList = ref<SessionRow[]>([...props.sessions]);
const messages = ref<Message[]>(props.activeSession?.messages ? [...props.activeSession.messages] : []);
const caseValue = ref<string | null>(props.activeSession?.case?.uuid ?? null);
const activeUuid = computed(() => props.activeSession?.uuid ?? null);

const input = ref('');
const streaming = ref(false);
const error = ref<string | null>(null);
const abort = ref<AbortController | null>(null);
const scroller = ref<HTMLElement | null>(null);
const mobilePane = ref<'list' | 'chat'>(props.activeSession ? 'chat' : 'list');

const editingId = ref<number | null>(null);
const editText = ref('');
const copiedId = ref<number | null>(null);

const caseOptions = computed(() =>
    props.cases.map((c) => ({
        value: c.uuid,
        label: c.case_number ? `${c.case_number} — ${c.title}` : c.title,
        hint: c.title,
    })),
);

const suggestions = [
    'What are the essential ingredients of cheating under IPC 420 / BNS 318?',
    'What hearings do I have coming up in the next 7 days?',
    'Draft a checklist for filing an anticipatory bail application.',
];

const lastAssistantId = computed(() => {
    for (let i = messages.value.length - 1; i >= 0; i--) {
        if (messages.value[i].role === 'assistant') return messages.value[i].id;
    }
    return null;
});

watch(
    () => props.sessions,
    (v) => {
        sessionList.value = [...v];
    },
);
watch(
    () => props.activeSession?.uuid,
    () => {
        messages.value = props.activeSession?.messages ? [...props.activeSession.messages] : [];
        caseValue.value = props.activeSession?.case?.uuid ?? null;
        mobilePane.value = props.activeSession ? 'chat' : 'list';
        editingId.value = null;
        scrollToBottom();
    },
);
watch(
    () => props.activeSession?.case?.uuid,
    (v) => {
        caseValue.value = v ?? null;
    },
);

async function scrollToBottom() {
    await nextTick();
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
}
onMounted(scrollToBottom);

function pushMessage(m: Message): Message {
    const r = reactive(m) as Message;
    messages.value.push(r);
    return r;
}
function removeMessage(target: Message) {
    messages.value = messages.value.filter((m) => m !== target);
}

function newChat() {
    stopStream();
    router.post('/assistant/sessions', {}, { preserveScroll: true });
}

function openSession(uuid: string) {
    if (uuid === activeUuid.value) {
        mobilePane.value = 'chat';
        return;
    }
    stopStream();
    router.get('/assistant', { session: uuid }, { preserveScroll: true, preserveState: false });
}

function attachCase(uuid: string | null) {
    caseValue.value = uuid;
    if (!activeUuid.value) return;
    router.put(`/assistant/sessions/${activeUuid.value}`, { case: uuid ?? '' }, { preserveScroll: true });
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

function stopStream() {
    abort.value?.abort();
}

async function send() {
    const text = input.value.trim();
    if (!text || streaming.value || !activeUuid.value) return;
    input.value = '';
    await runStream(`/assistant/sessions/${activeUuid.value}/stream`, { content: text }, text);
}

async function regenerate() {
    if (streaming.value || !activeUuid.value) return;
    // Drop the trailing assistant bubble locally; the server drops it too.
    for (let i = messages.value.length - 1; i >= 0; i--) {
        if (messages.value[i].role === 'assistant') {
            messages.value.splice(i, 1);
            break;
        }
    }
    await runStream(`/assistant/sessions/${activeUuid.value}/regenerate`, {});
}

function startEdit(m: Message) {
    if (streaming.value || m.id < 0) return;
    editingId.value = m.id;
    editText.value = m.content;
}
function cancelEdit() {
    editingId.value = null;
}
async function saveEdit(m: Message) {
    const text = editText.value.trim();
    if (!text || streaming.value) return;
    const idx = messages.value.findIndex((x) => x.id === m.id);
    editingId.value = null;
    if (idx === -1) return;
    m.content = text;
    messages.value = messages.value.slice(0, idx + 1);
    await runStream(`/assistant/sessions/${activeUuid.value}/messages/${m.id}/edit`, { content: text });
}

async function copyMessage(m: Message) {
    try {
        await navigator.clipboard.writeText(m.content);
        copiedId.value = m.id;
        setTimeout(() => {
            if (copiedId.value === m.id) copiedId.value = null;
        }, 1500);
    } catch {
        /* clipboard unavailable */
    }
}

async function rate(m: Message, value: 1 | -1) {
    if (m.id < 0 || !activeUuid.value) return;
    const next = m.rating === value ? 0 : value;
    const previous = m.rating;
    m.rating = next === 0 ? null : next;
    const { ok, data } = await postJson<{ rating: number | null }>(
        `/assistant/sessions/${activeUuid.value}/messages/${m.id}/feedback`,
        { rating: next },
    );
    m.rating = ok ? (data?.rating ?? null) : previous;
}

async function runStream(url: string, body: unknown, userText?: string) {
    error.value = null;
    streaming.value = true;

    let user: Message | null = null;
    if (userText !== undefined) {
        user = pushMessage({ id: -Date.now(), role: 'user', content: userText, citations: [], rating: null });
    }
    const assistant = pushMessage({
        id: -(Date.now() + 1),
        role: 'assistant',
        content: '',
        citations: [],
        rating: null,
        streaming: true,
        status: 'Thinking…',
    });
    await scrollToBottom();

    const controller = new AbortController();
    abort.value = controller;

    try {
        await streamSSE(url, body, {
            signal: controller.signal,
            onEvent: (event, data) => {
                if (event === 'user' && data?.message && user) {
                    Object.assign(user, data.message);
                } else if (event === 'status') {
                    assistant.status = data?.text ?? '';
                } else if (event === 'delta') {
                    assistant.status = undefined;
                    assistant.content += data?.text ?? '';
                    scrollToBottom();
                } else if (event === 'done') {
                    if (data?.reply) {
                        Object.assign(assistant, data.reply, { streaming: false, status: undefined });
                    } else {
                        removeMessage(assistant);
                    }
                    if (data?.session) patchSession(data.session);
                } else if (event === 'error') {
                    error.value = data?.message ?? 'The assistant could not reply.';
                    removeMessage(assistant);
                    if (user && user.id < 0) removeMessage(user);
                }
            },
        });
    } catch (e: unknown) {
        if (e instanceof DOMException && e.name === 'AbortError') {
            // User stopped — keep whatever streamed so far.
            if (!assistant.content) removeMessage(assistant);
        } else {
            error.value = 'Network error — please try again.';
            removeMessage(assistant);
        }
    } finally {
        assistant.streaming = false;
        assistant.status = undefined;
        streaming.value = false;
        abort.value = null;
        await scrollToBottom();
    }
}

function patchSession(s: { uuid: string; title: string; last_message_at: string | null }) {
    const idx = sessionList.value.findIndex((x) => x.uuid === s.uuid);
    if (idx === -1) return;
    const existing = sessionList.value[idx];
    existing.title = s.title;
    existing.last_message_at = s.last_message_at;
    sessionList.value.splice(idx, 1);
    sessionList.value.unshift(existing);
}

function citationIcon(type: string) {
    if (type === 'case') return Briefcase;
    if (type === 'section') return Scale;
    return Check;
}

// Rename
const renaming = ref<SessionRow | null>(null);
const renameTitle = ref('');
function startRename(s: SessionRow) {
    renaming.value = s;
    renameTitle.value = s.title;
}
function saveRename() {
    const s = renaming.value;
    const title = renameTitle.value.trim();
    if (s && title) {
        router.put(`/assistant/sessions/${s.uuid}`, { title }, { preserveScroll: true });
    }
    renaming.value = null;
}

// Delete
const deleting = ref<SessionRow | null>(null);
function confirmDelete() {
    const s = deleting.value;
    if (s) router.delete(`/assistant/sessions/${s.uuid}`, { preserveScroll: true });
    deleting.value = null;
}
</script>

<template>
    <Head title="AI Assistant" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100svh-9rem)] overflow-hidden bg-white md:h-[calc(100svh-4rem)]">
            <!-- Sessions sidebar -->
            <aside
                class="w-full shrink-0 flex-col border-r border-slate-200 bg-slate-50/60 md:flex md:w-72"
                :class="mobilePane === 'list' ? 'flex' : 'hidden md:flex'"
            >
                <div class="flex items-center justify-between gap-2 p-3">
                    <h2 class="px-1 text-sm font-semibold text-slate-900">Conversations</h2>
                    <Button size="sm" variant="outline" @click="newChat">
                        <MessageSquarePlus class="size-4" />
                        New
                    </Button>
                </div>

                <div class="flex-1 overflow-y-auto px-2 pb-3">
                    <p v-if="!sessionList.length" class="px-2 py-6 text-center text-xs text-slate-400">
                        No conversations yet. Start a new chat to ask the assistant anything.
                    </p>

                    <ul v-else class="space-y-1">
                        <li v-for="s in sessionList" :key="s.uuid" class="group relative">
                            <button
                                type="button"
                                class="flex w-full flex-col items-start gap-0.5 rounded-lg px-3 py-2 pr-9 text-left transition-colors"
                                :class="s.uuid === activeUuid ? 'bg-indigo-50 text-indigo-900' : 'text-slate-600 hover:bg-slate-100'"
                                @click="openSession(s.uuid)"
                            >
                                <span class="line-clamp-1 w-full text-sm font-medium">{{ s.title }}</span>
                                <span class="flex w-full items-center gap-1.5 text-[11px] text-slate-400">
                                    <template v-if="s.case">
                                        <Briefcase class="size-3 shrink-0" />
                                        <span class="line-clamp-1">{{ s.case.case_number || s.case.title }}</span>
                                    </template>
                                    <span v-else-if="s.last_message_at">{{ formatDate(s.last_message_at, true) }}</span>
                                    <span v-else>New chat</span>
                                </span>
                            </button>

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        class="absolute right-1.5 top-1.5 rounded-md p-1.5 text-slate-400 opacity-0 transition hover:bg-slate-200 hover:text-slate-600 focus:opacity-100 group-hover:opacity-100"
                                        @click.stop
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="startRename(s)"><Pencil class="size-4" /> Rename</DropdownMenuItem>
                                    <DropdownMenuItem class="text-rose-600 focus:text-rose-600" @click="deleting = s">
                                        <Trash2 class="size-4" /> Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </li>
                    </ul>
                </div>
            </aside>

            <!-- Conversation pane -->
            <section class="min-w-0 flex-1 flex-col" :class="mobilePane === 'chat' ? 'flex' : 'hidden md:flex'">
                <template v-if="activeSession">
                    <!-- Header: case context -->
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-3 py-2.5 sm:px-4">
                        <button type="button" class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 md:hidden" @click="mobilePane = 'list'">
                            <ArrowLeft class="size-4" />
                        </button>
                        <div class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                            <Briefcase class="size-3.5" />
                            <span>Context case</span>
                        </div>
                        <div class="w-full min-w-0 sm:w-72">
                            <SearchableSelect
                                :model-value="caseValue"
                                :options="caseOptions"
                                placeholder="None — general legal Q&A"
                                clearable
                                @update:model-value="attachCase"
                            />
                        </div>
                    </div>

                    <!-- Messages -->
                    <div ref="scroller" class="flex-1 overflow-y-auto px-3 py-5 sm:px-6">
                        <div class="mx-auto max-w-3xl space-y-5">
                            <!-- Intro when empty -->
                            <div v-if="!messages.length" class="pt-6 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                                    <Sparkles class="size-6" />
                                </span>
                                <h3 class="text-base font-semibold text-slate-900">Ask the legal assistant</h3>
                                <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                                    Indian law, procedure, drafting or strategy — and it can look up your cases, hearings and clients, or create a task. Attach a
                                    case above to ground the answers.
                                </p>
                                <div class="mx-auto mt-5 flex max-w-xl flex-col gap-2">
                                    <button
                                        v-for="(sug, i) in suggestions"
                                        :key="i"
                                        type="button"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-slate-600 transition hover:border-indigo-300 hover:bg-indigo-50/50"
                                        @click="input = sug"
                                    >
                                        {{ sug }}
                                    </button>
                                </div>
                            </div>

                            <!-- Message thread -->
                            <div v-for="m in messages" :key="m.id" class="group flex gap-3" :class="m.role === 'user' ? 'flex-row-reverse' : ''">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                    :class="m.role === 'user' ? 'bg-slate-200 text-slate-600' : 'bg-indigo-600 text-white'"
                                >
                                    <Sparkles v-if="m.role === 'assistant'" class="size-4" />
                                    <template v-else>{{ userInitials }}</template>
                                </span>

                                <div class="min-w-0 max-w-[85%]" :class="m.role === 'user' ? 'flex flex-col items-end' : ''">
                                    <!-- Editing a user message -->
                                    <div v-if="editingId === m.id" class="w-full min-w-[16rem]">
                                        <textarea
                                            v-model="editText"
                                            rows="3"
                                            class="w-full resize-none rounded-xl border border-indigo-300 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                        />
                                        <div class="mt-1.5 flex justify-end gap-2">
                                            <Button size="sm" variant="ghost" @click="cancelEdit">Cancel</Button>
                                            <Button size="sm" :disabled="!editText.trim()" @click="saveEdit(m)">Save &amp; resend</Button>
                                        </div>
                                    </div>

                                    <template v-else>
                                        <!-- Bubble -->
                                        <div
                                            class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed"
                                            :class="
                                                m.role === 'user'
                                                    ? 'whitespace-pre-wrap break-words rounded-tr-sm bg-indigo-600 text-white'
                                                    : 'rounded-tl-sm bg-slate-100 text-slate-800'
                                            "
                                        >
                                            <template v-if="m.role === 'assistant'">
                                                <MarkdownMessage v-if="m.content" :content="m.content" />
                                                <!-- Live progress: current action + indeterminate bar -->
                                                <div
                                                    v-if="m.streaming && (m.status || !m.content)"
                                                    class="ai-progress"
                                                    :class="m.content ? 'mt-3' : ''"
                                                >
                                                    <div class="flex items-center gap-2 text-xs font-medium text-indigo-700">
                                                        <Loader2 class="size-3.5 shrink-0 animate-spin" />
                                                        <span class="line-clamp-1">{{ m.status || 'Thinking…' }}</span>
                                                    </div>
                                                    <div class="ai-progress-track">
                                                        <div class="ai-progress-bar" />
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else>{{ m.content }}</template>
                                        </div>

                                        <!-- Citations -->
                                        <div v-if="m.role === 'assistant' && !m.streaming && m.citations?.length" class="mt-2 flex flex-wrap gap-1.5">
                                            <Link
                                                v-for="(c, ci) in m.citations"
                                                :key="ci"
                                                :href="c.url"
                                                class="inline-flex max-w-[16rem] items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] text-slate-600 transition hover:border-indigo-300 hover:text-indigo-700"
                                                :title="c.title"
                                            >
                                                <component :is="citationIcon(c.type)" class="size-3 shrink-0 text-slate-400" />
                                                <span class="font-medium">{{ c.label }}</span>
                                                <span class="truncate text-slate-400">· {{ c.title }}</span>
                                            </Link>
                                        </div>

                                        <!-- Action bar -->
                                        <div
                                            v-if="m.role === 'assistant' && !m.streaming"
                                            class="mt-1.5 flex items-center gap-0.5 text-slate-400 opacity-0 transition group-hover:opacity-100"
                                        >
                                            <button type="button" class="rounded-md p-1.5 hover:bg-slate-100 hover:text-slate-600" title="Copy" @click="copyMessage(m)">
                                                <CheckCheck v-if="copiedId === m.id" class="size-3.5 text-emerald-500" />
                                                <Copy v-else class="size-3.5" />
                                            </button>
                                            <button
                                                v-if="m.id === lastAssistantId"
                                                type="button"
                                                class="rounded-md p-1.5 hover:bg-slate-100 hover:text-slate-600 disabled:opacity-40"
                                                title="Regenerate"
                                                :disabled="streaming"
                                                @click="regenerate"
                                            >
                                                <RotateCcw class="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md p-1.5 hover:bg-slate-100"
                                                :class="m.rating === 1 ? 'text-emerald-600' : 'hover:text-slate-600'"
                                                title="Helpful"
                                                @click="rate(m, 1)"
                                            >
                                                <ThumbsUp class="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md p-1.5 hover:bg-slate-100"
                                                :class="m.rating === -1 ? 'text-rose-600' : 'hover:text-slate-600'"
                                                title="Not helpful"
                                                @click="rate(m, -1)"
                                            >
                                                <ThumbsDown class="size-3.5" />
                                            </button>
                                        </div>

                                        <!-- Edit (user) -->
                                        <button
                                            v-if="m.role === 'user' && m.id > 0 && !streaming"
                                            type="button"
                                            class="mt-1 flex items-center gap-1 text-[11px] text-slate-400 opacity-0 transition hover:text-slate-600 group-hover:opacity-100"
                                            @click="startEdit(m)"
                                        >
                                            <Pencil class="size-3" /> Edit
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Composer -->
                    <div class="border-t border-slate-200 px-3 py-3 sm:px-6">
                        <div class="mx-auto max-w-3xl">
                            <div
                                v-if="error"
                                class="mb-2 flex items-start gap-2 rounded-lg border border-rose-100 bg-rose-50 p-2.5 text-xs text-rose-700"
                            >
                                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                                <span class="flex-1">{{ error }}</span>
                                <button type="button" @click="error = null"><X class="size-3.5" /></button>
                            </div>

                            <div
                                class="flex items-end gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm focus-within:border-indigo-400 focus-within:ring-1 focus-within:ring-indigo-400"
                            >
                                <textarea
                                    v-model="input"
                                    rows="1"
                                    :disabled="streaming"
                                    placeholder="Ask anything — about a case, the calendar, or Indian law…"
                                    class="max-h-40 flex-1 resize-none bg-transparent px-2 py-1.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none disabled:opacity-60"
                                    @keydown="onKeydown"
                                />
                                <Button v-if="streaming" size="icon" variant="outline" class="size-9 shrink-0 rounded-xl" title="Stop" @click="stopStream">
                                    <Square class="size-4" />
                                </Button>
                                <Button v-else size="icon" :disabled="!input.trim()" class="size-9 shrink-0 rounded-xl" @click="send">
                                    <Send class="size-4" />
                                </Button>
                            </div>
                            <p class="mt-2 text-center text-[11px] text-slate-400">
                                AI-generated · informational only, for a lawyer's own judgement — not legal advice. Enter to send, Shift+Enter for a new line.
                            </p>
                        </div>
                    </div>
                </template>

                <!-- No session selected -->
                <div v-else class="flex flex-1 items-center justify-center p-6">
                    <EmptyState
                        :icon="Sparkles"
                        title="Your AI legal assistant"
                        description="Start a conversation to ask anything about your cases or Indian law. Attach a case for context-aware answers."
                    >
                        <template #action>
                            <Button @click="newChat"><MessageSquarePlus class="size-4" /> New chat</Button>
                        </template>
                    </EmptyState>
                </div>
            </section>
        </div>

        <!-- Rename dialog -->
        <Dialog :open="renaming !== null" @update:open="(v) => { if (!v) renaming = null; }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Rename conversation</DialogTitle>
                </DialogHeader>
                <Input v-model="renameTitle" maxlength="120" placeholder="Conversation title" @keydown.enter="saveRename" />
                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" @click="renaming = null">Cancel</Button>
                    <Button type="button" :disabled="!renameTitle.trim()" @click="saveRename">Save</Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation -->
        <ConfirmDialog
            :open="deleting !== null"
            title="Delete conversation?"
            description="This permanently deletes the conversation and all its messages. This cannot be undone."
            confirm-label="Delete"
            @update:open="(v) => { if (!v) deleting = null; }"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>

<style scoped>
/* Indeterminate progress bar shown while the assistant is working (retrieving,
   running a tool, or composing) — a moving sliver sweeping left to right. */
.ai-progress-track {
    position: relative;
    height: 3px;
    width: 100%;
    max-width: 18rem;
    margin-top: 0.4rem;
    overflow: hidden;
    border-radius: 9999px;
    background: #e0e7ff;
}
.ai-progress-bar {
    position: absolute;
    top: 0;
    height: 100%;
    width: 40%;
    border-radius: 9999px;
    background: linear-gradient(90deg, #a5b4fc, #6366f1);
    animation: ai-indeterminate 1.2s ease-in-out infinite;
}
@keyframes ai-indeterminate {
    0% {
        left: -40%;
    }
    100% {
        left: 100%;
    }
}
@media (prefers-reduced-motion: reduce) {
    .ai-progress-bar {
        animation-duration: 2.4s;
    }
}
</style>
