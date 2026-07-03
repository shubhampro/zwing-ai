function copyWithExecCommand(text: string): boolean {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    textarea.style.opacity = '0';

    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    textarea.setSelectionRange(0, text.length);

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    document.body.removeChild(textarea);

    return copied;
}

export async function copyToClipboard(text: string): Promise<boolean> {
    if (text === '') {
        return false;
    }

    if (navigator.clipboard?.writeText !== undefined) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Fall back below when Clipboard API blocked (HTTP, permissions, etc.).
        }
    }

    return copyWithExecCommand(text);
}
