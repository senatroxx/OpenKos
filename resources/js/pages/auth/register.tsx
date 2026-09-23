import { Form, Head } from '@inertiajs/react';
import { InputError, PasswordInput } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { store } from '@/routes/register';
import type { RegisterPageProps } from '@/types/auth';

export default function Register({ redirect }: RegisterPageProps) {
    return (
        <>
            <Head title={t('Create an account')} />
            <Form {...store.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <div className="grid gap-5">
                        {redirect && (
                            <input type="hidden" name="redirect" value={redirect} />
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('Name')}</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                autoComplete="name"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('Email address')}</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                autoComplete="email"
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('Password')}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('Confirm password')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t('Create account')}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Create your account',
    description: 'Register to apply for a published listing.',
};
