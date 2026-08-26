import type { Auth } from '@/types/auth';
import type { Platform } from '@/types/platform';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            app: {
                timezone: string;
                currency_scales: Record<string, number>;
                locale: string;
                intl_locale: string;
                locales: Record<string, string>;
            };
            i18n: {
                locale: string;
                messages: Record<string, string>;
                fallback: Record<string, string>;
            };
            auth: Auth;
            setting: {
                id: number;
                site_name: string;
                country_code: string;
                locale: string;
                currency: string;
                supported_currencies: string[];
                timezone: string;
            };
            branding: {
                logoUrl: string;
                faviconUrl: string;
                hasCustomLogo: boolean;
                hasCustomFavicon: boolean;
                hasConfiguredLogo: boolean;
                hasConfiguredFavicon: boolean;
            };
            notificationChannels: { mail: boolean; whatsapp: boolean };
            sidebarOpen: boolean;
            platform: Platform;
        };
    }
}
