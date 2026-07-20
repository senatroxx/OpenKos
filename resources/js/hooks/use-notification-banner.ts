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

    const [hidden, setHidden] = useState(() =>
        typeof window === 'undefined'
            ? false
            : localStorage.getItem(PERMANENT_KEY) === '1' ||
              sessionStorage.getItem(SESSION_KEY) === '1',
    );

    const visible =
        auth.role === 'owner' &&
        !notificationChannels?.mail &&
        !notificationChannels?.whatsapp &&
        !hidden;

    function dismiss() {
        if (typeof window === 'undefined') {
            return;
        }

        sessionStorage.setItem(SESSION_KEY, '1');
        setHidden(true);
    }

    function dontShowAgain() {
        if (typeof window === 'undefined') {
            return;
        }

        localStorage.setItem(PERMANENT_KEY, '1');
        setHidden(true);
    }

    return { visible, dismiss, dontShowAgain };
}
