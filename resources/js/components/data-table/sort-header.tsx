import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-react';

type SortHeaderProps = {
    column: string;
    label: string;
    currentSort: string;
    onToggle: (column: string) => void;
};

function sortPart(sort: string, column: string): string | undefined {
    return sort.split(',').find((p) => p.replace(/^-/, '') === column);
}

export function SortHeader({
    column,
    label,
    currentSort,
    onToggle,
}: SortHeaderProps) {
    const part = sortPart(currentSort ?? '', column);
    const isActive = part !== undefined;
    const isDesc = isActive && part!.startsWith('-');

    return (
        <th
            className="cursor-pointer px-4 py-3 font-medium select-none hover:text-foreground"
            onClick={() => onToggle(column)}
        >
            {label}
            {isActive && isDesc ? (
                <ChevronDown className="ml-1 inline size-3.5" />
            ) : isActive ? (
                <ChevronUp className="ml-1 inline size-3.5" />
            ) : (
                <ChevronsUpDown className="ml-1 inline size-3.5 opacity-40" />
            )}
        </th>
    );
}
