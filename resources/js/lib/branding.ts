import type { TenantInfo } from '@/types';

const HEX = /^#[0-9a-f]{6}$/i;

/** Contraste simples (YIQ) para texto sobre a cor primária. */
function contrast(hex: string): string {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);

    return (r * 299 + g * 587 + b * 114) / 1000 >= 140 ? '#111827' : '#ffffff';
}

export function applyBranding(tenant: TenantInfo | null): void {
    const root = document.documentElement.style;
    const primary = tenant?.branding.primary_color;
    const accent = tenant?.branding.accent_color;

    if (primary && HEX.test(primary)) {
        root.setProperty('--brand-primary', primary);
        root.setProperty('--brand-primary-contrast', contrast(primary));
    } else {
        root.removeProperty('--brand-primary');
        root.removeProperty('--brand-primary-contrast');
    }

    if (accent && HEX.test(accent)) {
        root.setProperty('--brand-accent', accent);
    } else {
        root.removeProperty('--brand-accent');
    }
}
