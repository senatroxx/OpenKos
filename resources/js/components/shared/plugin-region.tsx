import type { ComponentType, ReactNode } from 'react';

// ponytail: module-level registry, registered via side-effect imports in
// resources/js/plugins/. Enough until plugins ship their own JS bundles.
const regions = new Map<string, ComponentType[]>();

export function registerRegion(name: string, component: ComponentType) {
    const components = regions.get(name) ?? [];
    components.push(component);
    regions.set(name, components);
}

export function PluginRegion({
    name,
    children,
}: {
    name: string;
    children?: ReactNode;
}) {
    const components = regions.get(name) ?? [];

    // Empty regions render nothing — an empty div would still be a flex
    // item and produce phantom gaps in gap-* layouts.
    if (!children && components.length === 0) {
        return null;
    }

    return (
        <div data-plugin-region={name}>
            {children}
            {components.map((Component, index) => (
                <Component key={index} />
            ))}
        </div>
    );
}
