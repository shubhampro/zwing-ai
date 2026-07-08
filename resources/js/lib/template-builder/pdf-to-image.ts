import * as pdfjs from 'pdfjs-dist';

pdfjs.GlobalWorkerOptions.workerSrc = new URL(
    'pdfjs-dist/build/pdf.worker.min.mjs',
    import.meta.url,
).toString();

/**
 * Renders the first page of a PDF to a PNG blob for vision import.
 */
export async function pdfFirstPageToPngBlob(file: File, scale = 2): Promise<Blob> {
    const bytes = await file.arrayBuffer();
    const pdf = await pdfjs.getDocument({ data: bytes }).promise;
    const page = await pdf.getPage(1);
    const viewport = page.getViewport({ scale });
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Could not create canvas context for PDF rendering.');
    }

    canvas.width = viewport.width;
    canvas.height = viewport.height;

    await page.render({
        canvasContext: context,
        viewport,
    }).promise;

    const blob = await new Promise<Blob>((resolve, reject) => {
        canvas.toBlob(
            (result) => {
                if (result) {
                    resolve(result);

                    return;
                }

                reject(new Error('Failed to convert PDF page to PNG.'));
            },
            'image/png',
            0.92,
        );
    });

    return blob;
}

/**
 * Normalizes an uploaded image or PDF file to a PNG/JPEG blob for the vision API.
 */
export async function normalizeDocumentToImageBlob(file: File): Promise<Blob> {
    const mime = file.type.toLowerCase();

    if (mime === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
        return pdfFirstPageToPngBlob(file);
    }

    if (mime.startsWith('image/')) {
        return file;
    }

    throw new Error('Unsupported file type. Upload PNG, JPEG, WebP, or PDF.');
}
