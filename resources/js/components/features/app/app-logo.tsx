import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { setting, branding } = usePage<{
        setting: { site_name: string };
        branding: { logoUrl: string };
    }>().props;

    return (
        <span className="flex min-w-0 items-center gap-2 truncate text-sm font-semibold">
            <img
                src={branding.logoUrl}
                alt=""
                aria-hidden="true"
                className="size-6 shrink-0 object-contain"
            />
            <span className="truncate">{setting.site_name}</span>
        </span>
    );
}
