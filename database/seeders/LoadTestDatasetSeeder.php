<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Models\City;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Region;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitRate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class LoadTestDatasetSeeder extends Seeder
{
    private const NAMESPACE = 'ope-177-load-test';

    private const PROPERTY_COUNT = 8;

    private const UNITS_PER_PROPERTY = 12;

    private const TENANT_COUNT = 48;

    private const HISTORICAL_LEASE_COUNT = 12;

    private const INVOICE_PERIOD_COUNT = 3;

    private const MAINTENANCE_TICKET_COUNT = 24;

    private const STAFF_PROPERTY_COUNT = 4;

    public function run(): void
    {
        /** @var array{enabled: bool, users: array<string, array{email: string|null}>} $fixtures */
        $fixtures = config('load-test.fixtures');
        /** @var array{enabled: bool} $dataset */
        $dataset = config('load-test.dataset');

        $this->ensureAllowed($fixtures['enabled'], $dataset['enabled']);
        $users = $this->resolveUsers($fixtures['users']);
        [$region, $city] = $this->resolveGeography();

        DB::transaction(function () use ($users, $region, $city): void {
            $this->seedDataset($users, $region, $city);
        });
    }

    private function ensureAllowed(bool $fixturesEnabled, bool $datasetEnabled): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Load-test dataset cannot be seeded in production.');
        }

        if (! $fixturesEnabled) {
            throw new RuntimeException('Set LOAD_TEST_FIXTURES_ENABLED=true before seeding the load-test dataset.');
        }

        if (! $datasetEnabled) {
            throw new RuntimeException('Set LOAD_TEST_DATASET_ENABLED=true before seeding the load-test dataset.');
        }
    }

    /**
     * @param  array<string, array{email: string|null}>  $configuredUsers
     * @return array{owner: User, admin: User, staff: User, tenant: User}
     */
    private function resolveUsers(array $configuredUsers): array
    {
        $users = [];

        foreach (['owner', 'admin', 'staff', 'tenant'] as $persona) {
            $email = $configuredUsers[$persona]['email'] ?? null;

            if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("LOAD_TEST_{$this->envPersona($persona)}_EMAIL must be configured before seeding the dataset.");
            }

            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                throw new RuntimeException("Load-test {$persona} user [{$email}] was not found. Run LoadTestSeeder first.");
            }

            $users[$persona] = $user;
        }

        if (! $users['owner']->isOwner()) {
            throw new RuntimeException('Configured load-test owner does not have the owner role.');
        }

        if (! $users['admin']->hasRole('admin')) {
            throw new RuntimeException('Configured load-test manager does not have the admin role.');
        }

        if (! $users['staff']->hasRole('staff')) {
            throw new RuntimeException('Configured load-test staff user does not have the staff role.');
        }

        if (! $users['tenant']->tenant()->exists()) {
            throw new RuntimeException('Configured load-test tenant user does not have a tenant profile.');
        }

        return $users;
    }

    /**
     * @return array{0: Region, 1: City}
     */
    private function resolveGeography(): array
    {
        $region = Region::query()->where('country_code', 'ID')->first();

        if (! $region) {
            throw new RuntimeException('Load-test dataset requires at least one seeded Indonesian region.');
        }

        $city = City::query()->where('region_id', $region->id)->first();

        if (! $city) {
            throw new RuntimeException('Load-test dataset requires at least one city in the seeded region.');
        }

        return [$region, $city];
    }

    /**
     * @param  array{owner: User, admin: User, staff: User, tenant: User}  $users
     */
    private function seedDataset(array $users, Region $region, City $city): void
    {
        $properties = collect(range(1, self::PROPERTY_COUNT))
            ->map(fn (int $index): Property => $this->upsertProperty($index, $region, $city));
        $units = $properties->flatMap(fn (Property $property): Collection => collect(range(1, self::UNITS_PER_PROPERTY))
            ->map(fn (int $index): Unit => $this->upsertUnit($property, $index)));
        $tenants = collect(range(1, self::TENANT_COUNT))
            ->map(fn (int $index): Tenant => $this->upsertTenant($users['tenant'], $index));

        $this->assignPropertyScopes($users, $properties);

        $activeLeases = $tenants
            ->values()
            ->map(fn (Tenant $tenant, int $index): Lease => $this->upsertActiveLease(
                $units->values()->get($index),
                $tenant,
                $index + 1,
            ));

        $this->seedHistoricalLeases($units, $tenants);
        $this->seedInvoicesAndPayments($activeLeases, $users);
        $this->seedMaintenanceTickets($units, $properties, $users);
    }

    /**
     * @param  array{owner: User, admin: User, staff: User, tenant: User}  $users
     * @param  Collection<int, Property>  $properties
     */
    private function assignPropertyScopes(array $users, Collection $properties): void
    {
        $propertyIds = $properties->pluck('id')->all();
        $staffPropertyIds = $properties->take(self::STAFF_PROPERTY_COUNT)->pluck('id')->all();

        $users['admin']->properties()->syncWithoutDetaching($propertyIds);
        $users['staff']->properties()->syncWithoutDetaching($staffPropertyIds);
    }

    private function upsertProperty(int $index, Region $region, City $city): Property
    {
        $slug = sprintf('%s-property-%02d', self::NAMESPACE, $index);
        $property = Property::withTrashed()->firstOrNew(['slug' => $slug]);

        if ($property->trashed()) {
            $property->restoreQuietly();
        }

        $property->forceFill([
            'name' => sprintf('OPE-177 Load Test Property %02d', $index),
            'type' => 'boarding_house',
            'slug' => $slug,
            'address' => sprintf('Load-test address %02d', $index),
            'region_id' => $region->id,
            'city_id' => $city->id,
            'postal_code' => sprintf('40%03d', $index),
            'phone' => sprintf('+622100177%02d', $index),
            'description' => 'Reserved OPE-177 load-test property.',
            'is_active' => true,
        ])->saveQuietly();

        return $property->refresh();
    }

    private function upsertUnit(Property $property, int $index): Unit
    {
        $name = sprintf('OPE-177 Unit %02d', $index);
        $unit = Unit::withTrashed()->firstOrNew([
            'property_id' => $property->id,
            'name' => $name,
        ]);

        if ($unit->trashed()) {
            $unit->restoreQuietly();
        }

        $unit->forceFill([
            'property_id' => $property->id,
            'name' => $name,
            'slug' => sprintf('%s-unit-%02d', $property->slug, $index),
            'floor' => (string) (intdiv($index - 1, 4) + 1),
            'size_sqm' => 18 + ($index % 5),
            'capacity' => 1,
            'status' => UnitStatus::Available,
            'notes' => 'Reserved OPE-177 load-test unit.',
        ])->saveQuietly();

        $rate = UnitRate::query()->firstOrNew([
            'unit_id' => $unit->id,
            'billing_interval' => 1,
            'billing_unit' => 'month',
        ]);
        $rate->forceFill([
            'unit_id' => $unit->id,
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'amount' => 1_500_000 + ($index * 50_000),
            'currency' => 'IDR',
            'is_active' => true,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_until' => null,
        ])->saveQuietly();

        return $unit->refresh();
    }

    private function upsertTenant(User $tenantUser, int $index): Tenant
    {
        $tenant = $index === 1
            ? $tenantUser->tenant()->withTrashed()->firstOrFail()
            : Tenant::withTrashed()->firstOrNew(['id_card_number' => $this->tenantIdCard($index)]);

        if ($tenant->trashed()) {
            $tenant->restoreQuietly();
        }

        $tenant->forceFill([
            'user_id' => $index === 1 ? $tenantUser->id : null,
            'name' => $index === 1 ? $tenantUser->name : sprintf('OPE-177 Load Test Tenant %03d', $index),
            'phone' => sprintf('+628120177%04d', $index),
            'id_card_number' => $this->tenantIdCard($index),
            'is_active' => true,
        ])->saveQuietly();

        return $tenant->refresh();
    }

    private function tenantIdCard(int $index): string
    {
        return $index === 1
            ? 'load-test-tenant'
            : sprintf('%s-tenant-%03d', self::NAMESPACE, $index);
    }

    private function upsertActiveLease(Unit $unit, Tenant $tenant, int $index): Lease
    {
        $rate = $unit->rates()
            ->where('billing_interval', 1)
            ->where('billing_unit', 'month')
            ->firstOrFail();
        $reference = sprintf('%s-lease-%03d', self::NAMESPACE, $index);
        $lease = Lease::withTrashed()->firstOrNew(['reference' => $reference]);

        if ($lease->trashed()) {
            $lease->restoreQuietly();
        }

        $lease->forceFill([
            'reference' => $reference,
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => now()->subMonths(3)->startOfMonth()->addDays(($index - 1) % 10)->toDateString(),
            'end_date' => null,
            'rent_amount' => $rate->amount,
            'currency' => 'IDR',
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'billing_strategy' => 'advance',
            'is_custom_price' => false,
            'unit_rate_id' => $rate->id,
            'deposit_amount' => 1_500_000,
            'deposit_paid_at' => now()->subMonths(3),
            'deposit_refund_amount' => null,
            'deposit_refunded_at' => null,
            'rent_due_day' => (($index - 1) % 20) + 1,
            'status' => LeaseStatus::Active,
            'termination_date' => null,
            'termination_reason' => null,
            'notes' => 'Reserved OPE-177 active load-test lease.',
        ])->saveQuietly();

        $lease->tenants()->sync([$tenant->id => ['is_primary' => true]]);
        $unit->forceFill(['status' => UnitStatus::Occupied])->saveQuietly();

        return $lease->refresh();
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @param  Collection<int, Tenant>  $tenants
     */
    private function seedHistoricalLeases(Collection $units, Collection $tenants): void
    {
        for ($index = 1; $index <= self::HISTORICAL_LEASE_COUNT; $index++) {
            $unit = $units->values()->get(self::TENANT_COUNT + $index - 1);
            $tenant = $tenants->values()->get($index - 1);
            $startDate = now()->subMonths(9 + ($index % 3))->startOfMonth();
            $endDate = now()->subMonths(4 + ($index % 3))->endOfMonth();
            $reference = sprintf('%s-history-%03d', self::NAMESPACE, $index);
            $lease = Lease::withTrashed()->firstOrNew(['reference' => $reference]);

            if ($lease->trashed()) {
                $lease->restoreQuietly();
            }

            $rate = $unit->rates()
                ->where('billing_interval', 1)
                ->where('billing_unit', 'month')
                ->firstOrFail();

            $lease->forceFill([
                'reference' => $reference,
                'primary_tenant_id' => $tenant->id,
                'unit_id' => $unit->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'rent_amount' => $rate->amount,
                'currency' => 'IDR',
                'billing_interval' => 1,
                'billing_unit' => 'month',
                'billing_strategy' => 'advance',
                'is_custom_price' => false,
                'unit_rate_id' => $rate->id,
                'deposit_amount' => 1_500_000,
                'deposit_paid_at' => $startDate,
                'deposit_refund_amount' => 1_500_000,
                'deposit_refunded_at' => $endDate,
                'rent_due_day' => (($index - 1) % 10) + 1,
                'status' => LeaseStatus::Terminated,
                'termination_date' => $endDate->toDateString(),
                'termination_reason' => 'contract_ended',
                'notes' => 'Reserved OPE-177 historical load-test lease.',
            ])->saveQuietly();

            $lease->tenants()->sync([$tenant->id => ['is_primary' => true]]);
        }
    }

    /**
     * @param  Collection<int, Lease>  $leases
     * @param  array{owner: User, admin: User, staff: User, tenant: User}  $users
     */
    private function seedInvoicesAndPayments(Collection $leases, array $users): void
    {
        $now = now();

        foreach ($leases->values() as $leaseIndex => $lease) {
            for ($periodIndex = 0; $periodIndex < self::INVOICE_PERIOD_COUNT; $periodIndex++) {
                $periodStart = $now->copy()->startOfMonth()->addMonthsNoOverflow($periodIndex - 1);
                $invoice = $this->upsertInvoice($lease, $leaseIndex + 1, $periodIndex, $periodStart);
                $state = $this->invoiceState($leaseIndex + 1, $periodIndex);

                $this->upsertInvoiceLineItem($invoice);
                $this->upsertInvoicePayment($invoice, $leaseIndex + 1, $state, $users);
                $this->normalizeInvoiceState($invoice, $state);
            }
        }
    }

    private function upsertInvoice(Lease $lease, int $leaseIndex, int $periodIndex, CarbonImmutable $periodStart): Invoice
    {
        $reference = sprintf('%s-invoice-%03d-%d', self::NAMESPACE, $leaseIndex, $periodIndex);
        $invoice = Invoice::query()->firstOrNew([
            'lease_id' => $lease->id,
            'period_start' => $periodStart->toDateString(),
        ]);
        $dueDate = $periodStart->copy()->setDay(min(5, $periodStart->daysInMonth));

        if ($periodIndex === 2) {
            $dueDate = $dueDate->addDays(5);
        }

        $invoice->forceFill([
            'lease_id' => $lease->id,
            'reference' => $reference,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'status' => InvoiceStatus::Pending,
            'total' => $lease->rent_amount,
            'amount_paid' => 0,
            'currency' => 'IDR',
        ])->saveQuietly();

        return $invoice->refresh();
    }

    private function invoiceState(int $leaseIndex, int $periodIndex): string
    {
        if ($periodIndex === 0) {
            return 'paid';
        }

        if ($periodIndex === 2) {
            return 'upcoming';
        }

        return match (($leaseIndex - 1) % 4) {
            0 => 'pending',
            1 => 'paid',
            2 => 'partial',
            default => 'pending-review',
        };
    }

    private function upsertInvoiceLineItem(Invoice $invoice): void
    {
        $lineItem = InvoiceLineItem::query()->firstOrNew([
            'invoice_id' => $invoice->id,
            'type' => 'rent',
        ]);
        $lineItem->forceFill([
            'invoice_id' => $invoice->id,
            'type' => 'rent',
            'description' => 'Rent '.$invoice->period_start->format('F Y'),
            'amount' => $invoice->total,
        ])->saveQuietly();
    }

    /**
     * @param  array{owner: User, admin: User, staff: User, tenant: User}  $users
     */
    private function upsertInvoicePayment(Invoice $invoice, int $leaseIndex, string $state, array $users): void
    {
        if (! in_array($state, ['paid', 'partial', 'pending-review'], true)) {
            return;
        }

        $reference = sprintf('%s-payment-%03d-%s', self::NAMESPACE, $leaseIndex, $invoice->period_start->format('Ym'));
        $payment = Payment::query()->firstOrNew([
            'invoice_id' => $invoice->id,
            'reference_number' => $reference,
        ]);
        $amount = $state === 'partial'
            ? intdiv((int) $invoice->total, 2)
            : (int) $invoice->total;
        $confirmed = $state !== 'pending-review';

        $payment->forceFill([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'currency' => 'IDR',
            'payment_date' => $invoice->due_date->toDateString(),
            'payment_method' => $confirmed ? 'cash' : 'bank_transfer',
            'reference_number' => $reference,
            'notes' => 'Reserved OPE-177 load-test payment.',
            'status' => $confirmed ? PaymentStatus::Confirmed : PaymentStatus::Pending,
            'confirmed_by' => $confirmed ? $users['owner']->id : null,
            'recorded_by' => $users['staff']->id,
            'verified_by' => null,
            'verified_at' => null,
        ])->saveQuietly();
    }

    private function normalizeInvoiceState(Invoice $invoice, string $state): void
    {
        $amountPaid = match ($state) {
            'paid' => $invoice->total,
            'partial' => intdiv((int) $invoice->total, 2),
            default => 0,
        };
        $status = match ($state) {
            'paid' => InvoiceStatus::Paid,
            'partial' => InvoiceStatus::Partial,
            default => InvoiceStatus::Pending,
        };

        $invoice->forceFill([
            'amount_paid' => $amountPaid,
            'status' => $status,
        ])->saveQuietly();
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @param  Collection<int, Property>  $properties
     * @param  array{owner: User, admin: User, staff: User, tenant: User}  $users
     */
    private function seedMaintenanceTickets(Collection $units, Collection $properties, array $users): void
    {
        for ($index = 1; $index <= self::MAINTENANCE_TICKET_COUNT; $index++) {
            $unit = $units->values()->get(($index - 1) % $units->count());
            $property = $properties->firstWhere('id', $unit->property_id);
            $status = match (($index - 1) % 4) {
                0 => MaintenanceStatus::Reported,
                1 => MaintenanceStatus::InProgress,
                2 => MaintenanceStatus::Resolved,
                default => MaintenanceStatus::Cancelled,
            };
            $reference = sprintf('%s-ticket-%03d', self::NAMESPACE, $index);
            $ticket = MaintenanceTicket::query()->firstOrNew(['reference' => $reference]);

            $ticket->forceFill([
                'property_id' => $property->id,
                'unit_id' => $index % 5 === 0 ? null : $unit->id,
                'location' => $index % 5 === 0 ? 'Shared laundry area' : null,
                'reference' => $reference,
                'title' => sprintf('OPE-177 maintenance issue %03d', $index),
                'description' => 'Reserved OPE-177 maintenance fixture.',
                'status' => $status,
                'priority' => match (($index - 1) % 4) {
                    0 => MaintenancePriority::Medium,
                    1 => MaintenancePriority::High,
                    2 => MaintenancePriority::Low,
                    default => MaintenancePriority::Urgent,
                },
                'assigned_to' => $status === MaintenanceStatus::Reported ? null : $users['staff']->id,
                'created_by' => $index === 1 ? $users['tenant']->id : $users['owner']->id,
                'cost' => $status === MaintenanceStatus::Resolved ? 250_000 : null,
                'resolved_at' => $status === MaintenanceStatus::Resolved ? now()->subDay() : null,
                'resolution_notes' => $status === MaintenanceStatus::Resolved ? 'Resolved for load-test coverage.' : null,
            ])->saveQuietly();
        }
    }

    private function envPersona(string $persona): string
    {
        return match ($persona) {
            'admin' => 'ADMIN',
            default => strtoupper($persona),
        };
    }
}
