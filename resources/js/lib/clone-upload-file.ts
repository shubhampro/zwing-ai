/**
 * Create an independent in-memory copy of a user-selected file.
 *
 * Chrome throws ERR_UPLOAD_FILE_CHANGED when the same on-disk file is attached
 * to multiple FormData fields (e.g. the same CSV picked for Zwing and ERP).
 */
export async function cloneUploadFile(file: File): Promise<File> {
    const buffer = await file.arrayBuffer();

    return new File([buffer], file.name, {
        type: file.type || 'application/octet-stream',
        lastModified: Date.now(),
    });
}
