<?php

use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    config(['filesystems.default' => 'local']);
    config(['inertia.ssr.enabled' => false]);
    Storage::fake('local');
});

it('allows owners to configure public portal metadata without changing the admin name', function () {
    $owner = User::factory()->owner()->create();
    Setting::set('site_name', 'Admin Name');

    $this->actingAs($owner)
        ->patch(route('settings.public-portal.update'), [
            'public_site_name' => 'Public Name',
            'public_homepage_title' => 'Welcome to Public Name',
            'public_homepage_description' => 'A public description.',
        ])
        ->assertRedirect();

    expect(Setting::get('public_site_name'))->toBe('Public Name')
        ->and(Setting::get('site_name'))->toBe('Admin Name');

    $this->get(route('public.portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metadata.siteName', 'Public Name')
            ->where('metadata.title', 'Welcome to Public Name')
            ->where('metadata.description', 'A public description.')
            ->where('metadata.openGraph.siteName', 'Public Name')
            ->where('metadata.openGraph.title', 'Welcome to Public Name')
            ->where('metadata.twitter.description', 'A public description.')
            ->missing('setting.public_og_image_path'));
});

it('falls back through public name and homepage defaults when values are blank', function () {
    Setting::set('site_name', 'Installation Name');
    Setting::set('public_site_name', '   ');
    Setting::set('public_homepage_title', '');
    Setting::set('public_homepage_description', '   ');

    $this->get(route('public.portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metadata.siteName', 'Installation Name')
            ->where('metadata.title', 'Find your next place')
            ->where('metadata.description', 'Discover available properties and rental options that fit your needs.')
            ->where('metadata.canonical', '/'));
});

it('falls back to OpenKOS when the stored site name is blank', function () {
    Setting::set('site_name', '  ');
    Setting::set('public_site_name', null);

    $this->get(route('public.portal.index'))
        ->assertInertia(fn (Assert $page) => $page->where('metadata.siteName', 'OpenKOS'));
});

it('keeps entity metadata separate from homepage metadata', function () {
    Setting::set('site_name', 'Installation Name');
    Setting::set('public_site_name', 'Public Name');
    Setting::set('public_homepage_title', 'Homepage Title');

    $property = Property::factory()->create([
        'name' => 'Sunrise House',
        'public_slug' => 'sunrise-house',
        'is_published' => true,
        'description' => null,
    ]);
    $unitType = UnitType::factory()->for($property)->create([
        'is_published' => true,
        'public_slug' => 'studio',
    ]);
    Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

    $this->get(route('public.portal.show', ['property' => $property->public_slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metadata.title', 'Sunrise House | Public Name')
            ->where('metadata.title', fn (string $title): bool => $title !== 'Homepage Title')
            ->where('metadata.description', 'Discover this property and its rental options.'));
});

it('clears the social image without exposing its storage path', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.public-portal.social-image.update'), [
            'file' => UploadedFile::fake()->create('social.png', 100, 'image/png'),
        ])
        ->assertRedirect();

    $path = Setting::get('public_og_image_path');
    $imageUrl = route('branding.asset', ['asset' => 'og-image', 'v' => sha1($path)]);

    expect($path)->toStartWith('branding/');
    Storage::disk('local')->assertExists($path);

    $this->get(route('branding.asset', ['asset' => 'og-image']))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/png');
    expect(route('branding.asset', ['asset' => 'og-image']))->not->toContain($path);

    $this->get(route('public.portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metadata.image', $imageUrl)
            ->missing('metadata.public_og_image_path'));

    $this->actingAs($owner)
        ->delete(route('settings.public-portal.social-image.destroy'))
        ->assertRedirect();

    Storage::disk('local')->assertMissing($path);
    expect(Setting::get('public_og_image_path'))->toBe('');

    $this->get(route('public.portal.index'))
        ->assertInertia(fn (Assert $page) => $page->where('metadata.image', null));
});

it('changes the social image URL when the configured file is replaced', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.public-portal.social-image.update'), [
            'file' => UploadedFile::fake()->create('first.png', 100, 'image/png'),
        ])
        ->assertRedirect();

    $firstPath = Setting::get('public_og_image_path');

    $this->actingAs($owner)
        ->post(route('settings.public-portal.social-image.update'), [
            'file' => UploadedFile::fake()->create('second.png', 100, 'image/png'),
        ])
        ->assertRedirect();

    $secondPath = Setting::get('public_og_image_path');

    expect($secondPath)->not->toBe($firstPath);

    $this->get(route('public.portal.index'))
        ->assertInertia(fn (Assert $page) => $page->where(
            'metadata.image',
            route('branding.asset', ['asset' => 'og-image', 'v' => sha1($secondPath)]),
        ));
});

it('validates social image uploads', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.public-portal.social-image.update'), [
            'file' => UploadedFile::fake()->create('social.pdf', 100, 'application/pdf'),
        ])
        ->assertInvalid('file');
});

it('forbids non-owners from public portal settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.public-portal.edit'))
        ->assertForbidden();
});
