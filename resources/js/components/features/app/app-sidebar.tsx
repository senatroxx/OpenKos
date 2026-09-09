import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Building2,
    DollarSign,
    FileText,
    Landmark,
    LayoutGrid,
    Receipt,
    ReceiptText,
    Shield,
    Tags,
    UserCog,
    Users,
    WalletCards,
    Wrench,
} from 'lucide-react';
import { index as dataTransferIndex } from '@/actions/App/Http/Controllers/DataTransferController';
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
import expenses from '@/routes/expenses';
import leases from '@/routes/leases';
import maintenanceTickets from '@/routes/maintenance-tickets';
import { dashboard as portalDashboard } from '@/routes/portal';
import { index as portalBilling } from '@/routes/portal/billing';
import { index as portalLease } from '@/routes/portal/lease';
import properties from '@/routes/properties';
import roles from '@/routes/roles';
import expenseCategories from '@/routes/settings/expense-categories';
import propertyTypes from '@/routes/settings/property-types';
import tenants from '@/routes/tenants';
import userRoutes from '@/routes/users';
import type { Auth, NavItem, NavSection } from '@/types';
import type { Platform } from '@/types/platform';

export function AppSidebar() {
    const { auth, platform } = usePage<{ auth: Auth; platform: Platform }>()
        .props;
    const permissions = auth.permissions;
    const isOwner = auth.role === 'owner';
    const canTransfer =
        isOwner ||
        [
            'properties.import',
            'properties.export',
            'units.import',
            'units.export',
            'tenants.import',
            'tenants.export',
            'unit-rates.import',
            'unit-rates.export',
        ].some((permission) => permissions.includes(permission));
    const home = auth.tenant ? portalDashboard() : dashboard();
    const settingsNavItems = [
        ...platformPageNavItems(
            platform.settings.filter((page) => page.key !== 'about'),
            auth,
        ),
        ...platformPageNavItems(
            platform.settings.filter((page) => page.key === 'about'),
            auth,
        ),
    ];

    const navSections: NavSection[] = auth.tenant
        ? [
              {
                  title: 'OVERVIEW',
                  items: [
                      {
                          title: 'Dashboard',
                          icon: LayoutGrid,
                          href: portalDashboard(),
                      },
                  ],
              },
              {
                  title: 'DAILY OPERATIONS',
                  items: [
                      {
                          title: 'Billing',
                          icon: Receipt,
                          href: portalBilling(),
                      },
                  ],
              },
              {
                  title: 'PORTFOLIO',
                  items: [
                      {
                          title: 'Lease',
                          icon: FileText,
                          href: portalLease(),
                      },
                  ],
              },
          ]
        : [
              ...(isOwner || permissions.includes('dashboard.view')
                  ? [
                        {
                            title: 'OVERVIEW',
                            items: [
                                {
                                    title: 'Dashboard',
                                    icon: LayoutGrid,
                                    href: dashboard(),
                                    ...(platformPageNavItems(
                                        platform.dashboard,
                                        auth,
                                    ).length > 0
                                        ? {
                                              children: [
                                                  {
                                                      title: 'Overview',
                                                      icon: LayoutGrid,
                                                      href: dashboard(),
                                                  },
                                                  ...platformPageNavItems(
                                                      platform.dashboard,
                                                      auth,
                                                  ),
                                              ],
                                          }
                                        : {}),
                                },
                            ],
                        },
                    ]
                  : []),
              ...(isOwner ||
              permissions.includes('dashboard.view') ||
              permissions.includes('maintenance-tickets.view') ||
              permissions.includes('expenses.view')
                  ? [
                        {
                            title: 'DAILY OPERATIONS',
                            items: [
                                ...(isOwner ||
                                permissions.includes('dashboard.view')
                                    ? [
                                          {
                                              title: 'Billing',
                                              icon: DollarSign,
                                              href: dashboardRent(),
                                          },
                                      ]
                                    : []),
                                ...(isOwner ||
                                permissions.includes('maintenance-tickets.view')
                                    ? [
                                          {
                                              title: 'Maintenance',
                                              href: maintenanceTickets.index(),
                                              icon: Wrench,
                                          },
                                      ]
                                    : []),
                                ...(isOwner ||
                                permissions.includes('expenses.view')
                                    ? [
                                          {
                                              title: 'Expenses',
                                              icon: WalletCards,
                                              children: [
                                                  {
                                                      title: 'All Expenses',
                                                      href: expenses.index(),
                                                      icon: ReceiptText,
                                                  },
                                                  ...(isOwner
                                                      ? [
                                                            {
                                                                title: 'Expense Categories',
                                                                href: expenseCategories.index(),
                                                                icon: Tags,
                                                            },
                                                        ]
                                                      : []),
                                              ],
                                          },
                                      ]
                                    : []),
                            ],
                        },
                    ]
                  : []),
              ...(isOwner ||
              permissions.includes('properties.view') ||
              permissions.includes('units.view') ||
              permissions.includes('leases.view') ||
              permissions.includes('tenants.view')
                  ? [
                        {
                            title: 'PORTFOLIO',
                            items: [
                                ...(isOwner ||
                                permissions.includes('properties.view')
                                    ? [
                                          {
                                              title: 'Properties',
                                              icon: Landmark,
                                              children: [
                                                  {
                                                      title: 'All Properties',
                                                      href: properties.index(),
                                                      icon: Building2,
                                                  },
                                                  ...(isOwner
                                                      ? [
                                                            {
                                                                title: 'Property Types',
                                                                href: propertyTypes.index(),
                                                                icon: Tags,
                                                            },
                                                        ]
                                                      : []),
                                              ],
                                          },
                                      ]
                                    : []),
                                ...(isOwner ||
                                permissions.includes('leases.view')
                                    ? [
                                          {
                                              title: 'Leases',
                                              href: leases.index(),
                                              icon: FileText,
                                          },
                                      ]
                                    : []),
                                ...(isOwner ||
                                permissions.includes('tenants.view')
                                    ? [
                                          {
                                              title: 'Tenants',
                                              href: tenants.index(),
                                              icon: Users,
                                          },
                                      ]
                                    : []),
                            ],
                        },
                    ]
                  : []),
              ...(isOwner || permissions.includes('users.view') || canTransfer
                  ? [
                        {
                            title: 'ADMINISTRATION',
                            items: [
                                ...(canTransfer
                                    ? [
                                          {
                                              title: 'Data Transfer',
                                              href: dataTransferIndex(),
                                              icon: ArrowLeftRight,
                                          },
                                      ]
                                    : []),
                                ...(isOwner ||
                                permissions.includes('users.view')
                                    ? [
                                          {
                                              title: 'Users',
                                              href: userRoutes.index(),
                                              icon: UserCog,
                                          },
                                      ]
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
                            ],
                        },
                    ]
                  : []),
              ...(settingsNavItems.length > 0
                  ? [
                        {
                            title: 'SETTINGS',
                            items: settingsNavItems,
                        },
                    ]
                  : []),
          ];

    const activeSections = navSections.filter(
        (section) => section.items.length > 0,
    );

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
                <NavMain sections={activeSections} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
