import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { Auth } from '@/types';

// Permanent opt-out (localStorage) vs. this-session dismissal (sessionStorage, so it
// stays hidden while navigating/reloading in this tab but returns on the next visit).
const PERMANENT_KEY = 'notif-setup-banner-hidden';
const SESSION_KEY = 'notif-setup-banner-dismissed';

export function useNotificationBanner() {
    const { auth, notificationChannels } = usePage<{
        auth: Auth;
        notificationChannels: { mail: boolean; whatsapp: boolean };
    }>().props;

    const [hidden, setHidden] = useState(
        () =>
            localStorage.getItem(PERMANENT_KEY) === '1' ||
            sessionStorage.getItem(SESSION_KEY) === '1',
    );

    // Only owners can reach the mail/WhatsApp settings, so only they see the nudge.
    const visible =
        auth.role === 'owner' &&
        !notificationChannels?.mail &&
        !notificationChannels?.whatsapp &&
        !hidden;

    function dismiss() {
        sessionStorage.setItem(SESSION_KEY, '1');
        setHidden(true);
    }

    function dontShowAgain() {
        localStorage.setItem(PERMANENT_KEY, '1');
        setHidden(true);
    }

    return { visible, dismiss, dontShowAgain };
}
