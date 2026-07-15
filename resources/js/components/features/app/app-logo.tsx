import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { setting } = usePage<{ setting: { site_name: string } }>().props;

    return (
        <span className="truncate text-sm font-semibold">
            {setting.site_name}
        </span>
    );
}
