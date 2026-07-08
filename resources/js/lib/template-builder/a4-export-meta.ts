import type { A4Block, A4PageBackground, A4PageSettings } from '@/lib/template-builder/a4';

export type A4TemplateSnapshot = {
    v: 1;
    blocks: A4Block[];
    pageBackground: A4PageBackground;
    pageSettings: A4PageSettings;
};

const META_REGEX = /<!--\s*TB_TEMPLATE:([A-Za-z0-9+/=]+)\s*-->/;

function encodeBase64Json(value: unknown): string {
    const json = JSON.stringify(value);
    const bytes = new TextEncoder().encode(json);
    let binary = '';

    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return btoa(binary);
}

function decodeBase64Json<T>(encoded: string): T {
    const binary = atob(encoded);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return JSON.parse(new TextDecoder().decode(bytes)) as T;
}

function isSnapshot(value: unknown): value is A4TemplateSnapshot {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const snapshot = value as A4TemplateSnapshot;

    return snapshot.v === 1 && Array.isArray(snapshot.blocks);
}

/** Embeds block editor state in exported EJS for lossless re-import. */
export function embedSnapshotInHtml(html: string, snapshot: A4TemplateSnapshot): string {
    const encoded = encodeBase64Json(snapshot);

    return `<!-- TB_TEMPLATE:${encoded} -->\n${html}`;
}

/** Reads embedded block editor state from an exported EJS file. */
export function extractSnapshotFromSource(source: string): A4TemplateSnapshot | null {
    const match = source.match(META_REGEX);

    if (!match) {
        return null;
    }

    try {
        const snapshot = decodeBase64Json<A4TemplateSnapshot>(match[1].replace(/\s+/g, ''));

        return isSnapshot(snapshot) ? snapshot : null;
    } catch {
        return null;
    }
}

export function createSnapshot(
    blocks: A4Block[],
    pageBackground: A4PageBackground,
    pageSettings: A4PageSettings,
): A4TemplateSnapshot {
    return {
        v: 1,
        blocks,
        pageBackground,
        pageSettings,
    };
}
