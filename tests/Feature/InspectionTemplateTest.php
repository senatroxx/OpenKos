<?php

use App\Enums\InspectionType;
use App\Enums\Permission;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\InspectionTemplateItem;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('creates installation-wide inspection templates with checklist items', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('inspections.templates.store'), [
            'name' => 'Move-in standard',
            'inspection_type' => InspectionType::MoveIn->value,
            'items' => [
                ['label' => 'Walls', 'description' => 'Check paint and marks.'],
                ['label' => 'Windows', 'description' => null],
            ],
        ])
        ->assertRedirect();

    $template = InspectionTemplate::query()->firstOrFail();

    expect($template->name)->toBe('Move-in standard')
        ->and($template->inspection_type)->toBe(InspectionType::MoveIn)
        ->and($template->items)->toHaveCount(2)
        ->and($template->items[0]->label)->toBe('Walls');

    $this->actingAs($owner)
        ->get(route('inspections.templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('inspections/templates'));
});

it('restricts template management to the template capability', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('inspections.templates.index'))
        ->assertForbidden();
});

it('allows template access without dashboard access', function () {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::InspectionTemplatesManage->value);

    $this->actingAs($staff)
        ->get(route('inspections.templates.index'))
        ->assertSuccessful();
});

it('redirects the former settings template URL to inspections', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/inspection-templates')
        ->assertRedirect(route('inspections.templates.index'));
});

it('snapshots template lineage and checklist content when an inspection is created', function () {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $template = InspectionTemplate::factory()->create([
        'name' => 'Routine checklist',
        'inspection_type' => InspectionType::Periodic,
    ]);
    $item = $template->items()->create([
        'label' => 'Original label',
        'description' => 'Original guidance',
        'position' => 4,
    ]);

    $this->actingAs($owner)
        ->from(route('properties.workspace.inspections', $property))
        ->post(route('properties.inspections.store', $property), [
            'inspection_type' => InspectionType::Periodic->value,
            'inspection_template_id' => $template->id,
            'inspection_date' => '2026-09-14',
        ])
        ->assertRedirect(route('properties.workspace.inspections', $property));

    $inspection = Inspection::query()->firstOrFail();

    $this->actingAs($owner)
        ->from(route('inspections.templates.index'))
        ->patch(route('inspections.templates.update', $template), [
            'name' => 'Updated checklist',
            'inspection_type' => InspectionType::Periodic->value,
            'is_active' => true,
            'items' => [
                [
                    'id' => $item->id,
                    'label' => 'Updated label',
                    'description' => 'Updated guidance',
                ],
            ],
        ])
        ->assertRedirect(route('inspections.templates.index'));

    $snapshot = $inspection->fresh()->items->firstOrFail();

    expect($inspection->template_name)->toBe('Routine checklist')
        ->and($snapshot->label)->toBe('Original label')
        ->and($snapshot->description)->toBe('Original guidance')
        ->and($snapshot->position)->toBe(4);
});

it('keeps historical inspections when a template is deactivated', function () {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $template = InspectionTemplate::factory()->create([
        'inspection_type' => InspectionType::Periodic,
    ]);
    InspectionTemplateItem::factory()->for($template, 'template')->create();

    $this->actingAs($owner)
        ->post(route('properties.inspections.store', $property), [
            'inspection_type' => InspectionType::Periodic->value,
            'inspection_template_id' => $template->id,
            'inspection_date' => '2026-09-14',
        ]);

    $this->actingAs($owner)
        ->patch(route('inspections.templates.update', $template), [
            'name' => $template->name,
            'inspection_type' => InspectionType::Periodic->value,
            'is_active' => false,
            'items' => $template->items->map(fn (InspectionTemplateItem $item): array => [
                'id' => $item->id,
                'label' => $item->label,
                'description' => $item->description,
            ])->all(),
        ])
        ->assertRedirect();

    expect($template->fresh()->is_active)->toBeFalse()
        ->and(Inspection::query()->exists())->toBeTrue();
});
