<?php

use App\Actions\Settings\UpdateBranding;
use App\Actions\Settings\UpdateSettings;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenKOS\Core\Events\SettingsUpdated as PlatformSettingsUpdated;

use function Pest\Laravel\mock;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    config(['filesystems.default' => 'local']);
    Storage::fake('local');
});

test('bundled branding is served and raw paths stay out of shared settings', function () {
    $this->get(route('branding.asset', ['asset' => 'logo']))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/svg+xml');

    $this->get(route('branding.asset', ['asset' => 'favicon']))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/x-icon');

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.hasCustomLogo', false)
            ->where('branding.hasCustomFavicon', false)
            ->missing('setting.branding_logo_path')
            ->missing('setting.branding_favicon_path'));
});

test('owners can upload and replace branding assets', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.general.branding.update', ['asset' => 'logo']), [
            'file' => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertRedirect();

    $firstPath = Setting::get('branding_logo_path');

    expect($firstPath)->toStartWith('branding/');
    Storage::disk('local')->assertExists($firstPath);

    $this->get(route('branding.asset', ['asset' => 'logo']))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/png');

    $this->actingAs($owner)
        ->post(route('settings.general.branding.update', ['asset' => 'logo']), [
            'file' => UploadedFile::fake()->image('replacement.png'),
        ])
        ->assertRedirect();

    $secondPath = Setting::get('branding_logo_path');

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($secondPath);
});

test('replacement keeps the new file when settings commit before a later failure', function () {
    $owner = User::factory()->owner()->create();
    $oldPath = 'branding/old-logo.png';

    Storage::disk('local')->put($oldPath, 'old');
    Setting::set('branding_logo_path', $oldPath);

    Event::listen(PlatformSettingsUpdated::class, function (): void {
        throw new RuntimeException('Listener failed after settings commit.');
    });

    expect(fn () => app(UpdateBranding::class)->execute(
        'logo',
        UploadedFile::fake()->image('replacement.png'),
        $owner,
    ))->toThrow(RuntimeException::class);

    $newPath = Setting::get('branding_logo_path');

    expect($newPath)->toBeString()
        ->and(Setting::get('branding_logo_path'))->toBe($newPath);
    Storage::disk('local')->assertExists($newPath);
    Storage::disk('local')->assertMissing($oldPath);
});

test('replacement removes the new file when settings fail before commit', function () {
    $owner = User::factory()->owner()->create();
    $oldPath = 'branding/old-logo.png';

    Storage::disk('local')->put($oldPath, 'old');
    Setting::set('branding_logo_path', $oldPath);

    $newPath = null;
    $updateSettings = mock(UpdateSettings::class);
    $updateSettings->shouldReceive('execute')->once()->andReturnUsing(function (array $data) use (&$newPath): void {
        $newPath = $data['branding_logo_path'];

        throw new RuntimeException('Settings write failed before commit.');
    });

    expect(fn () => (new UpdateBranding($updateSettings))->execute(
        'logo',
        UploadedFile::fake()->image('replacement.png'),
        $owner,
    ))->toThrow(RuntimeException::class);

    expect($newPath)->toBeString()
        ->and(Setting::get('branding_logo_path'))->toBe($oldPath);
    Storage::disk('local')->assertMissing($newPath);
    Storage::disk('local')->assertExists($oldPath);
});

test('removal deletes the old file when settings commit before a later failure', function () {
    $owner = User::factory()->owner()->create();
    $oldPath = 'branding/old-logo.png';

    Storage::disk('local')->put($oldPath, 'old');
    Setting::set('branding_logo_path', $oldPath);

    $updateSettings = mock(UpdateSettings::class);
    $updateSettings->shouldReceive('execute')->once()->andReturnUsing(function (): void {
        Setting::set('branding_logo_path', '');

        throw new RuntimeException('Listener failed after settings commit.');
    });

    expect(fn () => (new UpdateBranding($updateSettings))->remove('logo', $owner))
        ->toThrow(RuntimeException::class);

    expect(Setting::get('branding_logo_path'))->toBe('');
    Storage::disk('local')->assertMissing($oldPath);
});

test('owners can upload and remove a favicon', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.general.branding.update', ['asset' => 'favicon']), [
            'file' => UploadedFile::fake()->image('favicon.png', 32, 32),
        ])
        ->assertRedirect();

    $path = Setting::get('branding_favicon_path');

    Storage::disk('local')->assertExists($path);

    $this->actingAs($owner)
        ->delete(route('settings.general.branding.destroy', ['asset' => 'favicon']))
        ->assertRedirect();

    expect(Setting::get('branding_favicon_path'))->toBe('');
    Storage::disk('local')->assertMissing($path);

    $this->get(route('branding.asset', ['asset' => 'favicon']))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/x-icon');
});

test('missing stored files fall back to bundled branding', function () {
    $owner = User::factory()->owner()->create();
    Setting::set('branding_logo_path', 'branding/missing.png');

    $this->get(route('branding.asset', ['asset' => 'logo']))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/svg+xml');

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.hasCustomLogo', false)
            ->where('branding.hasConfiguredLogo', true));

    $this->actingAs($owner)
        ->delete(route('settings.general.branding.destroy', ['asset' => 'logo']))
        ->assertRedirect();

    expect(Setting::get('branding_logo_path'))->toBe('');
});

test('branding props fall back when the configured disk cannot be resolved', function () {
    Setting::set('branding_logo_path', 'branding/logo.png');
    config(['filesystems.default' => 'missing']);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.hasCustomLogo', false)
            ->where('branding.hasConfiguredLogo', true)
            ->where('branding.logoUrl', route('branding.asset', ['asset' => 'logo', 'v' => 'default'])));
});

test('stored files with unsupported MIME types are treated as bundled branding', function () {
    $path = 'branding/not-a-logo.txt';

    Storage::disk('local')->put($path, 'not an image');
    Setting::set('branding_logo_path', $path);

    $this->get(route('branding.asset', ['asset' => 'logo']))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/svg+xml');

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.hasCustomLogo', false)
            ->where('branding.hasConfiguredLogo', true));
});

test('branding uploads reject unsupported and oversized files', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.general.branding.update', ['asset' => 'logo']), [
            'file' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('file');

    $this->actingAs($owner)
        ->post(route('settings.general.branding.update', ['asset' => 'favicon']), [
            'file' => UploadedFile::fake()->create('favicon.png', 513, 'image/png'),
        ])
        ->assertSessionHasErrors('file');

    expect(Setting::get('branding_logo_path'))->toBe('')
        ->and(Setting::get('branding_favicon_path'))->toBe('');
});

test('non-owners cannot modify branding', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('settings.general.branding.update', ['asset' => 'logo']), [
            'file' => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('settings.general.branding.destroy', ['asset' => 'logo']))
        ->assertForbidden();
});
