export type Role = 'owner' | 'admin' | 'member' | 'viewer';

export interface TenantInfo {
    id: string;
    name: string;
    slug: string;
    branding: { primary_color?: string; accent_color?: string; logo_url?: string };
}

export interface AuthInfo {
    user: { id: string; name: string; email: string } | null;
    role: Role | null;
    permissions: string[];
    impersonating: boolean;
}

export interface SharedProps {
    app: { name: string };
    tenant: TenantInfo | null;
    auth: AuthInfo;
    flash: { status: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface Project {
    id: string;
    name: string;
    description: string | null;
    tasks_count?: number;
    created_at: string;
}

export interface Task {
    id: string;
    title: string;
    status: 'todo' | 'doing' | 'done';
    due_on: string | null;
}
