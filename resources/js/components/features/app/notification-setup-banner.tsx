import { Link } from '@inertiajs/react';
import { TriangleAlert, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import mail from '@/routes/settings/mail';

// Fixed full-width bar pinned to the very top of the viewport (above the sidebar).
// Its height must match --app-banner-height set on the layout wrapper so the sidebar
// and page content are offset by the same amount.
export function NotificationSetupBanner({
    visible,
    dismiss,
    dontShowAgain,
}: {
    visible: boolean;
    dismiss: () => void;
    dontShowAgain: () => void;
}) {
    if (!visible) {
        return null;
    }

    return (
        <div className="fixed inset-x-0 top-0 z-50 flex h-11 items-center gap-3 border-b border-amber-500/40 bg-amber-50 px-4 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100">
            <TriangleAlert className="size-4 shrink-0" />
            <p className="flex-1 truncate">
                Set up email or WhatsApp so tenants can receive rent reminders
                and invitations.
            </p>
            <Button asChild size="sm" variant="outline">
                <Link href={mail.edit.url()}>Set up</Link>
            </Button>
            <button
                type="button"
                onClick={dontShowAgain}
                className="hidden shrink-0 text-xs underline underline-offset-2 opacity-80 transition-opacity hover:opacity-100 sm:inline"
            >
                Don't show again
            </button>
            <button
                type="button"
                onClick={dismiss}
                aria-label="Dismiss"
                title="Hide until next visit"
                className="rounded p-1 text-amber-900/70 transition-colors hover:bg-amber-500/20 hover:text-amber-900 dark:text-amber-100/70 dark:hover:text-amber-100"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}
