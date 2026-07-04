import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Heading } from '@/components/shared';
import { PluginRegion } from '@/components/shared/plugin-region';

export function EntityWorkspaceLayout({
    title,
    subtitle,
    backRoute,
    backLabel,
    actions,
    children,
}: {
    title: string;
    subtitle?: string;
    backRoute: string;
    backLabel: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="workspace-enter flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <Link
                        href={backRoute}
                        className="mb-2 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <ChevronLeft className="size-3" />
                        {backLabel}
                    </Link>
                    <div className="flex items-center gap-3">
                        <Heading title={title} description={subtitle} />
                        <PluginRegion name="workspace-header-badge" />
                    </div>
                </div>
                {actions && (
                    <div className="flex shrink-0 items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>

            <PluginRegion name="workspace-before-content" />

            <div className="flex-1">{children}</div>

            <PluginRegion name="workspace-after-content" />
        </div>
    );
}
