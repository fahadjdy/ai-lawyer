import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedData } from '@/types';

/**
 * Permission/role helpers backed by the Inertia-shared auth payload. Mirrors
 * the server-side Spatie permission checks so the UI can hide actions the user
 * isn't allowed to perform (defence-in-depth — the server still authorises).
 */
export function usePermissions() {
    const page = usePage<SharedData>();

    const permissions = computed(() => page.props.auth.user?.permissions ?? []);
    const roles = computed(() => page.props.auth.user?.roles ?? []);

    const can = (permission: string | string[]): boolean => {
        const list = Array.isArray(permission) ? permission : [permission];
        return list.some((p) => permissions.value.includes(p));
    };

    const hasRole = (role: string): boolean => roles.value.includes(role);

    return { permissions, roles, can, hasRole };
}
