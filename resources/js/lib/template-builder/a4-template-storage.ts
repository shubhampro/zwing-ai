import type { A4Block, A4PageBackground, A4PageSettings } from '@/lib/template-builder/a4';

export type A4TemplateCategory = 'tax-invoice' | 'retail' | 'report' | 'receipt' | 'blank' | 'custom';

export type A4TemplateDocument = {
    id: string;
    name: string;
    description: string;
    category: A4TemplateCategory;
    accent: string;
    isCustom: boolean;
    savedAt?: string;
    blocks: A4Block[];
    pageBackground: A4PageBackground;
    pageSettings: A4PageSettings;
};

const STORAGE_KEY = 'zwing-a4-saved-templates';

export function cloneA4Template(template: A4TemplateDocument): A4TemplateDocument {
    return JSON.parse(JSON.stringify(template)) as A4TemplateDocument;
}

export function loadSavedA4Templates(): A4TemplateDocument[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw) as A4TemplateDocument[];

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed.filter((item) => item.isCustom && item.id && item.name);
    } catch {
        return [];
    }
}

export function persistSavedA4Templates(templates: A4TemplateDocument[]): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(templates));
}

export function saveA4Template(template: A4TemplateDocument): A4TemplateDocument {
    const saved = loadSavedA4Templates();
    const next = cloneA4Template({
        ...template,
        isCustom: true,
        category: 'custom',
        savedAt: new Date().toISOString(),
    });

    const index = saved.findIndex((item) => item.id === next.id);

    if (index === -1) {
        persistSavedA4Templates([next, ...saved]);
    } else {
        const updated = [...saved];
        updated[index] = next;
        persistSavedA4Templates(updated);
    }

    return next;
}

export function deleteSavedA4Template(id: string): void {
    persistSavedA4Templates(loadSavedA4Templates().filter((item) => item.id !== id));
}

export function createCustomTemplateId(): string {
    return `custom-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
}
