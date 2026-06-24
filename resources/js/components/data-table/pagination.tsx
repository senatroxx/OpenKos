import { Button } from '@/components/ui/button';
import type { PaginatedData } from '@/types';

type DataTablePaginationProps<T> = {
    data: PaginatedData<T>;
    perPage: number;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
    noun: string;
};

export function DataTablePagination<T>({
    data,
    perPage,
    onPageChange,
    onPerPageChange,
    noun,
}: DataTablePaginationProps<T>) {
    return (
        <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
            <div className="flex items-center gap-4">
                <p className="text-muted-foreground">
                    Showing {data.from} to {data.to} of {data.total} {noun}
                </p>

                <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground">
                        Per page
                    </span>
                    <select
                        className="rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        value={perPage}
                        onChange={(e) =>
                            onPerPageChange(Number(e.target.value))
                        }
                    >
                        {[10, 15, 25, 50].map((n) => (
                            <option key={n} value={n}>
                                {n}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={data.current_page === 1}
                    onClick={() => onPageChange(data.current_page - 1)}
                >
                    Previous
                </Button>

                {data.links
                    .filter((link) => !isNaN(Number(link.label)))
                    .map((link) => (
                        <Button
                            key={link.label}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => onPageChange(Number(link.label))}
                        >
                            {link.label}
                        </Button>
                    ))}

                <Button
                    variant="outline"
                    size="sm"
                    disabled={data.current_page === data.last_page}
                    onClick={() => onPageChange(data.current_page + 1)}
                >
                    Next
                </Button>
            </div>
        </div>
    );
}
