<?php

namespace Database\Seeders;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\UnitStatus;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaintenanceSeeder extends Seeder
{
    private const PROPERTY_SLUG = 'ope-184-demo-kos-anggrek-residence';

    public function run(): void
    {
        $now = now();
        DB::transaction(function () use ($now): void {
            $property = Property::query()->where('slug', self::PROPERTY_SLUG)->firstOrFail();
            $staff = User::query()->where('email', 'demo.staff@openkos.com')->firstOrFail();
            $tenant = User::query()->where('email', 'demo.tenant@openkos.com')->firstOrFail();
            $owner = User::query()->where('email', 'budi@openkos.com')->firstOrFail();
            $maintenanceUnit = $this->unit($property, 'C1');
            $maintenanceUnit->forceFill(['status' => UnitStatus::Maintenance])->saveQuietly();

            $this->upsertTicket([
                'reference' => 'ope-184-demo-ticket-001',
                'property_id' => $property->id,
                'unit_id' => null,
                'location' => 'Lobby',
                'title' => 'Lobby light needs replacement',
                'description' => 'The main lobby light flickers after rain.',
                'status' => MaintenanceStatus::Reported,
                'priority' => MaintenancePriority::Medium,
                'assigned_to' => null,
                'created_by' => $tenant->id,
                'cost' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
            ], $now);

            $this->upsertTicket([
                'reference' => 'ope-184-demo-ticket-002',
                'property_id' => $property->id,
                'unit_id' => $maintenanceUnit->id,
                'location' => 'Unit C1',
                'title' => 'Bathroom tap is leaking',
                'description' => 'The bathroom tap has a steady leak and needs a washer replacement.',
                'status' => MaintenanceStatus::InProgress,
                'priority' => MaintenancePriority::High,
                'assigned_to' => $staff->id,
                'created_by' => $tenant->id,
                'cost' => null,
                'resolved_at' => null,
                'resolution_notes' => null,
            ], $now);

            $this->upsertTicket([
                'reference' => 'ope-184-demo-ticket-003',
                'property_id' => $property->id,
                'unit_id' => $this->unit($property, 'C2')->id,
                'location' => 'Unit C2',
                'title' => 'Air conditioner filter replaced',
                'description' => 'Routine filter replacement completed before the next move-in.',
                'status' => MaintenanceStatus::Resolved,
                'priority' => MaintenancePriority::Low,
                'assigned_to' => $staff->id,
                'created_by' => $owner->id,
                'cost' => '250000',
                'resolved_at' => $now->copy()->subDays(2),
                'resolution_notes' => 'Filter cleaned and replaced.',
            ], $now);
        });
    }

    /**
     * @param  array{reference: string, property_id: int, unit_id: int|null, location: string, title: string, description: string, status: MaintenanceStatus, priority: MaintenancePriority, assigned_to: int|null, created_by: int, cost: string|null, resolved_at: CarbonInterface|null, resolution_notes: string|null}  $data
     */
    private function upsertTicket(array $data, CarbonInterface $now): void
    {
        $ticket = MaintenanceTicket::query()->firstOrNew(['reference' => $data['reference']]);
        $ticket->forceFill([
            ...$data,
            'created_at' => $now,
            'updated_at' => $now,
        ])->saveQuietly();
    }

    private function unit(Property $property, string $name): Unit
    {
        return $property->units()->where('name', $name)->firstOrFail();
    }
}
