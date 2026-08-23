<?php

use App\Models\Lease;
use App\Models\Media;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\TenantDocument;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses()->beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
});

function tenantDocumentOwner(): array
{
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $owner->properties()->sync([$property->id]);
    $unit = Unit::factory()->for($property)->create();
    $tenant = Tenant::factory()->create();
    Lease::factory()->create([
        'unit_id' => $unit->id,
        'primary_tenant_id' => $tenant->id,
    ]);

    return [$owner, $tenant];
}

it('stores new tenant documents through canonical media', function (): void {
    [$owner, $tenant] = tenantDocumentOwner();

    $this->actingAs($owner)
        ->post(route('tenants.documents.store', $tenant), [
            'type' => 'ktp',
            'file' => UploadedFile::fake()->create('identity.pdf', 2, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = $tenant->documents()->sole();

    expect($document->media_id)->not->toBeNull()
        ->and($document->media->mediable->is($tenant))->toBeTrue()
        ->and($document->media->collection)->toBe('documents')
        ->and($document->file_path)->toBe($document->media->path);

    Storage::disk('local')->assertExists($document->media->path);
});

it('never falls back to the legacy path after a canonical link exists', function (): void {
    [$owner, $tenant] = tenantDocumentOwner();
    $legacyPath = 'tenant-documents/legacy.pdf';
    Storage::disk('local')->put($legacyPath, 'legacy file');
    $media = Media::create([
        'mediable_type' => $tenant->getMorphClass(),
        'mediable_id' => $tenant->id,
        'collection' => 'documents',
        'disk' => 'local',
        'path' => 'media/missing.pdf',
        'mime_type' => 'application/pdf',
        'size' => 10,
        'original_name' => 'canonical.pdf',
        'position' => 0,
    ]);
    $document = TenantDocument::create([
        'tenant_id' => $tenant->id,
        'media_id' => $media->id,
        'type' => 'other',
        'original_name' => 'legacy.pdf',
        'file_path' => $legacyPath,
        'mime_type' => 'application/pdf',
        'size' => 10,
    ]);

    $this->actingAs($owner)
        ->get(route('tenants.documents.show', [$tenant, $document]))
        ->assertNotFound();
});
