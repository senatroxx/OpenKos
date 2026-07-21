import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    DollarSign,
    FileText,
    LayoutGrid,
    Receipt,
    Shield,
    UserCog,
    Users,
    Wrench,
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
import { platformNavItems, platformPageNavItems } from '@/lib/platform';
import { dashboard } from '@/routes';
import { rent as dashboardRent } from '@/routes/dashboard';
import { dashboard as portalDashboard } from '@/routes/portal';
import leases from '@/routes/leases';
import maintenanceTickets from '@/routes/maintenance-tickets';
import properties from '@/routes/properties';
import roles from '@/routes/roles';
import tenants from '@/routes/tenants';
import userRoutes from '@/routes/users';
import type { Auth } from '@/types';
import type { NavItem } from '@/types';
import type { Platform } from '@/types/platform';

export function AppSidebar() {
    const { auth, platform } = usePage<{ auth: Auth; platform: Platform }>()
        .props;
    const permissions = auth.permissions;
    const isOwner = auth.role === 'owner';
    const home = auth.tenant ? portalDashboard() : dashboard();

    const mainNavItems: NavItem[] = [
        ...(auth.tenant
            ? [{ title: 'Portal', href: portalDashboard(), icon: LayoutGrid }]
            : []),
        ...(isOwner || permissions.includes('dashboard.view')
            ? [
                  {
                      title: 'Dashboard',
                      icon: LayoutGrid,
                      children: [
                          {
                              title: 'Overview',
                              icon: LayoutGrid,
                              href: dashboard(),
                          },
                          ...platformPageNavItems(platform.dashboard, auth),
                      ],
                  },
              ]
            : []),
        ...(isOwner || permissions.includes('dashboard.view')
            ? [
                  {
                      title: 'Billing',
                      icon: DollarSign,
                      children: [
                          {
                              title: 'Collection',
                              icon: Receipt,
                              href: dashboardRent(),
                          },
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
        ...(isOwner || permissions.includes('maintenance-tickets.view')
            ? [
                  {
                      title: 'Maintenance',
                      href: maintenanceTickets.index(),
                      icon: Wrench,
                  },
              ]
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
        ...platformNavItems(platform.navigation.main, auth),
    ];

    const footerNavItems: NavItem[] = [
        ...platformNavItems(platform.navigation.footer, auth),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={home} prefetch>
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
