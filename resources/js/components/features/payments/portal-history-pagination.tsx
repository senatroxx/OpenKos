import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

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
                    <Link href={previousHref}>Previous</Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    Previous
                </Button>
            )}
            <p className="text-sm text-muted-foreground">
                Page {currentPage} of {lastPage}
            </p>
            {nextHref ? (
                <Button variant="outline" asChild>
                    <Link href={nextHref}>Next</Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    Next
                </Button>
            )}
        </div>
    );
}
