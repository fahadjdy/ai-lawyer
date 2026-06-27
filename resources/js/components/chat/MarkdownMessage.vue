<script setup lang="ts">
import ChartBlock, { type ChartSpec } from '@/components/chat/ChartBlock.vue';
import { renderMarkdown } from '@/lib/markdown';
import { computed } from 'vue';

const props = defineProps<{ content: string }>();

type Segment = { type: 'html'; html: string } | { type: 'chart'; chart: ChartSpec };

// Matches a complete ```chart … ``` fenced block. Incomplete blocks (still
// streaming, no closing fence) simply stay in the prose until they finish.
const CHART_FENCE = /```[ \t]*chart[ \t]*\r?\n([\s\S]*?)```/gi;

function parseChart(raw: string): ChartSpec | null {
    try {
        const spec = JSON.parse(raw.trim());
        if (!spec || !['pie', 'donut', 'bar'].includes(spec.type) || !Array.isArray(spec.data)) return null;
        const data = spec.data
            .filter((d: unknown): d is { label: unknown; value: unknown } => !!d && typeof d === 'object')
            .map((d: { label: unknown; value: unknown }) => ({ label: String(d.label ?? '—'), value: Number(d.value) }))
            .filter((d: { value: number }) => Number.isFinite(d.value));
        if (!data.length) return null;
        return { type: spec.type, title: typeof spec.title === 'string' ? spec.title : undefined, data };
    } catch {
        return null;
    }
}

const segments = computed<Segment[]>(() => {
    const content = props.content ?? '';
    const out: Segment[] = [];
    let last = 0;
    let m: RegExpExecArray | null;
    CHART_FENCE.lastIndex = 0;
    while ((m = CHART_FENCE.exec(content)) !== null) {
        const spec = parseChart(m[1]);
        // Only peel out the block if it parses; otherwise leave it in the prose.
        if (!spec) continue;
        if (m.index > last) out.push({ type: 'html', html: renderMarkdown(content.slice(last, m.index)) });
        out.push({ type: 'chart', chart: spec });
        last = m.index + m[0].length;
    }
    if (last < content.length) out.push({ type: 'html', html: renderMarkdown(content.slice(last)) });
    return out;
});
</script>

<template>
    <div>
        <template v-for="(seg, i) in segments" :key="i">
            <!-- eslint-disable-next-line vue/no-v-html — markdown-it runs with html:false, so output is escaped/safe -->
            <div v-if="seg.type === 'html'" class="chat-prose" v-html="seg.html" />
            <ChartBlock v-else :spec="seg.chart" />
        </template>
    </div>
</template>

<style scoped>
.chat-prose {
    font-size: 0.875rem;
    line-height: 1.65;
    color: #1e293b;
    word-break: break-word;
}
.chat-prose :deep(> *:first-child) {
    margin-top: 0;
}
.chat-prose :deep(> *:last-child) {
    margin-bottom: 0;
}
.chat-prose :deep(p) {
    margin: 0.5rem 0;
}
.chat-prose :deep(h1),
.chat-prose :deep(h2),
.chat-prose :deep(h3),
.chat-prose :deep(h4) {
    margin: 0.9rem 0 0.4rem;
    font-weight: 600;
    line-height: 1.3;
    color: #0f172a;
}
.chat-prose :deep(h1) {
    font-size: 1.05rem;
}
.chat-prose :deep(h2) {
    font-size: 1rem;
}
.chat-prose :deep(h3),
.chat-prose :deep(h4) {
    font-size: 0.9rem;
}
.chat-prose :deep(ul),
.chat-prose :deep(ol) {
    margin: 0.5rem 0;
    padding-left: 1.25rem;
}
.chat-prose :deep(ul) {
    list-style: disc;
}
.chat-prose :deep(ol) {
    list-style: decimal;
}
.chat-prose :deep(li) {
    margin: 0.2rem 0;
}
.chat-prose :deep(li > ul),
.chat-prose :deep(li > ol) {
    margin: 0.2rem 0;
}
.chat-prose :deep(strong) {
    font-weight: 600;
    color: #0f172a;
}
.chat-prose :deep(a) {
    color: #4f46e5;
    text-decoration: underline;
    text-underline-offset: 2px;
}
.chat-prose :deep(code) {
    background: #f1f5f9;
    padding: 0.1rem 0.3rem;
    border-radius: 0.25rem;
    font-size: 0.8125rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.chat-prose :deep(pre) {
    background: #0f172a;
    color: #e2e8f0;
    padding: 0.75rem 0.9rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 0.6rem 0;
    font-size: 0.8125rem;
    line-height: 1.5;
}
.chat-prose :deep(pre code) {
    background: transparent;
    padding: 0;
    color: inherit;
}
.chat-prose :deep(blockquote) {
    border-left: 3px solid #c7d2fe;
    padding-left: 0.75rem;
    margin: 0.6rem 0;
    color: #475569;
}
.chat-prose :deep(table) {
    border-collapse: collapse;
    margin: 0.6rem 0;
    font-size: 0.8125rem;
    width: 100%;
}
.chat-prose :deep(th),
.chat-prose :deep(td) {
    border: 1px solid #e2e8f0;
    padding: 0.35rem 0.55rem;
    text-align: left;
}
.chat-prose :deep(th) {
    background: #f8fafc;
    font-weight: 600;
}
.chat-prose :deep(hr) {
    border: none;
    border-top: 1px solid #e2e8f0;
    margin: 0.8rem 0;
}
</style>
