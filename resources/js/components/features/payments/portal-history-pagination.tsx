import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';

export default function PortalHistoryPagination({
    currentPage,
    lastPage,
    previousHref,
    nextHref,
}: {
    currentPage: number;
    lastPage: number;
    previousHref: string | null;
    nextHref: string | null;
}) {
    if (lastPage === 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-3">
            {previousHref ? (
                <Button variant="outline" asChild>
                    <Link href={previousHref}>{t('Previous')}</Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    {t('Previous')}
                </Button>
            )}
            <p className="text-sm text-muted-foreground">
                {t('Page')} {currentPage} {t('of')} {lastPage}
            </p>
            {nextHref ? (
                <Button variant="outline" asChild>
                    <Link href={nextHref}>{t('Next')}</Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    {t('Next')}
                </Button>
            )}
        </div>
    );
}
