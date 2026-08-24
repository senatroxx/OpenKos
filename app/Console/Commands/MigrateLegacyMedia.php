<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Tenant;
use App\Models\TenantDocument;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[Signature('media:migrate-legacy {--chunk=500 : Number of legacy rows to process per chunk}')]
#[Description('Backfill tenant documents and payment proofs into canonical media')]
final class MigrateLegacyMedia extends Command
{
    private const DISK = 'local';

    /**
     * @var array<string, int>
     */
    private array $counts = [
        'migrated' => 0,
        'recovered' => 0,
        'skipped' => 0,
        'missing' => 0,
        'failed' => 0,
    ];

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk');

        if ($chunk < 1) {
            $this->error('The chunk size must be at least 1.');

            return self::INVALID;
        }

        TenantDocument::query()
            ->orderBy('id')
            ->chunkById($chunk, function (Collection $documents): void {
                foreach ($documents as $document) {
                    $this->processTenantDocument($document);
                }
            });

        PaymentProof::query()
            ->orderBy('id')
            ->chunkById($chunk, function (Collection $proofs): void {
                foreach ($proofs as $proof) {
                    $this->processPaymentProof($proof);
                }
            });

        $this->info(sprintf(
            'Legacy media migration: migrated=%d, recovered=%d, skipped=%d, missing=%d, failed=%d.',
            $this->counts['migrated'],
            $this->counts['recovered'],
            $this->counts['skipped'],
            $this->counts['missing'],
            $this->counts['failed'],
        ));

        return $this->counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function processTenantDocument(TenantDocument $document): void
    {
        try {
            $status = $this->migrateLegacyRecord(
                Tenant::class,
                'documents',
                'tenant_documents',
                $document->id,
                fn (): ?Model => TenantDocument::query()->lockForUpdate()->find($document->id),
                fn (Model $record): array => [
                    'owner_id' => (int) $record->tenant_id,
                    'path' => (string) $record->file_path,
                    'original_name' => (string) $record->original_name,
                    'mime_type' => (string) $record->mime_type,
                    'size' => (int) $record->size,
                ],
                function (Model $record, Media $media): void {
                    $record->forceFill(['media_id' => $media->id])->saveOrFail();
                },
            );

            $this->counts[$status]++;
        } catch (Throwable $exception) {
            $this->recordFailure('tenant document', $document->id, $exception);
        }
    }

    private function processPaymentProof(PaymentProof $proof): void
    {
        try {
            $status = $this->migrateLegacyRecord(
                Payment::class,
                'proofs',
                'payment_proofs',
                $proof->id,
                fn (): ?Model => PaymentProof::query()->lockForUpdate()->find($proof->id),
                fn (Model $record): array => [
                    'owner_id' => (int) $record->payment_id,
                    'path' => (string) $record->path,
                    'original_name' => (string) $record->original_name,
                    'mime_type' => (string) $record->mime_type,
                    'size' => null,
                ],
                function (Model $record, Media $media): void {
                    $record->forceFill(['media_id' => $media->id])->saveOrFail();
                },
            );

            $this->counts[$status]++;
        } catch (Throwable $exception) {
            $this->recordFailure('payment proof', $proof->id, $exception);
        }
    }

    /**
     * @param  Closure(): ?Model  $load
     * @param  Closure(Model): array{owner_id: int, path: string, original_name: string, mime_type: string, size: ?int}  $attributes
     * @param  Closure(Model, Media): void  $link
     */
    private function migrateLegacyRecord(
        string $ownerType,
        string $collection,
        string $table,
        int $legacyId,
        Closure $load,
        Closure $attributes,
        Closure $link,
    ): string {
        return DB::transaction(function () use ($ownerType, $collection, $table, $legacyId, $load, $attributes, $link): string {
            $record = $load();

            if ($record === null || $record->media_id !== null) {
                return 'skipped';
            }

            $values = $attributes($record);
            $this->lockOwner($ownerType, $values['owner_id']);
            $existing = $this->findExistingMedia(
                $ownerType,
                $values['owner_id'],
                $collection,
                $values['path'],
            );
            $missing = $existing !== null && ! $this->mediaFileExists($existing);

            if ($existing === null) {
                [$existing, $missing] = $this->createMedia(
                    $ownerType,
                    $values['owner_id'],
                    $collection,
                    $table,
                    $legacyId,
                    $values,
                );
            } elseif ($missing) {
                $this->markMissingMedia($existing, $table, $legacyId);
            }

            $link($record, $existing);

            return $missing ? 'missing' : ($existing->wasRecentlyCreated ? 'migrated' : 'recovered');
        });
    }

