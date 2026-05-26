import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronUp,
    ChevronsUpDown,
    EllipsisVertical,
    Eye,
    LogOut,
    Pencil,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import LeaseDetailSheet from '@/components/lease-detail-sheet';
import LeaseEditSheet from '@/components/lease-edit-sheet';
import MoveOutSheet from '@/components/move-out-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import leases from '@/routes/leases';

type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
};

type RoomInfo = {
    id: number;
    name: string;
    property_id: number;
    property: {
        id: number;
        name: string;
        city: { name: string } | null;
    } | null;
};

type Lease = {
    id: number;
    start_date: string;
    end_date: string | null;
    monthly_rent: string | null;
    deposit_amount: string;
    deposit_paid_at: string | null;
    deposit_refund_amount: string | null;
    deposit_refunded_at: string | null;
    rent_due_day: number;
    status: string;
    termination_date: string | null;
    termination_reason: string | null;
    notes: string | null;
    created_at: string;
    tenant: TenantInfo | null;
    room: RoomInfo | null;
};

type Property = {
    id: number;
    name: string;
};

type AvailableRoom = {
    id: number;
    name: string;
    property_id: number;
    property: { id: number; name: string; city: { name: string } | null } | null;
};

type PaginationLinks = {
    url: string | null;
    label: string;
    active: boolean;
};

