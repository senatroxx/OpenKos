import { Head, Link, useForm } from '@inertiajs/react';
import { InputError, StatusBadge } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { convert, index, transition } from '@/routes/applications';
import type {
    ApplicationActionErrors,
    ApplicationShowProps,
    ApplicationTransitionFormData,
    ApplicationWithdrawalFormData,
    OperatorApplication,
} from '@/types';

export default function ApplicationShow({
    application,
    operator,
}: ApplicationShowProps) {
    const detail = application as OperatorApplication;
    const canWithdraw =
        !operator && ['new', 'reviewing'].includes(application.status);
    const transitionForm = useForm<ApplicationTransitionFormData>({
        status: application.status,
        operator_notes: '',
        applicant_feedback: '',
    });
    const conversionForm = useForm<Record<string, never>>({});
    const withdrawalForm = useForm<ApplicationWithdrawalFormData>({
        status: 'withdrawn',
    });

    function submitTransition(event: React.FormEvent) {
        event.preventDefault();
        transitionForm.submit(transition(application.id), {
            preserveScroll: true,
        });
    }

    function submitConversion(event: React.FormEvent) {
        event.preventDefault();
        conversionForm.submit(convert(application.id), {
            preserveScroll: true,
        });
    }

    function submitWithdrawal(event: React.FormEvent) {
        event.preventDefault();
        withdrawalForm.submit(transition(application.id), {
            preserveScroll: true,
        });
    }

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
                        <form
                            onSubmit={submitTransition}
                            className="grid gap-3"
                        >
                            <select
                                name="status"
                                value={transitionForm.data.status}
                                onChange={(event) =>
                                    transitionForm.setData(
                                        'status',
                                        event.target
                                            .value as ApplicationTransitionFormData['status'],
                                    )
                                }
                                className="h-10 rounded-md border bg-background px-3"
                                disabled={transitionForm.processing}
                            >
                                <option value="new" disabled>
                                    New
                                </option>
                                <option value="reviewing">Reviewing</option>
                                <option value="accepted">Accepted</option>
                                <option value="rejected">Rejected</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                            <InputError
                                message={transitionForm.errors.status}
                            />
                            <Textarea
                                name="operator_notes"
                                value={transitionForm.data.operator_notes}
                                onChange={(event) =>
                                    transitionForm.setData(
                                        'operator_notes',
                                        event.target.value,
                                    )
                                }
                                placeholder={t('Private operator notes')}
                                disabled={transitionForm.processing}
                            />
                            <InputError
                                message={transitionForm.errors.operator_notes}
                            />
                            <Textarea
                                name="applicant_feedback"
                                value={transitionForm.data.applicant_feedback}
                                onChange={(event) =>
                                    transitionForm.setData(
                                        'applicant_feedback',
                                        event.target.value,
                                    )
                                }
                                placeholder={t('Applicant-facing feedback')}
                                disabled={transitionForm.processing}
                            />
                            <InputError
                                message={
                                    transitionForm.errors.applicant_feedback
                                }
                            />
                            <InputError
                                message={
                                    (
                                        transitionForm.errors as ApplicationActionErrors
                                    ).application
                                }
                            />
                            <Button
                                type="submit"
                                disabled={transitionForm.processing}
                            >
                                {t('Save application')}
                            </Button>
                        </form>
                        {detail.status === 'accepted' &&
                            !detail.converted_tenant_id && (
                                <form onSubmit={submitConversion}>
                                    <InputError
                                        message={
                                            (
                                                conversionForm.errors as ApplicationActionErrors
                                            ).application
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={conversionForm.processing}
                                    >
                                        {t('Convert to Tenant')}
                                    </Button>
                                </form>
                            )}
                    </section>
                )}
                {canWithdraw && (
                    <form onSubmit={submitWithdrawal}>
                        <InputError
                            message={
                                (
                                    withdrawalForm.errors as ApplicationActionErrors
                                ).application
                            }
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={withdrawalForm.processing}
                        >
                            {t('Withdraw application')}
                        </Button>
                    </form>
                )}
            </div>
        </>
    );
}
