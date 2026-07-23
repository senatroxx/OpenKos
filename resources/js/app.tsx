import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import TenantPortalLayout from '@/layouts/tenant-portal-layout';
import '@/plugins';

// The server drives the display name from the settings table (site_name),
// shared as the `name` prop on the initial page. Read it here so the title
// suffix matches, rather than the build-time VITE_APP_NAME. The page JSON
// lives in #app's data-page attribute (client render) or a <script data-page>
// element (SSR hydration), so check both.
function resolveAppName(): string {
    const fallback = import.meta.env.VITE_APP_NAME || 'OpenKOS';

    if (typeof document === 'undefined') {
        return fallback;
    }

    try {
        const raw =
            document.getElementById('app')?.getAttribute('data-page') ||
            document.querySelector('script[data-page]')?.textContent;
        const name = raw
            ? (JSON.parse(raw).props?.name as string | undefined)
            : undefined;

        return name || fallback;
    } catch {
        return fallback;
    }
}

const appName = resolveAppName();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name, page) => {
        const auth = page.props as {
            auth?: {
                tenant?: unknown;
            };
        };

        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                if (auth.auth?.tenant) {
                    return [TenantPortalLayout, SettingsLayout];
                }

                return [AppLayout, SettingsLayout];
            case name.startsWith('tenant-portal/'):
                return TenantPortalLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
