import type { Auth } from '@/types/auth';

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
                auth: Auth;
                setting: {
                    id: number;
                    site_name: string;
                    country_code: string;
                    locale: string;
                    currency: string;
                    timezone: string;
                };
                sidebarOpen: boolean;
            };
        }
    }
