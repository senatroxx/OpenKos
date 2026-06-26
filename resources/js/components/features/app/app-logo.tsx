import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/features/app/app-logo-icon';

export default function AppLogo() {
    const { setting } = usePage<{ setting: { site_name: string } }>().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {setting.site_name}
                </span>
            </div>
        </>
    );
}
