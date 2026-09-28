import { Link, router } from '@inertiajs/react';
import { LogOut, UserRound } from 'lucide-react';
import { UserInfo } from '@/components/features/app/user-info';
import {
    DropdownMenuItem,
    DropdownMenuLabel,
} from '@/components/ui/dropdown-menu';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { t } from '@/lib/i18n';
import { logout } from '@/routes';
import type { User } from '@/types';

type Props = {
    user: User;
    profileHref?: string;
};

export function UserMenuContent({ user, profileHref }: Props) {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            {profileHref && (
                <DropdownMenuItem asChild>
                    <Link className="block w-full cursor-pointer" href={profileHref}>
                        <UserRound className="mr-2" />
                        Profile
                    </Link>
                </DropdownMenuItem>
            )}
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    {t('Log out')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
