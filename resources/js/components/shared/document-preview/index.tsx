import { X } from 'lucide-react';
import { useEffect } from 'react';
import type { PointerEvent } from 'react';
import { createPortal } from 'react-dom';

type DocumentPreviewProps = {
    src: string;
    mimeType: string;
    title?: string;
    subtitle?: string;
    onClose: () => void;
};

export default function DocumentPreview({
    src,
    mimeType,
    title,
    subtitle,
    onClose,
}: DocumentPreviewProps) {
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };

        window.addEventListener('keydown', handler);

        return () => window.removeEventListener('keydown', handler);
    }, [onClose]);

    const handlePointerDown = (e: PointerEvent) => {
        e.stopPropagation();
    };

    const isPdf = mimeType === 'application/pdf';

    return createPortal(
        <div
            className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80"
            style={{ pointerEvents: 'auto' }}
            onPointerDown={handlePointerDown}
            onClick={onClose}
        >
            <div className="absolute top-0 right-0 left-0 z-10 flex items-center justify-between bg-linear-to-b from-black/50 to-transparent px-4 py-3">
                <div>
                    {title && (
                        <p className="text-sm font-medium text-white">
                            {title}
                        </p>
                    )}
                    {subtitle && (
                        <p className="text-xs text-white/70">{subtitle}</p>
                    )}
                </div>
                <button
                    type="button"
                    onClick={(e) => {
                        e.stopPropagation();
                        onClose();
                    }}
                    className="text-white hover:text-gray-300"
                >
                    <X className="size-6" />
                </button>
            </div>
            {isPdf ? (
                <iframe
                    src={src}
                    className="h-[90vh] w-[90vw] rounded bg-white"
                    onClick={(e) => e.stopPropagation()}
                />
            ) : (
                <img
                    src={src}
                    alt={title ?? 'Preview'}
                    className="max-h-[90vh] max-w-[90vw] rounded object-contain"
                    onClick={(e) => e.stopPropagation()}
                />
            )}
        </div>,
        document.body,
    );
}
