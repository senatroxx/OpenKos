import {
    AppContent,
    AppShell,
    AppSidebar,
    AppSidebarHeader,
} from '@/components/features';
import { NotificationSetupBanner } from '@/components/features/app/notification-setup-banner';
import { useNotificationBanner } from '@/hooks/use-notification-banner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const banner = useNotificationBanner();

    return (
        <div
            style={
                banner.visible
                    ? ({ '--app-banner-height': '2.75rem' } as React.CSSProperties)
                    : undefined
            }
        >
            <NotificationSetupBanner
                visible={banner.visible}
                dismiss={banner.dismiss}
                dontShowAgain={banner.dontShowAgain}
            />
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent variant="sidebar" className="overflow-x-hidden">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
            </AppShell>
        </div>
    );
}