    private function findExistingMedia(
        string $ownerType,
        int $ownerId,
        string $collection,
        string $path,
    ): ?Media {
        $matches = Media::query()
            ->where('mediable_type', (new $ownerType)->getMorphClass())
            ->where('mediable_id', $ownerId)
            ->where('collection', $collection)
            ->where('disk', self::DISK)
            ->where('path', $path)
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple canonical media rows match the legacy record.');
        }

        return $matches->first();
    }

    private function lockOwner(string $ownerType, int $ownerId): void
    {
        $owner = new $ownerType;
        $query = $owner->newQuery();

        if (in_array(SoftDeletes::class, class_uses_recursive($ownerType), true)) {
            $query->withTrashed();
        }

        $query->lockForUpdate()->findOrFail($ownerId);
    }

    private function mediaFileExists(Media $media): bool
    {
        return $media->path !== '' && Storage::disk($media->disk)->exists($media->path);
    }

    private function markMissingMedia(Media $media, string $table, int $legacyId): void
    {
        $metadata = is_array($media->metadata) ? $media->metadata : [];
        $legacyMetadata = is_array($metadata['legacy'] ?? null) ? $metadata['legacy'] : [];

        $media->forceFill([
            'metadata' => [
                ...$metadata,
                'legacy' => [
                    ...$legacyMetadata,
                    'table' => $table,
                    'id' => $legacyId,
                    'missing_file' => true,
                ],
            ],
        ])->saveOrFail();

        $this->warn("Missing legacy file: {$table}#{$legacyId} ({$media->path}).");
    }

    /**
     * @param  array{owner_id: int, path: string, original_name: string, mime_type: string, size: ?int}  $values
     * @return array{0: Media, 1: bool}
     */
    private function createMedia(
        string $ownerType,
        int $ownerId,
        string $collection,
        string $table,
        int $legacyId,
        array $values,
    ): array {
        $storage = Storage::disk(self::DISK);
        $path = $values['path'];
        $missing = $path === '' || ! $storage->exists($path);
        $size = max(0, $values['size'] ?? 0);
        $mimeType = $values['mime_type'] ?: 'application/octet-stream';

        if (! $missing) {
            try {
                $size = (int) $storage->size($path);
            } catch (Throwable) {
                // Keep the legacy size when the backend cannot report it.
            }

            try {
                $detectedMimeType = $storage->mimeType($path);

                if (is_string($detectedMimeType) && $detectedMimeType !== '') {
                    $mimeType = $detectedMimeType;
                }
            } catch (Throwable) {
                // Keep the legacy MIME type when the backend cannot report it.
            }
        }

        $metadata = [
            'legacy' => [
                'table' => $table,
                'id' => $legacyId,
                'missing_file' => $missing,
            ],
        ];

        $media = Media::create([
            'mediable_type' => (new $ownerType)->getMorphClass(),
            'mediable_id' => $ownerId,
            'collection' => $collection,
            'disk' => self::DISK,
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $size,
            'original_name' => $this->originalName($values['original_name']),
            'position' => 0,
            'metadata' => $metadata,
        ]);

        if ($missing) {
            $this->warn("Missing legacy file: {$table}#{$legacyId} ({$path}).");
        }

        return [$media, $missing];
    }

    private function originalName(string $originalName): string
    {
        $name = basename($originalName);

        return Str::substr($name !== '' ? $name : 'file', 0, 255);
    }

    private function recordFailure(string $type, int $id, Throwable $exception): void
    {
        $this->counts['failed']++;
        $this->error("Failed to migrate {$type}#{$id}: {$exception->getMessage()}");
        report($exception);
    }
}
