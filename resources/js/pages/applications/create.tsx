import { Form, Head } from '@inertiajs/react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { store } from '@/routes/applications';
import type { ApplicationCreateProps } from '@/types';

export default function ApplicationCreate({ target }: ApplicationCreateProps) {
    return (
        <>
            <Head title={t('Apply for a listing')} />
            <div className="mx-auto w-full max-w-2xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <header>
                    <p className="text-sm text-muted-foreground">
                        {target.property_name}
                    </p>
                    <h1 className="text-3xl font-semibold">
                        {target.unit_type_name ?? t('Whole property')}
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        {t('Tell the property team a little about your plans.')}
                    </p>
                </header>
                <Form
                    {...store.form()}
                    className="grid gap-5 rounded-xl border bg-card p-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="target_type"
                                value={target.target_type}
                            />
                            <input
                                type="hidden"
                                name="property_slug"
                                value={target.property_slug}
                            />
                            {target.unit_type_slug && (
                                <input
                                    type="hidden"
                                    name="unit_type_slug"
                                    value={target.unit_type_slug}
                                />
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="applicant_phone">
                                    {t('Phone')}
                                </Label>
                                <Input
                                    id="applicant_phone"
                                    name="applicant_phone"
                                    autoComplete="tel"
                                />
                                <InputError message={errors.applicant_phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="intended_move_in_date">
                                    {t('Preferred move-in date')}
                                </Label>
                                <Input
                                    id="intended_move_in_date"
                                    name="intended_move_in_date"
                                    type="date"
                                />
                                <InputError
                                    message={errors.intended_move_in_date}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="intended_move_in_timeframe">
                                    {t('Move-in timeframe')}
                                </Label>
                                <Input
                                    id="intended_move_in_timeframe"
                                    name="intended_move_in_timeframe"
                                    placeholder={t('For example, early summer')}
                                />
                                <InputError
                                    message={errors.intended_move_in_timeframe}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="applicant_message">
                                    {t('Message')}
                                </Label>
                                <Textarea
                                    id="applicant_message"
                                    name="applicant_message"
                                    rows={5}
                                />
                                <InputError
                                    message={errors.applicant_message}
                                />
                            </div>
                            <InputError message={errors.application} />
                            <Button type="submit" disabled={processing}>
                                {t('Submit application')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
