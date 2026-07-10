import { Head, useForm } from '@inertiajs/react';
import { completeInvitation } from '@/actions/App/Http/Controllers/UserController';
import { InputError, PasswordInput } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function AcceptInvitation({
    token,
    email,
    passwordRules,
}: Props) {
    const { data, setData, submit, processing, errors } = useForm({
        email,
        password: '',
        password_confirmation: '',
        token,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(completeInvitation(), {
            onSuccess: () => {
                setData('password', '');
                setData('password_confirmation', '');
            },
        });
    }

    return (
        <>
            <Head title="Accept invitation" />

            <form onSubmit={handleSubmit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" value={data.email} readOnly />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autoComplete="new-password"
                        autoFocus
                        placeholder="Password"
                        passwordrules={passwordRules}
                        value={data.password}
                        onChange={e => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">
                        Confirm password
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autoComplete="new-password"
                        placeholder="Confirm password"
                        passwordrules={passwordRules}
                        value={data.password_confirmation}
                        onChange={e => setData('password_confirmation', e.target.value)}
                    />
                    <InputError
                        message={errors.password_confirmation}
                    />
                </div>

                <Button className="mt-4 w-full" disabled={processing}>
                    {processing && <Spinner />}
                    Accept invitation
                </Button>
            </form>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Accept invitation',
    description: 'Set your password to activate your OpenKos account',
};
