import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { t } from '@/lib/i18n';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate title={t(title)} description={t(description)}>
            {children}
        </AuthLayoutTemplate>
    );
}
