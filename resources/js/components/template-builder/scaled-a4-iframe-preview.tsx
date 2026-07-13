import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export const A4_PREVIEW_WIDTH_PX = 794;
export const A4_PREVIEW_HEIGHT_PX = 1123;

type ScaledA4IframePreviewProps = {
    srcDoc: string;
    title: string;
    className?: string;
};

export function ScaledA4IframePreview({ srcDoc, title, className }: ScaledA4IframePreviewProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [containerWidth, setContainerWidth] = useState(A4_PREVIEW_WIDTH_PX);

    useEffect(() => {
        const element = containerRef.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver((entries) => {
            const width = entries[0]?.contentRect.width ?? A4_PREVIEW_WIDTH_PX;
            setContainerWidth(width);
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const scale = Math.min(1, containerWidth / A4_PREVIEW_WIDTH_PX);
    const scaledHeight = A4_PREVIEW_HEIGHT_PX * scale;

    return (
        <div
            ref={containerRef}
            className={cn('w-full', className)}
            style={{ height: scaledHeight }}
        >
            <iframe
                title={title}
                srcDoc={srcDoc}
                sandbox="allow-same-origin"
                className="block origin-top-left border-0 bg-white"
                style={{
                    width: A4_PREVIEW_WIDTH_PX,
                    height: A4_PREVIEW_HEIGHT_PX,
                    transform: `scale(${scale})`,
                }}
            />
        </div>
    );
}
