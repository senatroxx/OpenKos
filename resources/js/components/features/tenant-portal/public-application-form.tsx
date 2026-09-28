import { Link, useForm } from '@inertiajs/react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatBillingOptionLabel, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { show as applicationShow, store } from '@/routes/applications';
import type { PublicApplicationFormProps } from '@/types';

export default function PublicApplicationForm({
    target,
    existingApplication,
}: PublicApplicationFormProps) {
    const { data, setData, submit, processing, errors } = useForm({
        target_type: target.target_type,
        property_slug: target.property_slug,
        unit_type_slug: target.unit_type_slug ?? '',
        intended_move_in_date: '',
        rental_billing_unit: target.rental_options[0]?.billing_unit ?? '',
        rental_billing_interval: target.rental_options[0]?.billing_interval ?? 0,
        rental_currency: target.rental_options[0]?.currency ?? '',
        applicant_message: '',
    });
    const selectedOptionKey = `${data.rental_billing_interval}|${data.rental_billing_unit}|${data.rental_currency}`;

    if (existingApplication) {
        return (
            <div className="border-t pt-5" aria-live="polite">
                <p className="text-sm text-muted-foreground">{t('Application submitted')}</p>
                <h2 className="mt-1 text-xl font-semibold">{t('Under review')}</h2>
                <Button asChild className="mt-4">
                    <Link href={applicationShow(existingApplication.id)}>
                        {t('View application')}
                    </Link>
                </Button>
            </div>
        );
    }

    return (
        <div id="application-form" className="scroll-mt-6 border-t pt-5">
            <div className="mb-5">
                <p className="text-sm text-muted-foreground">{t('Apply for')}</p>
                <h2 className="text-xl font-semibold">
                    {target.unit_type_name ?? t('Whole property')}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">{target.property_name}</p>
            </div>
            <form
                className="grid gap-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    submit(store(), { preserveScroll: true });
                }}
            >
                        <div className="grid gap-2">
                            <Label htmlFor="rental_option">{t('Rental option')}</Label>
                            <select
                                id="rental_option"
                                name="rental_option"
                                value={selectedOptionKey}
                                className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                onChange={(event) => {
                                    const [interval, unit, currency] = event.target.value.split('|');
                                    setData('rental_billing_interval', Number(interval));
                                    setData('rental_billing_unit', unit);
                                    setData('rental_currency', currency);
                                }}
                            >
                                {target.rental_options.map((option) => (
                                    <option key={`${option.billing_interval}|${option.billing_unit}|${option.currency}`} value={`${option.billing_interval}|${option.billing_unit}|${option.currency}`}>
                                        {formatBillingOptionLabel(option.billing_interval, option.billing_unit)} · {formatPrice(option.amount, option.currency)}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="intended_move_in_date">{t('Preferred move-in date')}</Label>
                            <Input
                                id="intended_move_in_date"
                                type="date"
                                value={data.intended_move_in_date}
                                onChange={(event) => setData('intended_move_in_date', event.target.value)}
                            />
                            <InputError message={errors.intended_move_in_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="applicant_message">{t('Message')}</Label>
                            <Textarea
                                id="applicant_message"
                                rows={5}
                                value={data.applicant_message}
                                onChange={(event) => setData('applicant_message', event.target.value)}
                            />
                            <InputError message={errors.applicant_message} />
                        </div>
                        <InputError
                            message={(errors as Record<string, string | undefined>).application}
                        />
                        <Button type="submit" disabled={processing}>
                            {t('Submit application')}
                        </Button>
            </form>
        </div>
    );
}
