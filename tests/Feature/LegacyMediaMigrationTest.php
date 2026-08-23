<?php

use App\Models\Media;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Tenant;
use App\Models\TenantDocument;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
});

it('backfills tenant documents and payment proofs without copying bytes', function (): void {
    $tenant = Tenant::factory()->create();
    $tenantPath = 'tenant-documents/'.$tenant->id.'/legacy.pdf';
    Storage::disk('local')->put($tenantPath, 'tenant document');
    $document = TenantDocument::create([
        'tenant_id' => $tenant->id,
        'type' => 'ktp',
        'original_name' => 'identity.pdf',
        'file_path' => $tenantPath,
        'mime_type' => 'application/pdf',
        'size' => 15,
    ]);

    $payment = Payment::factory()->create();
    $proofPath = 'payment-proofs/'.$payment->id.'/legacy.jpg';
    Storage::disk('local')->put($proofPath, 'payment proof');
    $proof = PaymentProof::create([
        'payment_id' => $payment->id,
        'path' => $proofPath,
        'original_name' => 'receipt.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    $this->artisan('media:migrate-legacy')
        ->assertExitCode(0);

    $document->refresh();
    $proof->refresh();

    expect($document->media_id)->not->toBeNull()
        ->and($document->media->mediable->is($tenant))->toBeTrue()
        ->and($document->media->collection)->toBe('documents')
        ->and($document->media->path)->toBe($tenantPath)
        ->and($proof->media_id)->not->toBeNull()
        ->and($proof->media->mediable->is($payment))->toBeTrue()
        ->and($proof->media->collection)->toBe('proofs')
        ->and($proof->media->path)->toBe($proofPath)
        ->and(Media::query()->count())->toBe(2);

    Storage::disk('local')->assertExists($tenantPath);
    Storage::disk('local')->assertExists($proofPath);

    $this->artisan('media:migrate-legacy')
        ->assertExitCode(0);

    expect(Media::query()->count())->toBe(2);
});

it('preserves a canonical row and reports a missing legacy file', function (): void {
    $tenant = Tenant::factory()->create();
    $document = TenantDocument::create([
        'tenant_id' => $tenant->id,
        'type' => 'passport',
        'original_name' => 'missing.pdf',
        'file_path' => 'tenant-documents/missing.pdf',
        'mime_type' => 'application/pdf',
        'size' => 42,
    ]);

    $this->artisan('media:migrate-legacy')
        ->expectsOutputToContain('missing=1')
        ->assertExitCode(0);

    $document->refresh();

    expect($document->media->metadata)->toMatchArray([
        'legacy' => [
            'table' => 'tenant_documents',
            'id' => $document->id,
            'missing_file' => true,
        ],
    ]);
});

it('uses a populated media pointer as the primary idempotency signal', function (): void {
    $tenant = Tenant::factory()->create();
    $media = Media::create([
        'mediable_type' => $tenant->getMorphClass(),
        'mediable_id' => $tenant->id,
        'collection' => 'documents',
        'disk' => 'local',
        'path' => 'media/canonical.pdf',
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
        'file_path' => 'tenant-documents/legacy.pdf',
        'mime_type' => 'application/pdf',
        'size' => 10,
    ]);

    $this->artisan('media:migrate-legacy')
        ->assertExitCode(0);

    expect(Media::query()->count())->toBe(1)
        ->and($document->fresh()->media_id)->toBe($media->id);
});

it('fails instead of guessing when recovery finds multiple media rows', function (): void {
    $tenant = Tenant::factory()->create();
    $attributes = [
        'mediable_type' => $tenant->getMorphClass(),
        'mediable_id' => $tenant->id,
        'collection' => 'documents',
        'disk' => 'local',
        'path' => 'tenant-documents/duplicate.pdf',
        'mime_type' => 'application/pdf',
        'size' => 10,
        'original_name' => 'duplicate.pdf',
        'position' => 0,
    ];
    Media::create($attributes);
    Media::create($attributes);
    $document = TenantDocument::create([
        'tenant_id' => $tenant->id,
        'type' => 'other',
        'original_name' => 'duplicate.pdf',
        'file_path' => $attributes['path'],
        'mime_type' => 'application/pdf',
        'size' => 10,
    ]);

    $this->artisan('media:migrate-legacy')
        ->expectsOutputToContain('failed=1')
        ->assertExitCode(1);

    expect($document->fresh()->media_id)->toBeNull();
});
