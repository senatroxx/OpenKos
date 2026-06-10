<?php

namespace Database\Seeders;

use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaseSeeder extends Seeder
{
    private array $activeAssignments = [
        ['property' => 'Kos Melati Indah', 'room' => 'A1', 'tenant' => 'Budi Santoso'],
        ['property' => 'Kos Melati Indah', 'room' => 'A2', 'tenant' => 'Siti Nurhaliza'],
        ['property' => 'Kos Melati Indah', 'room' => 'A3', 'tenant' => 'Ahmad Rizki'],
        ['property' => 'Kos Mawar Putih',  'room' => 'A1', 'tenant' => 'Dewi Lestari'],
        ['property' => 'Kos Mawar Putih',  'room' => 'B1', 'tenant' => 'Rudi Hartono'],
        ['property' => 'Kos Kenanga Asri', 'room' => 'A1', 'tenant' => 'Rina Wijaya'],
        ['property' => 'Kos Kenanga Asri', 'room' => 'B2', 'tenant' => 'Agus Prasetyo'],
        ['property' => 'Kos Dahlia Permai', 'room' => 'A4', 'tenant' => 'Maya Anggraini'],
    ];

    private array $historicalAssignments = [
        ['property' => 'Kos Melati Indah', 'room' => 'A3', 'tenant' => 'Fitri Handayani', 'months_ago' => 7],
        ['property' => 'Kos Mawar Putih',  'room' => 'B3', 'tenant' => 'Hendra Gunawan', 'months_ago' => 5],
        ['property' => 'Kos Kenanga Asri', 'room' => 'C3', 'tenant' => 'Joko Susilo',    'months_ago' => 4],
        ['property' => 'Kos Dahlia Permai', 'room' => 'C4', 'tenant' => 'Kartika Sari',   'months_ago' => 3],
        ['property' => 'Kos Melati Indah', 'room' => 'A2', 'tenant' => 'Doni Firmansyah', 'months_ago' => 9],
        ['property' => 'Kos Mawar Putih',  'room' => 'B4', 'tenant' => 'Indah Permata',   'months_ago' => 6],
        ['property' => 'Kos Kenanga Asri', 'room' => 'A2', 'tenant' => 'Lukman Hakim',    'months_ago' => 8],
    ];

    public function run(): void
    {
        /** @var Collection<int, Property> */
        $properties = Property::with('rooms.activeRates')->get()->keyBy('name');
        $tenants = Tenant::pluck('id', 'name');

        $now = Carbon::now();

        foreach ($this->activeAssignments as $assignment) {
            $property = $properties->get($assignment['property']);
            $tenantId = $tenants->get($assignment['tenant']);

            if (! $property || ! $tenantId) {
                continue;
            }

            $room = $property->rooms->firstWhere('name', $assignment['room']);

            if (! $room) {
                continue;
            }

            $rate = $room->activeRates->first();
            $startDate = $now->copy()->subMonths(fake()->numberBetween(1, 6));

            $lease = Lease::create([
                'primary_tenant_id' => $tenantId,
                'room_id' => $room->id,
                'start_date' => $startDate,
                'end_date' => null,
                'rent_amount' => $rate?->amount ?? 1_500_000,
                'billing_interval' => $rate?->billing_interval ?? 1,
                'billing_unit' => $rate?->billing_unit ?? 'month',
                'is_custom_price' => DB::raw('false'),
                'room_rate_id' => $rate?->id ?? null,
                'deposit_amount' => fake()->randomElement([500_000, 1_000_000, 1_500_000]),
                'deposit_paid_at' => $startDate,
                'rent_due_day' => fake()->randomElement([1, 5, 10, 15, 20, 25]),
                'status' => 'active',
                'notes' => null,
            ]);

            $lease->tenants()->attach($tenantId, ['is_primary' => DB::raw('true')]);
        }

        foreach ($this->historicalAssignments as $assignment) {
            $property = $properties->get($assignment['property']);
            $tenantId = $tenants->get($assignment['tenant']);

            if (! $property || ! $tenantId) {
                continue;
            }

            $room = $property->rooms->firstWhere('name', $assignment['room']);

            if (! $room) {
                continue;
            }

            $rate = $room->activeRates->first();
            $monthsAgo = $assignment['months_ago'];
            $startDate = $now->copy()->subMonths($monthsAgo + random_int(1, 4));
            $endDate = $now->copy()->subMonths($monthsAgo);

            $lease = Lease::create([
                'primary_tenant_id' => $tenantId,
                'room_id' => $room->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rent_amount' => $rate?->amount ?? 1_500_000,
                'billing_interval' => $rate?->billing_interval ?? 1,
                'billing_unit' => $rate?->billing_unit ?? 'month',
                'is_custom_price' => DB::raw('false'),
                'room_rate_id' => $rate?->id ?? null,
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

            $lease->tenants()->attach($tenantId, ['is_primary' => DB::raw('true')]);
        }

        Room::query()
            ->whereIn('id', Lease::where('status', 'active')->pluck('room_id'))
            ->update(['status' => RoomStatus::Occupied->value]);
    }
}
