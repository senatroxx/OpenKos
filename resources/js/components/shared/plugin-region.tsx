import type { ReactNode } from 'react';

export function PluginRegion({
    name,
    children,
}: {
    name: string;
    children?: ReactNode;
}) {
    return <div data-plugin-region={name}>{children}</div>;
}
