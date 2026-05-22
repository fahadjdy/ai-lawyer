import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { reactive } from 'vue';

/**
 * Server-driven filtering with a debounced, history-preserving Inertia visit.
 * Used by index pages (cases, clients, documents…) to keep filter state in the
 * URL while avoiding a request on every keystroke.
 */
export function useFilters<T extends Record<string, unknown>>(routeName: string, initial: T, debounce = 300) {
    const filters = reactive({ ...initial }) as T;

    watchDebounced(
        () => ({ ...filters }),
        (value) => {
            const query = Object.fromEntries(
                Object.entries(value).filter(([, v]) => v !== '' && v !== null && v !== undefined),
            );

            router.get(route(routeName), query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        { debounce, deep: true },
    );

    const reset = () => {
        (Object.keys(filters) as Array<keyof T>).forEach((key) => {
            (filters[key] as unknown) = '';
        });
    };

    return { filters, reset };
}
