<?php

use App\Enums\ApplicationStatus;
use App\Enums\PropertyRentalMode;
use App\Models\Application;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\Tenant;
use App\Models\User;

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
    $user = User::factory()->create();
    $property = publicWholeProperty();

    $this->actingAs($user)->post(route('applications.store'), [
        'target_type' => 'whole_property',
        'property_slug' => $property->public_slug,
        'applicant_message' => 'I would like to learn more.',
    ])->assertRedirect(route('applications.index'));

    expect(Application::query()->count())->toBe(1)
        ->and(Application::first()->target_type)->toBe('whole_property')
        ->and(Application::first()->user_id)->toBe($user->id);
});

test('duplicate open applications are rejected but terminal applications allow reapplication', function () {
    $user = User::factory()->create();
    $property = publicWholeProperty();
    $payload = ['target_type' => 'whole_property', 'property_slug' => $property->public_slug];

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
