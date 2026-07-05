import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Heading } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { platformPageNavItems } from '@/lib/platform';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editMail } from '@/routes/settings/mail';
import { edit as editReminders } from '@/routes/settings/reminders';
import { edit as editWhatsApp } from '@/routes/settings/whatsapp';
import type { Auth, NavItem } from '@/types';
import type { Platform } from '@/types/platform';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
    },
    {
        title: 'Security',
        href: editSecurity(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
    {
        title: 'Reminders',
        href: editReminders(),
    },
    {
        title: 'Property Types',
        href: '/settings/property-types',
    },
    {
        title: 'Credentials',
        children: [
            {
                title: 'Mail',
                href: editMail(),
            },
            {
                title: 'WhatsApp',
                href: editWhatsApp(),
            },
        ],
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth, platform } = usePage<{ auth: Auth; platform: Platform }>()
        .props;

    const navItems: NavItem[] = [
        ...sidebarNavItems,
        ...platformPageNavItems(platform.settings, auth),
    ];

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Settings"
                    >
                        {navItems.map((item, index) =>
                            item.children ? (
                                <div key={`group-${index}`} className="space-y-1">
                                    <p className="px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider pt-2">
                                        {item.title}
                                    </p>
                                    {item.children.map((child, childIndex) => (
                                        <Button
                                            key={`${toUrl(child.href!)}-${childIndex}`}
                                            size="sm"
                                            variant="ghost"
                                            asChild
                                            className={cn('w-full justify-start pl-6', {
                                                'bg-muted': isCurrentOrParentUrl(child.href!),
                                            })}
                                        >
                                            <Link href={child.href}>
                                                {child.icon && (
                                                    <child.icon className="h-4 w-4" />
                                                )}
                                                {child.title}
                                            </Link>
                                        </Button>
                                    ))}
                                </div>
                            ) : (
                                <Button
                                    key={`${toUrl(item.href!)}-${index}`}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn('w-full justify-start', {
                                        'bg-muted': isCurrentOrParentUrl(item.href!),
                                    })}
                                >
                                    <Link href={item.href}>
                                        {item.icon && (
                                            <item.icon className="h-4 w-4" />
                                        )}
                                        {item.title}
                                    </Link>
                                </Button>
                            ),
                        )}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
