import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Blocks, Puzzle } from 'lucide-react';
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

export function canSee(permission: string | null, auth: Auth): boolean {
    return (
        !permission ||
        auth.role === 'owner' ||
        auth.permissions.includes(permission)
    );
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
    const visible = pages.filter((page) => canSee(page.permission, auth));
    const groups: Record<string, NavItem[]> = {};
    const ungrouped: NavItem[] = [];

    for (const page of visible) {
        const item: NavItem = { title: page.title, href: page.href };
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
            children,
        })),
    ];
}