type PageProps = {
    leases: {
        data: Lease[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        from: number | null;
        to: number | null;
        links: PaginationLinks[];
    };
    availableRooms: AvailableRoom[];
    properties: Property[];
    sort?: string;
    direction?: string;
    search?: string;
    status?: string;
    per_page?: number;
};

const STATUS_COLORS: Record<string, string> = {
    active: 'bg-blue-600',
    terminated: 'bg-gray-400',
};

function formatPrice(cents: string | null): string {
    if (!cents) {
        return '—';
    }

    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

function formatDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function Index({
    leases: data,
    availableRooms: _availableRooms,
    properties: allProperties,
    sort: currentSort = 'created_at',
    direction: currentDirection = 'desc',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
}: PageProps) {
    const [searchValue, setSearchValue] = useState(currentSearch);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [selectedPropertyIds, setSelectedPropertyIds] = useState<number[]>([]);
    const [propertyFilterOpen, setPropertyFilterOpen] = useState(false);

    const propertyFilterRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (propertyFilterRef.current) {
            clearTimeout(propertyFilterRef.current);
        }

        propertyFilterRef.current = setTimeout(() => {
            applyFilters({});
        }, 300);

        return () => {
            if (propertyFilterRef.current) {
                clearTimeout(propertyFilterRef.current);
            }
        };
    }, [selectedPropertyIds]);

    const [detailLease, setDetailLease] = useState<Lease | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);

    const [editOpen, setEditOpen] = useState(false);
    const [moveOutOpen, setMoveOutOpen] = useState(false);

    function applyFilters(overrides: Record<string, string>) {
        const params: Record<string, string> = {
            search: searchValue,
            status: currentStatus,
            sort: currentSort,
            direction: currentDirection,
            per_page: String(currentPerPage),
            properties: selectedPropertyIds.length > 0 ? selectedPropertyIds.join(',') : '',
            ...overrides,
        };

        Object.keys(params).forEach((key) => {
            if (!params[key]) {
                delete params[key];
            }
        });

        router.get(leases.index(), params, {
            preserveState: true,
            replace: true,
        });
    }

    function handlePropertyToggle(propertyId: number) {
        setSelectedPropertyIds((prev) => {
            const next = prev.includes(propertyId)
                ? prev.filter((id) => id !== propertyId)
                : [...prev, propertyId];

            return next;
        });
    }

    function setStatusFilter(status: string) {
        applyFilters({ status, page: '' });
    }

    function handleSearchChange(value: string) {
        setSearchValue(value);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            const params: Record<string, string> = {
                sort: currentSort,
                direction: currentDirection,
                per_page: String(currentPerPage),
            };

            if (value) {
                params.search = value;
            }

            if (currentStatus) {
                params.status = currentStatus;
            }

            if (selectedPropertyIds.length > 0) {
                params.properties = selectedPropertyIds.join(',');
            }

            router.get(leases.index(), params, {
                preserveState: true,
                replace: true,
            });
        }, 300);
    }

    function toggleSort(column: string) {
        const direction =
            currentSort === column && currentDirection === 'asc' ? 'desc' : 'asc';

        applyFilters({ sort: column, direction, page: '' });
    }

    function goToPage(page: number) {
        applyFilters({ page: String(page) });
    }

    function SortIcon({ column: _column }: { column: string }) {
        if (currentSort !== _column) {
            return <ChevronsUpDown className="ml-1 inline size-3.5 opacity-40" />;
        }

        return currentDirection === 'asc' ? (
            <ChevronUp className="ml-1 inline size-3.5" />
        ) : (
            <ChevronDown className="ml-1 inline size-3.5" />
        );
    }

    function openDetail(lease: Lease) {
        setDetailLease(lease);
        setDetailOpen(true);
    }

    function openMoveOutFromDetail() {
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    function openEditFromDetail() {
        setDetailOpen(false);
        setEditOpen(true);
    }

    return (
        <>
            <Head title="Leases" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Leases"
                        description="View all leases across properties"
                    />
                </div>

                <div className="flex items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search tenant, room, property..."
                            className="pl-9"
                            value={searchValue}
                            onChange={(e) => handleSearchChange(e.target.value)}
                        />
                        {searchValue && (
                            <button
                                onClick={() => {
                                    if (debounceRef.current) {
                                        clearTimeout(debounceRef.current);
                                    }

                                    setSearchValue('');
                                    applyFilters({ search: '', page: '' });
                                }}
                                className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"

                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </div>

                    <Popover open={propertyFilterOpen} onOpenChange={setPropertyFilterOpen}>
                        <PopoverTrigger asChild>
                            <Button
                                variant="outline"
                                role="combobox"
                                aria-expanded={propertyFilterOpen}
                                className="w-60 justify-between font-normal"
                            >
                                {selectedPropertyIds.length === 0
                                    ? 'All properties'
                                    : `${selectedPropertyIds.length} selected`}
                                <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-60 p-0">
                            <Command>
                                <CommandInput placeholder="Search property..." />
                                <CommandList>
                                    <CommandEmpty>No property found.</CommandEmpty>
                                    <CommandGroup>
                                        {allProperties.map((property) => (
                                            <CommandItem
                                                key={property.id}
                                                value={property.name}
                                                onSelect={() => handlePropertyToggle(property.id)}
                                            >
                                                <Check
                                                    className={`mr-2 size-4 ${
                                                        selectedPropertyIds.includes(property.id)
                                                            ? 'opacity-100'
                                                            : 'opacity-0'
                                                    }`}
                                                />
                                                {property.name}
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>

                    <div className="flex items-center gap-1 rounded-lg border p-1">
                        {(['', 'active', 'terminated'] as const).map((value) => (
                            <Button
                                key={value}
                                variant={currentStatus === value ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter(value)}
                            >
                                {value === '' ? 'All' : value.charAt(0).toUpperCase() + value.slice(1)}
                            </Button>
                        ))}
                    </div>
                </div>

                {data.data.length === 0 ? (
                    <div className="flex flex-1 items-center justify-center rounded-lg border py-16">
                        <p className="text-muted-foreground">No leases found.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    {[
                                        { key: 'tenant_name', label: 'Tenant' },
                                        { key: 'room_name', label: 'Room' },
                                        { key: 'property_name', label: 'Property' },
                                        { key: 'start_date', label: 'Start' },
                                        { key: 'end_date', label: 'End' },
                                        { key: 'monthly_rent', label: 'Rent' },
                                        { key: 'status', label: 'Status' },
                                    ].map(({ key, label }) => (
                                        <th
                                            key={key}
                                            className="cursor-pointer px-4 py-3 font-medium select-none hover:text-foreground"
                                            onClick={() => toggleSort(key)}
                                        >
                                            {label}
                                            <SortIcon column={key} />
                                        </th>
                                    ))}
                                    <th className="w-12 px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((lease) => {
                                    const propertyName = lease.room?.property?.name ?? '—';
                                    const roomRoute = lease.room
                                        ? `/properties/${lease.room.property_id}/rooms`
                                        : null;

                                    return (
                                        <tr
                                            key={lease.id}
                                            className="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                                            onClick={() => openDetail(lease)}
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {lease.tenant?.name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {roomRoute ? (
                                                    <Link
                                                        href={roomRoute}
                                                        className="text-blue-600 hover:underline"
                                                        onClick={(e: React.MouseEvent) =>
                                                            e.stopPropagation()
                                                        }
                                                    >
                                                        {lease.room?.name ?? '—'}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {propertyName}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {formatDate(lease.start_date)}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums text-muted-foreground">
                                                {formatDate(lease.end_date)}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {formatPrice(lease.monthly_rent)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    className={`${STATUS_COLORS[lease.status] ?? 'bg-gray-400'} text-white`}
                                                >
                                                    {lease.status === 'active'
                                                        ? 'Active'
                                                        : 'Terminated'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                        onClick={(e: React.MouseEvent) =>
                                                            e.stopPropagation()
                                                        }
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                        >
                                                            <EllipsisVertical className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                        onClick={(e: React.MouseEvent) =>
                                                            e.stopPropagation()
                                                        }
                                                    >
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                openDetail(lease)
                                                            }
                                                        >
                                                            <Eye className="size-4" />
                                                            View
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() => {
                                                                setDetailLease(lease);
                                                                setDetailOpen(false);
                                                                setEditOpen(true);
                                                            }}
                                                        >
                                                            <Pencil className="size-4" />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        {lease.status === 'active' && (
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onClick={() => {
                                                                    setDetailLease(lease);
                                                                    setDetailOpen(false);
                                                                    setMoveOutOpen(true);
                                                                }}
                                                            >
                                                                <LogOut className="size-4" />
                                                                Move Out
                                                            </DropdownMenuItem>
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>

                        <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
                            <div className="flex items-center gap-4">
                                <p className="text-muted-foreground">
                                    Showing {data.from} to {data.to} of {data.total} leases
                                </p>

                                <div className="flex items-center gap-2">
                                    <span className="text-muted-foreground text-xs">Per page</span>
                                    <select
                                        className="rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        value={currentPerPage}
                                        onChange={(e) =>
                                            applyFilters({ per_page: e.target.value, page: '' })
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
                                    onClick={() => goToPage(data.current_page - 1)}
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
                                            onClick={() => goToPage(Number(link.label))}
                                        >
                                            {link.label}
                                        </Button>
                                    ))}

                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={data.current_page === data.last_page}
                                    onClick={() => goToPage(data.current_page + 1)}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <LeaseDetailSheet
                lease={detailLease}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onMoveOut={detailLease?.status === 'active' ? openMoveOutFromDetail : undefined}
                onEdit={detailLease ? openEditFromDetail : undefined}
            />

            <LeaseEditSheet
                lease={detailLease}
                open={editOpen}
                onOpenChange={setEditOpen}
            />

            <MoveOutSheet
                lease={
                    detailLease
                        ? {
                            id: detailLease.id,
                            tenant: detailLease.tenant,
                            room: detailLease.room,
                        }
                        : null
                }
                availableRooms={_availableRooms}
                open={moveOutOpen}
                onOpenChange={setMoveOutOpen}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        {
            title: 'Leases',
            href: leases.index(),
        },
    ],
};
