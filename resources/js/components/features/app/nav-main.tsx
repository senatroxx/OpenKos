import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { t } from '@/lib/i18n';
import type { NavItem, NavSection } from '@/types';

export function NavMain({
    sections = [],
    items = [],
}: {
    sections?: NavSection[];
    items?: NavItem[];
}) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const displaySections: NavSection[] =
        sections.length > 0
            ? sections
            : items.length > 0
              ? [{ title: 'PLATFORM', items }]
              : [];

    function isAnyChildActive(children: NavItem[]): boolean {
        return children.some((child) => isCurrentUrl(child.href ?? ''));
    }

    function checkItemActive(item: NavItem): boolean {
        if (item.isActive !== undefined) {
            return item.isActive;
        }

        if (item.title === 'Dashboard') {
            return isCurrentUrl(item.href ?? '/dashboard');
        }

        const href = item.href ?? item.children?.[0]?.href;

        return href ? isCurrentOrParentUrl(href) : false;
    }

    return (
        <div className="flex flex-col gap-4 py-1">
            {displaySections.map((section) => (
                <SidebarGroup key={section.title} className="px-2 py-0">
                    <SidebarGroupLabel>{t(section.title)}</SidebarGroupLabel>
                    <SidebarMenu className="gap-0.5">
                        {section.items.map((item) => {
                            const hasMultipleChildren =
                                Boolean(item.children) &&
                                (item.children?.length ?? 0) > 1;

                            if (hasMultipleChildren && item.children) {
                                const hasActiveChild = isAnyChildActive(
                                    item.children,
                                );

                                return (
                                    <Collapsible
                                        key={item.title}
                                        defaultOpen={hasActiveChild}
                                        className="group/collapsible"
                                    >
                                        <SidebarMenuItem>
                                            <CollapsibleTrigger asChild>
                                                <SidebarMenuButton
                                                    tooltip={{
                                                        children: t(item.title),
                                                    }}
                                                >
                                                    {item.icon && <item.icon />}
                                                    <span>{t(item.title)}</span>
                                                    <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                                </SidebarMenuButton>
                                            </CollapsibleTrigger>
                                            <CollapsibleContent>
                                                <SidebarMenuSub>
                                                    {item.children.map(
                                                        (child) => (
                                                            <SidebarMenuSubItem
                                                                key={
                                                                    child.title
                                                                }
                                                            >
                                                                <SidebarMenuSubButton
                                                                    asChild
                                                                    isActive={isCurrentUrl(
                                                                        child.href ??
                                                                            '',
                                                                    )}
                                                                >
                                                                    <Link
                                                                        href={
                                                                            child.href ??
                                                                            '#'
                                                                        }
                                                                        prefetch
                                                                    >
                                                                        {child.icon && (
                                                                            <child.icon />
                                                                        )}
                                                                        <span>
                                                                            {t(
                                                                                child.title,
                                                                            )}
                                                                        </span>
                                                                    </Link>
                                                                </SidebarMenuSubButton>
                                                            </SidebarMenuSubItem>
                                                        ),
                                                    )}
                                                </SidebarMenuSub>
                                            </CollapsibleContent>
                                        </SidebarMenuItem>
                                    </Collapsible>
                                );
                            }

                            const targetHref =
                                item.href ?? item.children?.[0]?.href ?? '#';

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={checkItemActive(item)}
                                        tooltip={{ children: t(item.title) }}
                                    >
                                        <Link href={targetHref} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{t(item.title)}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </div>
    );
}
