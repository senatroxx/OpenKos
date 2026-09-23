import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { DeleteUser } from '@/components/features';
import { InputError, PhoneInput } from '@/components/shared';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { update as portalProfileUpdate } from '@/routes/portal/profile';
import { edit, update as profileUpdate } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
    portalProfile = false,
    profileReturn,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    portalProfile?: boolean;
    profileReturn?: string | null;
}) {
    const { auth } = usePage<PageProps>().props;

    const { data, setData, submit, processing, errors } = useForm({
        name: auth.user.name,
        email: auth.user.email,
        phone: auth.user.phone ?? '',
        id_card_number: auth.user.id_card_number ?? '',
        emergency_contact_name: auth.user.emergency_contact_name ?? '',
        emergency_contact_phone: auth.user.emergency_contact_phone ?? '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(
            portalProfile
                ? portalProfileUpdate({ query: profileReturn ? { return: profileReturn } : {} })
                : profileUpdate(),
            {
            preserveScroll: true,
            },
        );
    }

    return (
        <>
            <Head title={t('Profile settings')} />

            <h1 className="sr-only">{t('Profile settings')}</h1>

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('Profile')}</CardTitle>
                        <CardDescription>
                            {t('Update your name, email address, and phone number.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                    autoComplete="name"
                                    placeholder={t('Full name')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">{t('Phone')}</Label>
                                <PhoneInput
                                    value={data.phone}
                                    onChange={(value) => setData('phone', value)}
                                    placeholder={t('Phone number')}
                                />
                                <InputError className="mt-2" message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="id_card_number">
                                    {t('ID Card Number (KTP)')}
                                </Label>
                                <Input
                                    id="id_card_number"
                                    value={data.id_card_number}
                                    onChange={(e) =>
                                        setData('id_card_number', e.target.value)
                                    }
                                    placeholder={t('ID card number')}
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.id_card_number}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emergency_contact_name">
                                    {t('Emergency Contact Name')}
                                </Label>
                                <Input
                                    id="emergency_contact_name"
                                    value={data.emergency_contact_name}
                                    onChange={(e) =>
                                        setData(
                                            'emergency_contact_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={t('Emergency contact name')}
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.emergency_contact_name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emergency_contact_phone">
                                    {t('Emergency Contact Phone')}
                                </Label>
                                <PhoneInput
                                    value={data.emergency_contact_phone}
                                    onChange={(value) =>
                                        setData('emergency_contact_phone', value)
                                    }
                                    placeholder={t('Phone number')}
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.emergency_contact_phone}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    readOnly={portalProfile}
                                    required
                                    autoComplete="username"
                                    placeholder={t('Email address')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            {t(
                                                'Your email address is unverified.',
                                            )}{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                {t(
                                                    'Click here to re-send the verification email.',
                                                )}
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                {t(
                                                    'A new verification link has been sent to your email address.',
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {t('Save')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
