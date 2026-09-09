<?php

use App\Models\Media;
use App\Models\Property;
use App\Models\UnitType;
use App\Models\User;
use App\Services\Media\MediaManager;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
});

it('manages property gallery metadata and explicit ordering', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();

    $this->actingAs($user)
        ->post(route('properties.gallery.store', $property), [
            'file' => UploadedFile::fake()->create('front.jpg', 1, 'image/jpeg'),
            'alt' => 'Front of house',
            'caption' => 'The property entrance',
        ])
        ->assertRedirect();

    $first = Media::query()->sole();

    $this->actingAs($user)
        ->post(route('properties.gallery.store', $property), [
            'file' => UploadedFile::fake()->create('garden.jpg', 1, 'image/jpeg'),
            'alt' => 'Garden',
        ])
        ->assertRedirect();

    $second = Media::query()->where('id', '!=', $first->id)->sole();

    expect($first->fresh()->metadata)->toBe([
        'alt' => 'Front of house',
        'caption' => 'The property entrance',
    ])
        ->and($first->fresh()->position)->toBe(0)
        ->and($second->fresh()->position)->toBe(1);

    $this->actingAs($user)
        ->patch(route('properties.gallery.update', [$property, $first]), [
            'alt' => 'Updated front view',
            'caption' => null,
        ])
        ->assertRedirect();

    expect($first->fresh()->metadata)->toBe([
        'alt' => 'Updated front view',
        'caption' => null,
    ]);

    $this->actingAs($user)
        ->post(route('properties.gallery.reorder', $property), [
            'media_ids' => [$second->id, $first->id],
        ])
        ->assertRedirect();

    expect($second->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1);

    $this->actingAs($user)
        ->post(route('properties.gallery.reorder', $property), [
            'media_ids' => [$second->id],
        ])
        ->assertSessionHasErrors('media_ids');
});

it('normalizes the next property cover after deleting the current cover', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $manager = app(MediaManager::class);
    $first = $manager->store($property, 'photos', UploadedFile::fake()->create('first.jpg', 1, 'image/jpeg'));
    $second = $manager->store($property, 'photos', UploadedFile::fake()->create('second.jpg', 1, 'image/jpeg'));

    $this->actingAs($user)
        ->delete(route('properties.gallery.destroy', [$property, $first]))
        ->assertRedirect();

    expect($second->fresh()->position)->toBe(0);
});

it('manages UnitType galleries without exposing them through Unit routes', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);

    $this->actingAs($user)
        ->post(route('properties.unit-types.gallery.store', [$property, $unitType]), [
            'file' => UploadedFile::fake()->create('studio.jpg', 1, 'image/jpeg'),
            'alt' => 'Studio interior',
        ])
        ->assertRedirect();

    $media = Media::query()->sole();

    $this->actingAs($user)
        ->get(route('properties.unit-types.gallery.show', [$property, $unitType, $media]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    expect($unitType->fresh()->media()->where('collection', 'photos')->count())->toBe(1);
});

it('rejects using another property gallery media', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $manager = app(MediaManager::class);
    $media = $manager->store($property, 'photos', UploadedFile::fake()->create('private.jpg', 1, 'image/jpeg'));

    $this->actingAs($user)
        ->delete(route('properties.gallery.destroy', [$otherProperty, $media]))
        ->assertNotFound();

    expect(Media::query()->whereKey($media->id)->exists())->toBeTrue();
});
