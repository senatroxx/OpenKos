import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, Menu } from 'lucide-react';
import { useState } from 'react';
import AppLogo from '@/components/features/app/app-logo';
import { UserInfo } from '@/components/features/app/user-info';
import { UserMenuContent } from '@/components/features/app/user-menu-content';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/portal';
import { index as billing } from '@/routes/portal/billing';
import { index as leases } from '@/routes/portal/lease';
import type { Auth } from '@/types/auth';
import type { AppLayoutProps } from '@/types/ui';

const navigationItems = [
    { title: 'Dashboard', href: dashboard(), exact: true },
    { title: 'Leases', href: leases() },
    { title: 'Billing', href: billing() },
];

export default function TenantPortalLayout({ children }: AppLayoutProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();
    const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header>
                <div className="mx-auto flex min-h-16 w-full max-w-6xl items-center gap-4 px-4">
                    <Sheet
                        open={mobileNavigationOpen}
                        onOpenChange={setMobileNavigationOpen}
                    >
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="md:hidden"
                                aria-label="Open navigation"
                            >
                                <Menu className="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            className="flex w-72 flex-col"
                        >
                            <SheetHeader>
                                <SheetTitle>
                                    <AppLogo />
                                </SheetTitle>
                            </SheetHeader>
                            <nav
                                className="grid flex-1 content-start gap-1 px-4"
                                aria-label="Tenant portal"
                            >
                                {navigationItems.map((item) => {
                                    const active = item.exact
                                        ? isCurrentUrl(item.href)
                                        : isCurrentOrParentUrl(item.href);

                                    return (
                                        <Link
                                            key={item.title}
                                            href={item.href}
                                            className={cn(
                                                'rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-muted',
                                                active &&
                                                    'bg-muted text-foreground',
                                            )}
                                            onClick={() =>
                                                setMobileNavigationOpen(false)
                                            }
                                        >
                                            {item.title}
                                        </Link>
                                    );
                                })}
                            </nav>
                            {auth.user && (
                                <div className="border-t p-4">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                className="h-10 w-full gap-2 px-2"
                                                aria-label="Open account menu"
                                            >
                                                <UserInfo user={auth.user} />
                                                <ChevronsUpDown className="size-4 text-muted-foreground" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            className="w-56"
                                            align="start"
                                        >
                                            <UserMenuContent user={auth.user} />
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            )}
                        </SheetContent>
                    </Sheet>

                    <Link href={dashboard()} prefetch className="shrink-0">
                        <AppLogo />
                    </Link>

                    {auth.user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="ml-auto hidden h-10 max-w-64 gap-2 px-2 md:flex"
                                    aria-label="Open account menu"
                                >
                                    <UserInfo user={auth.user} />
                                    <ChevronsUpDown className="size-4 text-muted-foreground" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </header>

            <div className="sticky top-0 z-20 hidden border-y bg-background/70 backdrop-blur md:block">
                <nav
                    className="mx-auto flex min-h-12 w-full max-w-6xl items-center gap-5 px-4"
                    aria-label="Tenant portal"
                >
                    {navigationItems.map((item) => {
                        const active = item.exact
                            ? isCurrentUrl(item.href)
                            : isCurrentOrParentUrl(item.href);

                        return (
                            <Link
                                key={item.title}
                                href={item.href}
                                className={cn(
                                    'flex min-h-12 items-center border-b-2 border-transparent text-sm font-medium text-muted-foreground transition-colors hover:text-foreground',
                                    active && 'border-primary text-foreground',
                                )}
                            >
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>
            </div>

            <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col">
                {children}
            </main>
        </div>
    );
}
