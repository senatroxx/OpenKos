<?php

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationTargetType;
use App\Enums\PropertyRentalMode;
use App\Models\Application;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\Tenant;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Database\QueryException;

function publicWholeProperty(): Property
{
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::WholeProperty,
        'is_published' => true,
        'public_slug' => 'public-property',
    ]);
    PropertyRate::factory()->for($property)->create();

    return $property;
}

test('a verified user can submit one application for a published whole property', function () {
    $user = User::factory()->create(['phone' => '+628123456789']);
    $property = publicWholeProperty();
    $rate = PropertyRate::query()->where('property_id', $property->id)->firstOrFail();

    $this->actingAs($user)->post(route('applications.store'), [
        'target_type' => 'whole_property',
        'property_slug' => $property->public_slug,
        'rental_billing_unit' => $rate->billing_unit->value,
        'rental_billing_interval' => $rate->billing_interval,
        'rental_currency' => $rate->currency,
        'applicant_message' => 'I would like to learn more.',
    ])->assertRedirect(route('applications.show', Application::first()));

    expect(Application::query()->count())->toBe(1)
        ->and(Application::first()->target_type)->toBe(ApplicationTargetType::WholeProperty)
        ->and(Application::first()->user_id)->toBe($user->id);
});

test('application target type accepts only the backed offering values', function () {
    $user = User::factory()->create(['phone' => '+628123456789']);

    $this->actingAs($user)->post(route('applications.store'), [
        'target_type' => 'unknown',
        'property_slug' => 'ignored',
    ])->assertSessionHasErrors('target_type');

    expect(ApplicationTargetType::cases())->toHaveCount(2)
        ->and(ApplicationTargetType::WholeProperty->value)->toBe('whole_property')
        ->and(ApplicationTargetType::UnitType->value)->toBe('unit_type');
});

test('the database enforces application target column invariants', function () {
    $application = Application::factory()->create();
    $unitType = UnitType::factory()->for($application->property)->create();

    expect(fn () => $application->update(['unit_type_id' => $unitType->id]))
        ->toThrow(QueryException::class);
});

test('a prospective renter can complete their profile without becoming a tenant', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('portal.profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('portalProfile', true));

    $this->actingAs($user)
        ->patch(route('portal.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+628123456789',
        ])
        ->assertRedirect();

    expect($user->refresh()->phone)->toBe('+628123456789')
        ->and(Tenant::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('incomplete renter profiles cannot submit applications', function () {
    $user = User::factory()->create();
    $property = publicWholeProperty();
    $rate = PropertyRate::query()->where('property_id', $property->id)->firstOrFail();

    $this->actingAs($user)
        ->post(route('applications.store'), [
            'target_type' => 'whole_property',
            'property_slug' => $property->public_slug,
            'rental_billing_unit' => $rate->billing_unit->value,
            'rental_billing_interval' => $rate->billing_interval,
            'rental_currency' => $rate->currency,
        ])
        ->assertSessionHasErrors('profile');

    expect(Application::query()->count())->toBe(0);
});

test('duplicate open applications are rejected but terminal applications allow reapplication', function () {
    $user = User::factory()->create(['phone' => '+628123456789']);
    $property = publicWholeProperty();
    $rate = PropertyRate::query()->where('property_id', $property->id)->firstOrFail();
    $payload = [
        'target_type' => 'whole_property',
        'property_slug' => $property->public_slug,
        'rental_billing_unit' => $rate->billing_unit->value,
        'rental_billing_interval' => $rate->billing_interval,
        'rental_currency' => $rate->currency,
    ];

    $this->actingAs($user)->post(route('applications.store'), $payload)->assertRedirect();
    $this->actingAs($user)->post(route('applications.store'), $payload)->assertSessionHasErrors('application');

    Application::first()->update(['status' => ApplicationStatus::Withdrawn, 'open_application_key' => null]);
    $this->actingAs($user)->post(route('applications.store'), $payload)->assertRedirect();

    expect(Application::query()->count())->toBe(2);
});

test('applicants can withdraw but cannot see operator notes', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'operator_notes' => 'Private note',
        'applicant_feedback' => 'We will contact you.',
        'status' => ApplicationStatus::New,
    ]);

    $this->actingAs($user)->get(route('applications.show', $application))
        ->assertInertia(fn ($page) => $page
            ->where('application.applicant_feedback', 'We will contact you.')
            ->missing('application.operator_notes'));

    $this->actingAs($user)->patch(route('applications.transition', $application), ['status' => 'withdrawn'])
        ->assertRedirect();

    expect($application->refresh()->status)->toBe(ApplicationStatus::Withdrawn);
});

test('accepted application conversion creates one tenant without a lease', function () {
    $operator = User::factory()->owner()->create();
    $applicant = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $applicant->id,
        'status' => ApplicationStatus::Accepted,
    ]);

    $this->actingAs($operator)->post(route('applications.convert', $application))->assertRedirect();
    $this->actingAs($operator)->post(route('applications.convert', $application))->assertRedirect();

    expect(Tenant::query()->where('user_id', $applicant->id)->count())->toBe(1)
        ->and($application->refresh()->converted_tenant_id)->not->toBeNull();
});

test('application transitions follow the centralized lifecycle', function () {
    $operator = User::factory()->owner()->create();
    $application = Application::factory()->create(['status' => ApplicationStatus::New]);

    $this->actingAs($operator)->patch(route('applications.transition', $application), ['status' => 'reviewing'])
        ->assertRedirect();
    $this->actingAs($operator)->patch(route('applications.transition', $application), ['status' => 'accepted'])
        ->assertRedirect();
    $this->actingAs($operator)->patch(route('applications.transition', $application), ['status' => 'reviewing'])
        ->assertSessionHasErrors('application');
});
