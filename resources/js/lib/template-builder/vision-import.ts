import { importVision } from '@/routes/template-builder';

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

export type VisionImportResponse = {
    success: boolean;
    ejs?: string;
    warnings?: string[];
    provider?: string;
    model?: string;
    message?: string;
};

export async function importTemplateFromVision(
    image: Blob,
    refinement?: string,
): Promise<VisionImportResponse> {
    const formData = new FormData();
    formData.append('image', image, image instanceof File ? image.name : 'upload.png');

    if (refinement?.trim()) {
        formData.append('refinement', refinement.trim());
    }

    const response = await fetch(importVision.url(), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: formData,
        credentials: 'same-origin',
    });

    const json = (await response.json().catch(() => ({
        success: false,
        message: 'Invalid response from server.',
    }))) as VisionImportResponse;

    if (!response.ok) {
        return {
            success: false,
            message: json.message ?? `Request failed (${response.status})`,
        };
    }

    return json;
}
