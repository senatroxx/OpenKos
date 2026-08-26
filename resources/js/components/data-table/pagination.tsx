import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { t } from '@/lib/i18n';
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
                    {t('Showing')} {data.from} {t('to')} {data.to} {t('of')}{' '}
                    {data.total} {t(noun)}
                </p>

                <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground">
                        {t('Per page')}
                    </span>
                    <Select
                        value={String(perPage)}
                        onValueChange={(v) => onPerPageChange(Number(v))}
                    >
                        <SelectTrigger className="h-8 w-16 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[10, 15, 25, 50].map((n) => (
                                <SelectItem key={n} value={String(n)}>
                                    {n}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={data.current_page === 1}
                    onClick={() => onPageChange(data.current_page - 1)}
                >
                    {t('Previous')}
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
                    {t('Next')}
                </Button>
            </div>
        </div>
    );
}
