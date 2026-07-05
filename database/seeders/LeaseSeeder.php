<?php

namespace Database\Seeders;

use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class LeaseSeeder extends Seeder
{
    private array $activeAssignments = [
        ['property' => 'Kos Melati Indah', 'unit_name' => 'A1', 'tenant' => 'Budi Santoso'],
        ['property' => 'Kos Melati Indah', 'unit_name' => 'A2', 'tenant' => 'Siti Nurhaliza'],
        ['property' => 'Kos Melati Indah', 'unit_name' => 'A3', 'tenant' => 'Ahmad Rizki'],
        ['property' => 'Kos Mawar Putih',  'unit_name' => 'A1', 'tenant' => 'Dewi Lestari'],
        ['property' => 'Kos Mawar Putih',  'unit_name' => 'B1', 'tenant' => 'Rudi Hartono'],
        ['property' => 'Kos Kenanga Asri', 'unit_name' => 'A1', 'tenant' => 'Rina Wijaya'],
        ['property' => 'Kos Kenanga Asri', 'unit_name' => 'B2', 'tenant' => 'Agus Prasetyo'],
        ['property' => 'Kos Dahlia Permai', 'unit_name' => 'A4', 'tenant' => 'Maya Anggraini'],
    ];

    private array $sharedAssignments = [
        ['property' => 'Kos Melati Indah', 'unit_name' => 'B1', 'primary_tenant' => 'Eko Wahyudi', 'extra_tenants' => ['Dian Permata']],
        ['property' => 'Kos Melati Indah', 'unit_name' => 'B2', 'primary_tenant' => 'Fajar Nugroho', 'extra_tenants' => ['Ratna Sari', 'Bayu Aji']],
    ];

    private array $historicalAssignments = [
        ['property' => 'Kos Melati Indah', 'unit_name' => 'A3', 'tenant' => 'Fitri Handayani', 'months_ago' => 7],
        ['property' => 'Kos Mawar Putih',  'unit_name' => 'B3', 'tenant' => 'Hendra Gunawan', 'months_ago' => 5],
        ['property' => 'Kos Kenanga Asri', 'unit_name' => 'C3', 'tenant' => 'Joko Susilo',    'months_ago' => 4],
        ['property' => 'Kos Dahlia Permai', 'unit_name' => 'C4', 'tenant' => 'Kartika Sari',   'months_ago' => 3],
        ['property' => 'Kos Melati Indah', 'unit_name' => 'A2', 'tenant' => 'Doni Firmansyah', 'months_ago' => 9],
        ['property' => 'Kos Mawar Putih',  'unit_name' => 'B4', 'tenant' => 'Indah Permata',   'months_ago' => 6],
        ['property' => 'Kos Kenanga Asri', 'unit_name' => 'A2', 'tenant' => 'Lukman Hakim',    'months_ago' => 8],
    ];

    public function run(): void
    {
        /** @var Collection<int, Property> */
        $properties = Property::with('units.activeRates')->get()->keyBy('name');
        $tenants = Tenant::pluck('id', 'name');

        $now = Carbon::now();

        foreach ($this->activeAssignments as $assignment) {
            $property = $properties->get($assignment['property']);
            $tenantId = $tenants->get($assignment['tenant']);

            if (! $property || ! $tenantId) {
                continue;
            }

            $unit = $property->units->firstWhere('name');

            if (! $unit) {
                continue;
            }

            $rate = $unit->activeRates->first();
            $startDate = $now->copy()->subMonths(fake()->numberBetween(1, 6));

            $lease = Lease::create([
                'primary_tenant_id' => $tenantId,
                'unit_id' => $unit->id,
                'start_date' => $startDate,
                'end_date' => null,
                'rent_amount' => $rate?->amount ?? 1_500_000,
                'billing_interval' => $rate?->billing_interval ?? 1,
                'billing_unit' => $rate?->billing_unit ?? 'month',
                'is_custom_price' => false,
                'unit_rate_id' => $rate?->id ?? null,
                'deposit_amount' => fake()->randomElement([500_000, 1_000_000, 1_500_000]),
                'deposit_paid_at' => $startDate,
                'rent_due_day' => fake()->randomElement([1, 5, 10, 15, 20, 25]),
                'status' => 'active',
                'notes' => null,
            ]);

            $lease->tenants()->attach($tenantId, ['is_primary' => true]);
        }

        foreach ($this->sharedAssignments as $assignment) {
            $property = $properties->get($assignment['property']);
            $tenantIds = collect($assignment['extra_tenants'])
                ->push($assignment['primary_tenant'])
                ->map(fn (string $name) => $tenants->get($name));

            if (! $property || $tenantIds->contains(null)) {
                continue;
            }

            $unit = $property->units->firstWhere('name');

            if (! $unit) {
                continue;
            }

            $rate = $unit->activeRates->first();
            $startDate = $now->copy()->subMonths(fake()->numberBetween(1, 6));
            $primaryId = $tenantIds->pop();

            $lease = Lease::create([
                'primary_tenant_id' => $primaryId,
                'unit_id' => $unit->id,
                'start_date' => $startDate,
                'end_date' => null,
                'rent_amount' => $rate?->amount ?? 1_500_000,
                'billing_interval' => $rate?->billing_interval ?? 1,
                'billing_unit' => $rate?->billing_unit ?? 'month',
                'is_custom_price' => false,
                'unit_rate_id' => $rate?->id ?? null,
                'deposit_amount' => fake()->randomElement([500_000, 1_000_000, 1_500_000]),
                'deposit_paid_at' => $startDate,
                'rent_due_day' => fake()->randomElement([1, 5, 10, 15, 20, 25]),
                'status' => 'active',
                'notes' => null,
            ]);

            $lease->tenants()->attach($primaryId, ['is_primary' => true]);

            foreach ($tenantIds as $extraId) {
                $lease->tenants()->attach($extraId, ['is_primary' => false]);
            }
        }

        foreach ($this->historicalAssignments as $assignment) {
            $property = $properties->get($assignment['property']);
            $tenantId = $tenants->get($assignment['tenant']);

            if (! $property || ! $tenantId) {
                continue;
            }

            $unit = $property->units->firstWhere('name');

            if (! $unit) {
                continue;
            }

            $rate = $unit->activeRates->first();
            $monthsAgo = $assignment['months_ago'];
            $startDate = $now->copy()->subMonths($monthsAgo + random_int(1, 4));
            $endDate = $now->copy()->subMonths($monthsAgo);

            $lease = Lease::create([
                'primary_tenant_id' => $tenantId,
                'unit_id' => $unit->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rent_amount' => $rate?->amount ?? 1_500_000,
                'billing_interval' => $rate?->billing_interval ?? 1,
                'billing_unit' => $rate?->billing_unit ?? 'month',
                'is_custom_price' => false,
                'unit_rate_id' => $rate?->id ?? null,
                'deposit_amount' => fake()->randomElement([500_000, 1_000_000]),
                'deposit_paid_at' => $startDate,
                'deposit_refund_amount' => fake()->randomElement([null, 500_000, 1_000_000]),
                'deposit_refunded_at' => fake()->randomElement([null, $endDate]),
                'rent_due_day' => fake()->randomElement([1, 5, 10]),
                'status' => 'terminated',
                'termination_date' => $endDate,
                'termination_reason' => fake()->randomElement([
                    'move_out', 'contract_ended', 'mutual_agreement',
                ]),
                'notes' => null,
            ]);

            $lease->tenants()->attach($tenantId, ['is_primary' => true]);
        }

        // Ensure dashboard rent status coverage, then create payments for the rest
        $activeLeases = Lease::where('status', 'active')->get();
        $today = (int) $now->day;

        if ($activeLeases->isNotEmpty()) {
            $activeLeases->first()->update(['rent_due_day' => $today]); // Due Today
        }

        if ($activeLeases->count() > 1) {
            $activeLeases->get(1)->update(['rent_due_day' => min($today + 3, 28)]); // Due Soon
        }

        // Create payments for ~half the active leases (makes them "Paid"), skip the first 2
        foreach ($activeLeases as $i => $lease) {
            if ($i > 1 && $i % 2 !== 0) {
                $lease->payments()->create([
                    'paymentable_type' => Lease::class,
                    'amount' => $lease->rent_amount,
                    'payment_date' => $now->copy()->setDay(min((int) $lease->rent_due_day, $now->daysInMonth)),
                    'period_start' => $now->copy()->startOfMonth(),
                    'period_end' => $now->copy()->endOfMonth(),
                    'payment_method' => 'cash',
                    'status' => 'confirmed',
                ]);
            }
        }

        Unit::query()
            ->whereIn('id', Lease::where('status', 'active')->pluck('unit_id'))
            ->update(['status' => UnitStatus::Occupied->value]);
    }
}
