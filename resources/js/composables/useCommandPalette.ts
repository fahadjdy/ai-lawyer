import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Global state + keyboard wiring for the command palette (⌘K / Ctrl+K).
 *
 * The `open` ref is module-level (singleton) so the trigger button in the
 * header and the palette component itself share one source of truth. The
 * keyboard listener is reference-counted: it's attached once while any consumer
 * is mounted and torn down when the last one unmounts.
 */
const open = ref(false);
let listeners = 0;

function isTypingContext(target: EventTarget | null): boolean {
    const el = target as HTMLElement | null;
    if (!el) return false;
    const tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
}

function onKeydown(e: KeyboardEvent): void {
    // ⌘K / Ctrl+K — the canonical command-palette shortcut.
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        open.value = !open.value;
        return;
    }

    // "/" opens the palette too — but only when the user isn't already typing.
    if (e.key === '/' && !isTypingContext(e.target) && !open.value) {
        e.preventDefault();
        open.value = true;
    }
}

export function useCommandPalette() {
    onMounted(() => {
        if (listeners === 0) {
            window.addEventListener('keydown', onKeydown);
        }
        listeners += 1;
    });

    onBeforeUnmount(() => {
        listeners -= 1;
        if (listeners === 0) {
            window.removeEventListener('keydown', onKeydown);
        }
    });

    return {
        open,
        openPalette: () => (open.value = true),
        closePalette: () => (open.value = false),
    };
}
