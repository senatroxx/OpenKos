import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    FileText,
    FolderGit2,
    LayoutGrid,
    Receipt,
    Shield,
    UserCog,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/features/app/app-logo';
import { NavFooter } from '@/components/features/app/nav-footer';
import { NavMain } from '@/components/features/app/nav-main';
import { NavUser } from '@/components/features/app/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { rent as dashboardRent } from '@/routes/dashboard';
import leases from '@/routes/leases';
import properties from '@/routes/properties';
import roles from '@/routes/roles';
import tenants from '@/routes/tenants';
import userRoutes from '@/routes/users';
import type { Auth } from '@/types';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const permissions = auth.permissions;
    const isOwner = auth.role === 'owner';

    const mainNavItems: NavItem[] = [
        ...(isOwner || permissions.includes('dashboard.view')
            ? [
                  {
                      title: 'Dashboard',
                      icon: LayoutGrid,
                      children: [
                          { title: 'Overview', icon: LayoutGrid, href: dashboard() },
                          { title: 'Rent', icon: Receipt, href: dashboardRent() },
                      ],
                  },
              ]
            : []),
        ...(isOwner || permissions.includes('properties.view')
            ? [
                  {
                      title: 'Properties',
                      href: properties.index(),
                      icon: Building2,
                  },
              ]
            : []),
        ...(isOwner || permissions.includes('tenants.view')
            ? [{ title: 'Tenants', href: tenants.index(), icon: Users }]
            : []),
        ...(isOwner || permissions.includes('leases.view')
            ? [{ title: 'Leases', href: leases.index(), icon: FileText }]
            : []),
        ...(isOwner || permissions.includes('users.view')
            ? [{ title: 'Users', href: userRoutes.index(), icon: UserCog }]
            : []),
        ...(isOwner
            ? [
                  {
                      title: 'Roles & Permissions',
                      href: roles.index(),
                      icon: Shield,
                  },
              ]
            : []),
    ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
