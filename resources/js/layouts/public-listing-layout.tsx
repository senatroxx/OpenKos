import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogo from '@/components/features/app/app-logo';
import { t } from '@/lib/i18n';
import { login, register } from '@/routes';
import { dashboard as portalDashboard } from '@/routes/portal';
import { index as publicIndex } from '@/routes/public/portal';
import type { Auth } from '@/types/auth';

export default function PublicListingLayout({ children }: PropsWithChildren) {
    const { setting, auth } = usePage<{
        setting: { site_name: string };
        auth?: Auth;
    }>().props;

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <header className="border-b bg-background/95">
                <div className="mx-auto flex min-h-16 w-full max-w-[1360px] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <Link
                        href={publicIndex()}
                        className="shrink-0 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label={t('Go to home')}
                    >
                        <AppLogo />
                    </Link>
                    <nav
                        className="flex items-center gap-1 text-sm font-medium"
                        aria-label="Public navigation"
                    >
                        <Link
                            href={auth?.user ? portalDashboard() : login({ query: { redirect: 'portal' } })}
                            className="rounded-md px-3 py-2 transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {auth?.user ? t('My account') : t('Log in')}
                        </Link>
                        {!auth?.user && (
                            <Link
                                href={register({ query: { redirect: 'portal' } })}
                                className="rounded-md px-3 py-2 transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {t('Register')}
                            </Link>
                        )}
                    </nav>
                </div>
            </header>
            <main className="flex-1">{children}</main>
            <footer className="mt-16 border-t bg-muted/20">
                <div className="mx-auto flex w-full max-w-[1360px] flex-col gap-1 px-4 py-6 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <p>{t('© :site_name', { site_name: setting.site_name })}</p>
                    <p>{t('Property listings')}</p>
                </div>
            </footer>
        </div>
    );
}
