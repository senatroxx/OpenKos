<?php

use App\Enums\InspectionItemCondition;
use App\Enums\InspectionType;
use App\Enums\Permission;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\InspectionTemplateItem;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('creates property, unit, and lease-scoped inspections through shared flows', function () {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();
    $lease = Lease::factory()->for($unit)->create();
    $periodic = InspectionTemplate::factory()->create(['inspection_type' => InspectionType::Periodic]);
    $moveIn = InspectionTemplate::factory()->create(['inspection_type' => InspectionType::MoveIn]);
    InspectionTemplateItem::factory()->for($periodic, 'template')->create(['label' => 'Property item']);
    InspectionTemplateItem::factory()->for($periodic, 'template')->create(['label' => 'Unit item']);
    InspectionTemplateItem::factory()->for($moveIn, 'template')->create(['label' => 'Move-in item']);

    $this->actingAs($owner)
        ->post(route('properties.inspections.store', $property), [
            'inspection_type' => InspectionType::Periodic->value,
            'inspection_template_id' => $periodic->id,
            'inspection_date' => '2026-09-14',
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('properties.units.inspections.store', [$property, $unit]), [
            'inspection_type' => InspectionType::Periodic->value,
            'inspection_template_id' => $periodic->id,
            'inspection_date' => '2026-09-14',
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('leases.inspections.store', $lease), [
            'inspection_type' => InspectionType::MoveIn->value,
            'inspection_template_id' => $moveIn->id,
            'inspection_date' => '2026-09-14',
        ])
        ->assertRedirect();

    expect(Inspection::query()->whereNull('unit_id')->whereNull('lease_id')->exists())->toBeTrue()
        ->and(Inspection::query()->where('unit_id', $unit->id)->whereNull('lease_id')->exists())->toBeTrue()
        ->and(Inspection::query()->where('lease_id', $lease->id)->where('unit_id', $unit->id)->exists())->toBeTrue();
});

it('renders shared history and detail pages', function () {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $inspection = Inspection::factory()->create(['property_id' => $property->id]);
    $inspection->items()->create(['label' => 'Entry', 'position' => 0]);

    $this->actingAs($owner)
        ->get(route('properties.workspace.inspections', $property))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('properties/inspections')
            ->has('inspections.data', 1)
        );

    $this->actingAs($owner)
        ->get(route('inspections.show', $inspection))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inspections/show')
            ->where('inspection.id', $inspection->id)
            ->where('inspection.items.0.label', 'Entry')
        );
});

