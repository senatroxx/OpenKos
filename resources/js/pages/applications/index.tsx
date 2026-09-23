import { Head, Link, usePage } from '@inertiajs/react';
import { EmptyState, StatusBadge } from '@/components/shared';
import { Card, CardContent } from '@/components/ui/card';
import { t } from '@/lib/i18n';
import { show } from '@/routes/applications';
import type { ApplicationsIndexProps } from '@/types';

export default function ApplicationsIndex({
    applications,
    operator,
}: ApplicationsIndexProps) {
    const { props } = usePage<{
        errors?: { application?: string };
        flash?: { status?: string };
    }>();

    return (
        <>
            <Head title={t('Applications')} />
            <div className="mx-auto w-full max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <header className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        {operator
                            ? t('Review applicant interest')
                            : t('Your listing applications')}
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {t('Applications')}
                    </h1>
                </header>
                {props.flash?.status && (
                    <p className="rounded-md bg-green-50 p-3 text-sm text-green-800">
                        {props.flash.status}
                    </p>
                )}
                {props.errors?.application && (
                    <p className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                        {props.errors.application}
                    </p>
                )}
                {applications.length === 0 ? (
                    <EmptyState message="Applications you submit for published listings will appear here." />
                ) : (
                    <div className="grid gap-4">
                        {applications.map((application) => (
                            <Link
                                key={application.id}
                                href={show(application.id)}
                                className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <Card className="transition-colors hover:bg-muted/30">
                                    <CardContent className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p className="font-medium">
                                                {application.unit_type?.name ??
                                                    application.property?.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {application.target_type ===
                                                'unit_type'
                                                    ? t('Unit type')
                                                    : t('Whole property')}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            domain="application"
                                            value={application.status}
                                        />
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
