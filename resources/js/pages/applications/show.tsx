import { Form, Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { convert, index, transition } from '@/routes/applications';
import type { ApplicationShowProps, OperatorApplication } from '@/types';

export default function ApplicationShow({
    application,
    operator,
}: ApplicationShowProps) {
    const detail = application as OperatorApplication;
    const canWithdraw =
        !operator && ['new', 'reviewing'].includes(application.status);

    return (
        <>
            <Head title={t('Application')} />
            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <Link
                    href={index()}
                    className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                >
                    {t('Back to applications')}
                </Link>
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-semibold">
                            {application.unit_type?.name ??
                                application.property?.name}
                        </h1>
                        <p className="text-muted-foreground">
                            {application.target_type === 'unit_type'
                                ? t('Unit type')
                                : t('Whole property')}
                        </p>
                    </div>
                    <StatusBadge
                        domain="application"
                        value={application.status}
                    />
                </div>
                {application.applicant_feedback && (
                    <section className="rounded-xl border bg-card p-5">
                        <h2 className="font-semibold">
                            {t('Message from the property team')}
                        </h2>
                        <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                            {application.applicant_feedback}
                        </p>
                    </section>
                )}
                {operator && (
                    <section className="grid gap-4 rounded-xl border bg-card p-5">
                        <p>
                            <strong>{detail.applicant.name}</strong>
                            <br />
                            {detail.applicant.email}
                            {detail.applicant.phone && (
                                <>
                                    <br />
                                    {detail.applicant.phone}
                                </>
                            )}
                        </p>
                        <p className="text-sm whitespace-pre-wrap">
                            {detail.applicant_message}
                        </p>
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {detail.operator_notes}
                        </p>
                        <Form
                            {...transition.form(application.id)}
                            className="grid gap-3"
                        >
                            <select
                                name="status"
                                defaultValue={detail.status}
                                className="h-10 rounded-md border bg-background px-3"
                            >
                                <option value="reviewing">Reviewing</option>
                                <option value="accepted">Accepted</option>
                                <option value="rejected">Rejected</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                            <Textarea
                                name="operator_notes"
                                placeholder={t('Private operator notes')}
                            />
                            <Textarea
                                name="applicant_feedback"
                                placeholder={t('Applicant-facing feedback')}
                            />
                            <Button type="submit">
                                {t('Save application')}
                            </Button>
                        </Form>
                        {detail.status === 'accepted' &&
                            !detail.converted_tenant_id && (
                                <Form {...convert.form(application.id)}>
                                    <Button type="submit" variant="secondary">
                                        {t('Convert to Tenant')}
                                    </Button>
                                </Form>
                            )}
                    </section>
                )}
                {canWithdraw && (
                    <Form {...transition.form(application.id)}>
                        <input type="hidden" name="status" value="withdrawn" />
                        <Button type="submit" variant="outline">
                            {t('Withdraw application')}
                        </Button>
                    </Form>
                )}
            </div>
        </>
    );
}