it('lists globally accessible inspections with operational filters', function () {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create(['name' => 'Sunrise Property']);
    $otherProperty = Property::factory()->create(['name' => 'Harbor Property']);
    $matching = Inspection::factory()->create([
        'property_id' => $property->id,
        'template_name' => 'Routine checklist',
        'inspection_type' => InspectionType::Periodic,
    ]);
    Inspection::factory()->completed()->create([
        'property_id' => $otherProperty->id,
        'inspection_type' => InspectionType::MoveIn,
    ]);

    $this->actingAs($owner)
        ->get(route('inspections.index', [
            'search' => 'Sunrise',
            'property_id' => $property->id,
            'inspection_type' => InspectionType::Periodic->value,
            'status' => 'draft',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inspections/index')
            ->has('inspections.data', 1)
            ->where('inspections.data.0.id', $matching->id)
            ->where('inspections.data.0.property.name', 'Sunrise Property')
            ->where('property_id', (string) $property->id)
        );
});

it('limits the global inspection workspace to assigned properties', function () {
    $staff = User::factory()->staff()->create();
    $staff->givePermissionTo(Permission::InspectionsView->value);

    $accessibleProperty = Property::factory()->create();
    $inaccessibleProperty = Property::factory()->create();
    $accessibleInspection = Inspection::factory()->create([
        'property_id' => $accessibleProperty->id,
    ]);
    Inspection::factory()->create([
        'property_id' => $inaccessibleProperty->id,
    ]);
    $staff->properties()->attach($accessibleProperty);

    $this->actingAs($staff)
        ->get(route('inspections.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('inspections.data', 1)
            ->where('inspections.data.0.id', $accessibleInspection->id)
        );
});

it('allows inspection access without dashboard access', function () {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::InspectionsView->value);
    $property = Property::factory()->create();
    $staff->properties()->attach($property);

    $this->actingAs($staff)
        ->get(route('inspections.index'))
        ->assertSuccessful();

    $this->actingAs($staff)
        ->get(route('properties.workspace.inspections', $property))
        ->assertSuccessful();
});

it('requires move-in and move-out inspections to be lease-scoped', function () {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $template = InspectionTemplate::factory()->create(['inspection_type' => InspectionType::MoveOut]);
    InspectionTemplateItem::factory()->for($template, 'template')->create();

    $this->actingAs($owner)
        ->post(route('properties.inspections.store', $property), [
            'inspection_type' => InspectionType::MoveOut->value,
            'inspection_template_id' => $template->id,
            'inspection_date' => '2026-09-14',
        ])
        ->assertStatus(422)
        ->assertSee('must be linked to a lease');
});

it('counts not applicable as assessed and locks completed inspections', function () {
    $owner = User::factory()->owner()->create();
    $inspection = Inspection::factory()->create();
    $firstItem = $inspection->items()->create([
        'label' => 'Door',
        'position' => 0,
    ]);
    $secondItem = $inspection->items()->create([
        'label' => 'Balcony',
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->put(route('inspections.update', $inspection), [
            'notes' => 'Ready for sign-off',
            'damage_observations' => null,
            'items' => [
                ['id' => $firstItem->id, 'condition' => InspectionItemCondition::Good->value, 'notes' => null],
                ['id' => $secondItem->id, 'condition' => InspectionItemCondition::NotApplicable->value, 'notes' => 'No balcony'],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('inspections.complete', $inspection))
        ->assertRedirect();

    expect($inspection->fresh()->isCompleted())->toBeTrue()
        ->and($secondItem->fresh()->condition)->toBe(InspectionItemCondition::NotApplicable);

    $this->actingAs($owner)
        ->put(route('inspections.update', $inspection), [
            'items' => [
                ['id' => $firstItem->id, 'condition' => InspectionItemCondition::Good->value],
                ['id' => $secondItem->id, 'condition' => InspectionItemCondition::NotApplicable->value],
            ],
        ])
        ->assertStatus(422);

    expect(fn () => $inspection->fresh()->update(['inspection_type' => InspectionType::MoveIn]))
        ->toThrow(LogicException::class);
    expect(fn () => $firstItem->fresh()->update(['notes' => 'Changed']))
        ->toThrow(LogicException::class);
});

it('keeps inspections readable when property, unit, or lease records are archived', function () {
    $owner = User::factory()->owner()->create();
    $propertyInspection = Inspection::factory()->create();
    $property = $propertyInspection->property;
    $property->delete();

    $unit = Unit::factory()->create();
    $unitInspection = Inspection::factory()->create([
        'property_id' => $unit->property_id,
        'unit_id' => $unit->id,
    ]);
    $unit->delete();

    $lease = Lease::factory()->create();
    $leaseInspection = Inspection::factory()->create([
        'property_id' => $lease->unit->property_id,
        'unit_id' => $lease->unit_id,
        'lease_id' => $lease->id,
    ]);
    $lease->delete();

    expect($propertyInspection->fresh()->property->is($property))->toBeTrue()
        ->and($unitInspection->fresh()->unit->is($unit))->toBeTrue()
        ->and($leaseInspection->fresh()->lease->is($lease))->toBeTrue();

    $this->actingAs($owner)
        ->get(route('inspections.show', $propertyInspection))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where(
            'inspection.property.deleted_at',
            fn ($deletedAt): bool => $deletedAt !== null,
        ));

    $this->actingAs($owner)
        ->get(route('inspections.show', $unitInspection))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where(
            'inspection.unit.deleted_at',
            fn ($deletedAt): bool => $deletedAt !== null,
        ));

    $this->actingAs($owner)
        ->get(route('inspections.show', $leaseInspection))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where(
            'inspection.lease.deleted_at',
            fn ($deletedAt): bool => $deletedAt !== null,
        ));
});
