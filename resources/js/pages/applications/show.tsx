import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import WholePropertyLeaseSheet from '@/components/features/leases/whole-property-lease-sheet';
import AssignTenantSheet from '@/components/features/units/assign-tenant-sheet';
import { InputError, StatusBadge } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { todayISO } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { index, transition } from '@/routes/applications';
import applicationReservations from '@/routes/applications/reservations';
import reservations from '@/routes/reservations';
import type {
    ApplicationActionErrors,
    ApplicationShowProps,
    ApplicationTransitionFormData,
    ApplicationWithdrawalFormData,
    OperatorApplication,
    ReservationConfirmationFormData,
    ReservationLeaseContext,
    ReservationFormData,
} from '@/types';
import type { Property, Unit } from '@/types/models';

export default function ApplicationShow({
    application,
    operator,
}: ApplicationShowProps) {
    const detail = application as OperatorApplication;
    const reservation = application.reservation ?? null;
    const leaseTargetProperty = application.property?.slug
        ? (application.property as Property)
        : null;
    const canWithdraw =
        !operator && ['new', 'reviewing'].includes(application.status);
    const today = todayISO();
    const requestDate =
        application.intended_move_in_date &&
        application.intended_move_in_date >= today
            ? application.intended_move_in_date
            : today;
    const transitionForm = useForm<ApplicationTransitionFormData>({
        status: application.status,
        operator_notes: '',
        applicant_feedback: '',
    });
    const withdrawalForm = useForm<ApplicationWithdrawalFormData>({
        status: 'withdrawn',
    });
    const reservationRequestForm = useForm<ReservationFormData>({
        move_in_date: requestDate,
    });
    const confirmationForm = useForm<ReservationConfirmationFormData>({
        unit_id: application.available_units?.[0]?.id.toString() ?? '',
    });
    const rejectionForm = useForm<Record<string, never>>({});
    const cancellationForm = useForm<Record<string, never>>({});
    const [leaseSheetOpen, setLeaseSheetOpen] = useState(false);
    const reservationId =
        reservation === null ? null : reservation.id;
    const reservationExpired =
        reservation?.status === 'confirmed' &&
        reservation.is_expired;
    const displayedReservationStatus = reservationExpired
        ? 'expired'
        : reservation?.status;
    const canRequestReservation = application.can_request_reservation;
    const leaseContext: ReservationLeaseContext | null =
        operator &&
        reservation &&
        reservation.status === 'confirmed' &&
        !reservationExpired
            ? {
                  id: reservation.id,
                  move_in_date: reservation.move_in_date,
                  applicant: { name: detail.applicant.name },
                  rental: {
                      billing_unit:
                          (application.rental_billing_unit ??
                              'month') as ReservationLeaseContext['rental']['billing_unit'],
                      billing_interval:
                          application.rental_billing_interval ?? 1,
                      currency: application.rental_currency ?? 'USD',
                      amount: application.rental_amount ?? '0',
                  },
              }
            : null;
    const leaseUnit: Unit | null =
        reservation === null
            ? null
            : reservation.unit === null
              ? null
              : {
                    ...reservation.unit,
                    floor: null,
                    description: null,
                    size_sqm: null,
                    occupied_count: 0,
                    notes: null,
                };

    function submitTransition(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        transitionForm.submit(transition(application.id), {
            preserveScroll: true,
        });
    }

    function submitWithdrawal(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        withdrawalForm.submit(transition(application.id), {
            preserveScroll: true,
        });
    }

    function submitReservationRequest(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        reservationRequestForm.submit(
            applicationReservations.store(application.id),
            { preserveScroll: true },
        );
    }

    function submitConfirmation(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (reservationId === null) {
            return;
        }

        confirmationForm.submit(reservations.confirm(reservationId), {
            preserveScroll: true,
        });
    }

    function rejectReservation() {
        if (reservationId === null) {
            return;
        }

        rejectionForm.submit(reservations.reject(reservationId), {
            preserveScroll: true,
        });
    }

    function cancelReservation() {
        if (reservationId === null) {
            return;
        }

        cancellationForm.submit(reservations.cancel(reservationId), {
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
                            <Label htmlFor="application-status">
                                {t('Application status')}
                            </Label>
                            <select
                                id="application-status"
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
                            <Label htmlFor="operator-notes">
                                {t('Private operator notes')}
                            </Label>
                            <Textarea
                                id="operator-notes"
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
                            <Label htmlFor="applicant-feedback">
                                {t('Applicant-facing feedback')}
                            </Label>
                            <Textarea
                                id="applicant-feedback"
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
                    </section>
                )}
                {(application.status === 'accepted' || reservation) && (
                    <section className="space-y-4 rounded-xl border bg-card p-5">
                        <div>
                            <h2 className="font-semibold">
                                {t('Reservation')}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t(
                                    'A reservation holds inventory only after the property team confirms it.',
                                )}
                            </p>
                        </div>
                        {reservation ? (
                            <div className="space-y-4 rounded-md border p-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="font-medium">
                                            {t('Reservation #:id', {
                                                id: reservation.id,
                                            })}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {t('Move-in date')}: {reservation.move_in_date}
                                        </p>
                                        {reservation.unit && (
                                            <p className="text-sm text-muted-foreground">
                                                {t('Unit')}: {reservation.unit.name}
                                            </p>
                                        )}
                                        {displayedReservationStatus ===
                                            'confirmed' &&
                                            reservation.expires_at && (
                                                <p className="text-sm text-muted-foreground">
                                                    {t('Hold expires')}: {new Date(reservation.expires_at).toLocaleString()}
                                                </p>
                                            )}
                                    </div>
                                    <StatusBadge
                                        domain="reservation"
                                        value={displayedReservationStatus!}
                                    />
                                </div>
                                {operator &&
                                    reservation.status === 'pending' &&
                                    application.status === 'accepted' && (
                                        <form
                                            onSubmit={submitConfirmation}
                                            className="grid gap-3"
                                        >
                                            {application.target_type ===
                                                'unit_type' && (
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="reservation-unit">
                                                            {t('Available unit')}
                                                        </Label>
                                                        <select
                                                            id="reservation-unit"
                                                            value={confirmationForm.data.unit_id}
                                                            onChange={(event) =>
                                                                confirmationForm.setData(
                                                                    'unit_id',
                                                                    event.target.value,
                                                                )
                                                            }
                                                            className="h-10 rounded-md border bg-background px-3"
                                                            required
                                                            disabled={confirmationForm.processing}
                                                        >
                                                            <option value="">
                                                                {t('Select a unit')}
                                                            </option>
                                                            {(application.available_units ?? []).map(
                                                                (unit) => (
                                                                    <option
                                                                        key={unit.id}
                                                                        value={unit.id}
                                                                    >
                                                                        {unit.name}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                        <InputError
                                                            message={confirmationForm.errors.unit_id}
                                                        />
                                                        {(application.available_units ?? []).length ===
                                                            0 && (
                                                            <p className="text-sm text-muted-foreground">
                                                                {t('No units currently have capacity for this move-in date.')}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        confirmationForm.processing ||
                                                        (application.target_type ===
                                                            'unit_type' &&
                                                            (application.available_units ?? []).length ===
                                                                0)
                                                    }
                                                >
                                                    {t('Confirm reservation')}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={rejectionForm.processing}
                                                    onClick={rejectReservation}
                                                >
                                                    {t('Reject request')}
                                                </Button>
                                            </div>
                                        </form>
                                    )}
                                {operator &&
                                    reservation.status === 'confirmed' &&
                                    !reservationExpired &&
                                    leaseContext &&
                                    leaseTargetProperty &&
                                    (application.target_type ===
                                    'whole_property'
                                        ? true
                                        : leaseUnit !== null) && (
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                setLeaseSheetOpen(true)
                                            }
                                        >
                                            {t('Create lease')}
                                        </Button>
                                    )}
                                {(reservation.status === 'pending' ||
                                    (reservation.status === 'confirmed' &&
                                        !reservationExpired)) && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={cancellationForm.processing}
                                        onClick={cancelReservation}
                                    >
                                        {t('Cancel reservation')}
                                    </Button>
                                )}
                            </div>
                        ) : operator ? (
                            <p className="text-sm text-muted-foreground">
                                {t('Waiting for the applicant to request a reservation.')}
                            </p>
                        ) : null}
                        {canRequestReservation && (
                            <form
                                onSubmit={submitReservationRequest}
                                className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="reservation-move-in-date">
                                        {t('Requested move-in date')}
                                    </Label>
                                    <Input
                                        id="reservation-move-in-date"
                                        type="date"
                                        min={today}
                                        value={reservationRequestForm.data.move_in_date}
                                        onChange={(event) =>
                                            reservationRequestForm.setData(
                                                'move_in_date',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        disabled={reservationRequestForm.processing}
                                    />
                                    <InputError
                                        message={reservationRequestForm.errors.move_in_date}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={reservationRequestForm.processing}
                                >
                                    {t('Request reservation')}
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
                {leaseContext && leaseTargetProperty &&
                    (application.target_type === 'whole_property' ? (
                        <WholePropertyLeaseSheet
                            key={`reservation-${leaseContext.id}`}
                            property={leaseTargetProperty}
                            tenants={[]}
                            reservation={leaseContext}
                            open={leaseSheetOpen}
                            onOpenChange={setLeaseSheetOpen}
                        />
                    ) : leaseUnit && (
                        <AssignTenantSheet
                            key={`reservation-${leaseContext.id}`}
                            unit={leaseUnit}
                            property={leaseTargetProperty}
                            reservation={leaseContext}
                            open={leaseSheetOpen}
                            onOpenChange={setLeaseSheetOpen}
                        />
                    ))}
            </div>
        </>
    );
}
