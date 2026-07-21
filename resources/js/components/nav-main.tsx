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
import type { NavItem } from '@/types';

function hasActiveChild(
    items: NavItem[],
    isCurrentUrl: (href: NonNullable<NavItem['href']>) => boolean,
    isCurrentOrParentUrl: (href: NonNullable<NavItem['href']>) => boolean,
): boolean {
    return items.some((item) => {
        if (
            item.href &&
            (isCurrentUrl(item.href) || isCurrentOrParentUrl(item.href))
        ) {
            return true;
        }

        if (item.items?.length) {
            return hasActiveChild(
                item.items,
                isCurrentUrl,
                isCurrentOrParentUrl,
            );
        }

        return false;
    });
}

function NavMenuItem({ item }: { item: NavItem }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const children = item.items ?? [];

    if (children.length > 0) {
        const openByDefault = hasActiveChild(
            children,
            isCurrentUrl,
            isCurrentOrParentUrl,
        );

        return (
            <Collapsible
                asChild
                defaultOpen={openByDefault}
                className="group/collapsible"
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuButton
                            tooltip={{ children: item.title }}
                            isActive={openByDefault}
                        >
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                            <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            {children.map((child) => (
                                <SidebarMenuSubItem key={child.title}>
                                    {child.href ? (
                                        <SidebarMenuSubButton
                                            asChild
                                            isActive={
                                                isCurrentUrl(child.href) ||
                                                isCurrentOrParentUrl(child.href)
                                            }
                                        >
                                            <Link href={child.href} prefetch>
                                                {child.icon && <child.icon />}
                                                <span>{child.title}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    ) : null}
                                </SidebarMenuSubItem>
                            ))}
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        );
    }

    if (!item.href) {
        return null;
    }

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={
                    isCurrentUrl(item.href) || isCurrentOrParentUrl(item.href)
                }
                tooltip={{ children: item.title }}
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <NavMenuItem key={item.title} item={item} />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
