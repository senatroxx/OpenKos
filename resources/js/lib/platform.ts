import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Bell,
    BellRing,
    Blocks,
    Building2,
    Info,
    KeyRound,
    Mail,
    MessageCircle,
    Plug,
    Puzzle,
    Settings,
    Shield,
    Tags,
    User,
} from 'lucide-react';
import type { Auth, NavItem } from '@/types';
import type {
    Platform,
    PlatformNavigationItem,
    PlatformPage,
    PlatformWorkspaceTab,
} from '@/types/platform';

// ponytail: explicit icon map, add names as plugins need them; unknown names
// fall back to Blocks. A full lucide name->component map would defeat tree-shaking.
const iconMap: Record<string, LucideIcon> = {
    puzzle: Puzzle,
};

const settingsGroupIconMap: Record<string, LucideIcon> = {
    Account: Shield,
    Preferences: Settings,
    Notifications: Bell,
    Property: Building2,
    Integrations: Plug,
};

const settingsPageIconMap: Record<string, LucideIcon> = {
    about: Info,
    general: Settings,
    profile: User,
    security: KeyRound,
    reminders: BellRing,
    'property-types': Tags,
    mail: Mail,
    whatsapp: MessageCircle,
    'payment-gateway': Plug,
};

export function canSee(permission: string | null, auth: Auth): boolean {
    return (
        !permission ||
        auth.role === 'owner' ||
        auth.permissions.includes(permission)
    );
}

export function canSeePlatformPage(page: PlatformPage, auth: Auth): boolean {
    if (page.ownerOnly && auth.role !== 'owner') {
        return false;
    }

    return canSee(page.permission, auth);
}

export function platformNavItems(
    items: PlatformNavigationItem[] | undefined,
    auth: Auth,
): NavItem[] {
    return (items ?? [])
        .filter((item) => canSee(item.permission, auth))
        .map((item) => ({
            title: item.title,
            href: item.href ?? undefined,
            icon: item.icon ? (iconMap[item.icon] ?? Blocks) : undefined,
            children: item.children.length
                ? platformNavItems(item.children, auth)
                : undefined,
        }));
}

export function usePlatformTabs(workspace: string): PlatformWorkspaceTab[] {
    const { auth, platform } = usePage<{ auth: Auth; platform: Platform }>()
        .props;

    return (platform.workspaces[workspace] ?? []).filter((tab) =>
        canSee(tab.permission, auth),
    );
}

export function platformPageNavItems(
    pages: PlatformPage[],
    auth: Auth,
): NavItem[] {
    const visible = pages.filter((page) => canSeePlatformPage(page, auth));
    const groups: Record<string, NavItem[]> = Object.create(null);
    const ungrouped: NavItem[] = [];

    for (const page of visible) {
        const item: NavItem = {
            title: page.title,
            href: page.href,
            icon: settingsPageIconMap[page.key] ?? Blocks,
        };

        if (page.group) {
            (groups[page.group] ??= []).push(item);
        } else {
            ungrouped.push(item);
        }
    }

    return [
        ...ungrouped,
        ...Object.entries(groups).map(([title, children]) => ({
            title,
            icon: settingsGroupIconMap[title] ?? Blocks,
            children,
        })),
    ];
}
