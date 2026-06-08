<script setup lang="ts">
import { renderMarkdown } from '@/lib/markdown';
import { computed } from 'vue';

const props = defineProps<{ content: string }>();
const html = computed(() => renderMarkdown(props.content));
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html — markdown-it runs with html:false, so output is escaped/safe -->
    <div class="chat-prose" v-html="html" />
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
