import { defineStore } from 'pinia';

export type ToastVariant = 'success' | 'error' | 'info';

export interface Toast {
    id: number;
    message: string;
    variant: ToastVariant;
}

let counter = 0;

/**
 * Lightweight toast notification store. Flash messages from Inertia and ad-hoc
 * client events are pushed here and rendered by <Toaster />.
 */
export const useToastStore = defineStore('toasts', {
    state: () => ({
        items: [] as Toast[],
    }),
    actions: {
        push(message: string, variant: ToastVariant = 'info', timeout = 4000) {
            const id = ++counter;
            this.items.push({ id, message, variant });
            setTimeout(() => this.dismiss(id), timeout);
        },
        success(message: string) {
            this.push(message, 'success');
        },
        error(message: string) {
            this.push(message, 'error');
        },
        dismiss(id: number) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    },
});
