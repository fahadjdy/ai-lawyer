import type { LucideIcon } from 'lucide-vue-next';

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string;
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

/** Normalised enum payload emitted by backend resources. */
export interface EnumOption {
    value: string;
    label: string;
    color: string;
}

/** A single hit returned by the global command-palette search endpoint. */
export interface SearchResult {
    id: string;
    group: 'Cases' | 'Clients' | 'Hearings' | 'Tasks';
    type: 'case' | 'client' | 'hearing' | 'task';
    icon: 'briefcase' | 'user' | 'calendar' | 'check';
    title: string;
    subtitle: string;
    badge: string | null;
    color: string | null;
    url: string;
}

export interface AuthUser {
    id: string;
    name: string;
    email: string;
    designation: string | null;
    initials: string;
    avatar_url: string | null;
    team: { id: string; name: string } | null;
    roles: string[];
    permissions: string[];
}

export interface Auth {
    user: AuthUser | null;
    unread_notifications: number;
}

export interface FlashMessages {
    success?: string | null;
    error?: string | null;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: FlashMessages;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

/** Laravel paginator shape (resource collection or ->through()). */
export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
}

// Kept for compatibility with starter-kit components that import `User`.
export interface User {
    id: number | string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
}
