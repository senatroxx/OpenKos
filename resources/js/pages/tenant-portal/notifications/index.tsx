import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { read, readAll } from '@/routes/portal/notifications';

type Notification = {
    id: string;
    type: string;
    title: string;
    message: string;
    url: string | null;
    created_at: string;
    read_at: string | null;
};

type Props = {
    notifications: {
        data: Notification[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    unreadCount: number;
};

export default function Notifications({ notifications, unreadCount }: Props) {
    return (
        <>
            <Head title={t('Notifications')} />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {t('Notifications')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('Stay up to date with your tenancy.')}
                        </p>
                    </div>
                    {unreadCount > 0 && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.post(readAll.url())}
                        >
                            <CheckCheck />
                            {t('Mark all as read')}
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Bell className="size-5" />
                            {t('History')}
                            {unreadCount > 0 && (
                                <Badge variant="secondary">
                                    {unreadCount} {t('unread')}
                                </Badge>
                            )}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {notifications.data.length === 0 ? (
                            <div className="flex min-h-40 items-center justify-center p-6 text-sm text-muted-foreground">
                                {t('No notifications yet.')}
                            </div>
                        ) : (
                            <div className="divide-y">
                                {notifications.data.map((notification) => (
                                    <div
                                        key={notification.id}
                                        className={`flex gap-4 p-5 ${!notification.read_at ? 'bg-muted/30' : ''}`}
                                    >
                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex items-center gap-2">
                                                <p className="font-medium">
                                                    {notification.title}
                                                </p>
                                                {!notification.read_at && (
                                                    <Badge variant="default">
                                                        {t('Unread')}
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {notification.message}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDate(
                                                    notification.created_at,
                                                )}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-start gap-2">
                                            {notification.url && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={notification.url}
                                                    >
                                                        {t('View')}
                                                    </Link>
                                                </Button>
                                            )}
                                            {!notification.read_at && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.post(
                                                            read.url(
                                                                notification.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {t('Read')}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {notifications.links.length > 3 && (
                    <div className="flex flex-wrap justify-center gap-2">
                        {notifications.links.map((link, index) => (
                            <Button
                                key={`${link.label}-${index}`}
                                asChild={Boolean(link.url)}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                            >
                                {link.url ? (
                                    <Link href={link.url}>{link.label}</Link>
                                ) : (
                                    link.label
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
