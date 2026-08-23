<?php

namespace App\Services\Media;

use App\Models\Media;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MediaManager
{
    public function store(
        Model $owner,
        string $collection,
        UploadedFile $file,
        ?string $disk = null,
        int $position = 0,
        ?array $metadata = null,
    ): Media {
        $ownerId = $this->ownerId($owner);
        $collection = $this->validateCollection($collection);
        $this->validatePosition($position);

        [$mimeType, $size, $originalName] = $this->fileMetadata($file);
        $diskName = $this->resolveDisk($disk);
        $storage = Storage::disk($diskName);
        $path = $this->storeFile($storage, $file);

        return $this->persistWithRollbackCleanup(
            $storage,
            $diskName,
            $path,
            fn (): Media => Media::create([
                'mediable_type' => $owner->getMorphClass(),
                'mediable_id' => $ownerId,
                'collection' => $collection,
                'disk' => $diskName,
                'path' => $path,
                'mime_type' => $mimeType,
                'size' => $size,
                'original_name' => $originalName,
                'position' => $position,
                'metadata' => $metadata,
            ]),
            fn (mixed $result): bool => $this->mediaHasExpectedPath($result, $diskName, $path),
        );
    }

    public function replace(
        Media $media,
        UploadedFile $file,
        ?string $disk = null,
        ?array $metadata = null,
    ): Media {
        $metadataProvided = func_num_args() >= 4;
        $mediaId = $this->persistedMediaId($media);

        if ($mediaId === null) {
            throw new InvalidArgumentException('Media must be persisted before it can be replaced.');
        }

        $persistedMedia = Media::query()->findOrFail($mediaId);
        [$mimeType, $size, $originalName] = $this->fileMetadata($file);
        $diskName = $this->resolveDisk($disk ?? (string) $persistedMedia->disk);
        $storage = Storage::disk($diskName);
        $path = $this->storeFile($storage, $file);

        return $this->persistWithRollbackCleanup(
            $storage,
            $diskName,
            $path,
            function () use ($mediaId, $disk, $diskName, $path, $mimeType, $size, $originalName, $metadata, $metadataProvided): Media {
                $lockedMedia = Media::query()->lockForUpdate()->findOrFail($mediaId);
                $oldDiskName = (string) $lockedMedia->disk;
                $oldPath = (string) $lockedMedia->path;

                if ($disk === null && $oldDiskName !== $diskName) {
                    throw new RuntimeException('Media changed during replacement; please retry.');
                }

                $lockedMedia->forceFill([
                    'disk' => $diskName,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'original_name' => $originalName,
                    'metadata' => $metadataProvided ? $metadata : $lockedMedia->metadata,
                ])->saveOrFail();

                if ($oldDiskName !== $diskName || $oldPath !== $path) {
                    DB::afterCommit(function () use ($oldDiskName, $oldPath, $mediaId): void {
                        $this->deleteFileByDiskName($oldDiskName, $oldPath, $mediaId);
                    });
                }

                return $lockedMedia;
            },
            fn (mixed $result): bool => $this->mediaHasExpectedPath($result, $diskName, $path),
        );
    }

    public function remove(Media $media): void
    {
        $mediaId = $this->persistedMediaId($media);

        if ($mediaId === null) {
            throw new InvalidArgumentException('Media must be persisted before it can be removed.');
        }

        DB::transaction(function () use ($mediaId): void {
            $lockedMedia = Media::query()->lockForUpdate()->findOrFail($mediaId);
            $diskName = (string) $lockedMedia->disk;
            $path = (string) $lockedMedia->path;

            $lockedMedia->deleteOrFail();

            DB::afterCommit(function () use ($diskName, $path, $mediaId): void {
                $this->deleteFileByDiskName($diskName, $path, $mediaId);
            });
        });
    }

    public function removeForOwner(Model $owner, ?string $collection = null): void
    {
        $ownerId = $this->ownerId($owner);
        $collection = $collection === null ? null : $this->validateCollection($collection);

        DB::transaction(function () use ($owner, $ownerId, $collection): void {
            $media = Media::query()
                ->where('mediable_type', $owner->getMorphClass())
                ->where('mediable_id', $ownerId)
                ->when($collection !== null, fn (Builder $query) => $query->where('collection', $collection))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $files = [];

            foreach ($media as $item) {
                $files[] = [
                    'disk' => (string) $item->disk,
                    'path' => (string) $item->path,
                    'id' => $item->getKey(),
                ];
                $item->deleteOrFail();
            }

            DB::afterCommit(function () use ($files): void {
                foreach ($files as $file) {
                    $this->deleteFileByDiskName($file['disk'], $file['path'], $file['id']);
                }
            });
        });
    }

    public function reorder(Model $owner, string $collection, array $mediaIds): void
    {
        $this->ownerId($owner);
        $collection = $this->validateCollection($collection);
        $mediaIds = array_values($mediaIds);

        foreach ($mediaIds as $mediaId) {
            if (! is_int($mediaId) && ! is_string($mediaId)) {
                throw new InvalidArgumentException('Media IDs must be integers or strings.');
            }
        }

        $normalizedIds = array_map(static fn (mixed $id): string => (string) $id, $mediaIds);

        if (count($normalizedIds) !== count(array_unique($normalizedIds))) {
            throw new InvalidArgumentException('Media IDs must be unique.');
        }

        DB::transaction(function () use ($owner, $collection, $mediaIds, $normalizedIds): void {
            $media = $this->forCollection($owner, $collection)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Media $item): string => (string) $item->getKey());

            if ($media->count() !== count($mediaIds)) {
                throw new InvalidArgumentException('Media IDs must belong to the owner and collection.');
            }

            if ($mediaIds === []) {
                return;
            }

            $ownerIds = array_map(static fn (mixed $id): string => (string) $id, $media->keys()->all());
            $requestedIds = $normalizedIds;
            sort($ownerIds);
            sort($requestedIds);

            if ($ownerIds !== $requestedIds) {
                throw new InvalidArgumentException('Media IDs must belong to the owner and collection.');
            }

            foreach ($normalizedIds as $position => $mediaId) {
                $media->get($mediaId)->forceFill(['position' => $position])->saveOrFail();
            }
        });
    }

    public function forCollection(Model $owner, string $collection): Builder
    {
        $ownerId = $this->ownerId($owner);

        return Media::query()
            ->where('mediable_type', $owner->getMorphClass())
            ->where('mediable_id', $ownerId)
            ->where('collection', $this->validateCollection($collection))
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $persist
     * @param  Closure(TValue): bool  $verify
     * @return TValue
     */
    private function persistWithRollbackCleanup(
        FilesystemAdapter $storage,
        string $diskName,
        string $path,
        Closure $persist,
        Closure $verify,
    ): mixed {
        $connection = DB::connection();
        $initialLevel = $connection->transactionLevel();
        $transactionStarted = false;
        $cleanupRegistered = false;
        $commitAttempted = false;
        $rootCommitAttempted = false;
        $result = null;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $connection->afterRollBack(function () use (&$rootCommitAttempted, $storage, $diskName, $path): void {
                if (! $rootCommitAttempted) {
                    $this->deleteFile($storage, $diskName, $path);
                }
            });
            $cleanupRegistered = true;

            $result = $persist();
            $commitAttempted = true;
            $rootCommitAttempted = $initialLevel === 0;
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if (! $transactionStarted) {
                $this->deleteFile($storage, $diskName, $path);
            } elseif (! $commitAttempted) {
                if ($connection->transactionLevel() > $initialLevel) {
                    try {
                        $connection->rollBack();
                    } catch (Throwable $rollbackException) {
                        report($rollbackException);
                    }
                }

                if (! $cleanupRegistered) {
                    $this->deleteFile($storage, $diskName, $path);
                }
            } elseif ($initialLevel > 0) {
                if ($connection->transactionLevel() > $initialLevel) {
                    try {
                        $connection->rollBack();
                    } catch (Throwable $rollbackException) {
                        report($rollbackException);
                    }
                }
            } else {
                $rootCommitAttempted = true;
                $rollbackSucceeded = true;

                try {
                    if ($connection->transactionLevel() > $initialLevel) {
                        $connection->rollBack();
                    }
                } catch (Throwable $rollbackException) {
                    $rollbackSucceeded = false;
                    report($rollbackException);
                }

                try {
                    $persisted = $verify($result);
                } catch (Throwable $verificationException) {
                    Log::error('Media persistence outcome requires reconciliation.', [
                        'disk' => $diskName,
                        'path' => $path,
                        'exception' => $verificationException,
                    ]);

                    throw new RuntimeException(
                        'Media persistence outcome requires reconciliation.',
                        0,
                        $exception,
                    );
                }

                if (! $rollbackSucceeded) {
                    Log::error('Media persistence outcome requires reconciliation.', [
                        'disk' => $diskName,
                        'path' => $path,
                        'media_id' => $result instanceof Media ? $result->getKey() : null,
                        'exception' => $exception,
                    ]);

                    throw new RuntimeException(
                        'Media persistence outcome requires reconciliation.',
                        0,
                        $exception,
                    );
                }

                if (! $persisted) {
                    $this->deleteFile($storage, $diskName, $path);
                } else {
                    Log::warning('Media commit completed with an exception.', [
                        'disk' => $diskName,
                        'path' => $path,
                        'media_id' => $result instanceof Media ? $result->getKey() : null,
                        'exception' => $exception,
                    ]);
                }
            }

            throw $exception;
        }
    }

    private function ownerId(Model $owner): mixed
    {
        if (! $owner->exists || $owner->getKey() === null) {
            throw new InvalidArgumentException('Media owners must be persisted before attaching media.');
        }

        return $owner->getRawOriginal($owner->getKeyName()) ?? $owner->getKey();
    }

    private function validateCollection(string $collection): string
    {
        if (preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $collection) !== 1) {
            throw new InvalidArgumentException('Media collections must contain only letters, numbers, underscores, or hyphens.');
        }

        return $collection;
    }

    private function validatePosition(int $position): void
    {
        if ($position < 0) {
            throw new InvalidArgumentException('Media positions cannot be negative.');
        }
    }

    private function persistedMediaId(Media $media): mixed
    {
        if (! $media->exists) {
            return null;
        }

        return $media->getRawOriginal($media->getKeyName()) ?? $media->getKey();
    }

    private function mediaHasExpectedPath(mixed $media, string $diskName, string $path): bool
    {
        return $media instanceof Media
            && $media->getKey() !== null
            && Media::query()
                ->whereKey($media->getKey())
                ->where('disk', $diskName)
                ->where('path', $path)
                ->exists();
    }

    /**
     * @return array{string, int, string}
     */
    private function fileMetadata(UploadedFile $file): array
    {
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        if (! is_string($mimeType) || $mimeType === '' || ! is_int($size)) {
            throw new InvalidArgumentException('Media files must have detectable MIME type and size.');
        }

        $originalName = basename($file->getClientOriginalName());

        return [$mimeType, $size, Str::substr($originalName !== '' ? $originalName : 'file', 0, 255)];
    }

    private function resolveDisk(?string $disk): string
    {
        $disk ??= (string) config('filesystems.default', 'local');

        if ($disk === '') {
            throw new InvalidArgumentException('A filesystem disk is required for media.');
        }

        return $disk;
    }

    private function storeFile(FilesystemAdapter $storage, UploadedFile $file): string
    {
        $path = $storage->putFile('media', $file);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store media file.');
        }

        return $path;
    }

    private function deleteFile(
        FilesystemAdapter $storage,
        string $diskName,
        string $path,
        mixed $mediaId = null,
    ): void {
        try {
            if (! $storage->delete($path)) {
                Log::warning('Media file cleanup failed.', [
                    'disk' => $diskName,
                    'path' => $path,
                    'media_id' => $mediaId,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Media file cleanup failed.', [
                'disk' => $diskName,
                'path' => $path,
                'media_id' => $mediaId,
                'exception' => $exception,
            ]);
        }
    }

    private function deleteFileByDiskName(string $diskName, string $path, mixed $mediaId = null): void
    {
        try {
            $this->deleteFile(Storage::disk($diskName), $diskName, $path, $mediaId);
        } catch (Throwable $exception) {
            Log::warning('Media file cleanup failed.', [
                'disk' => $diskName,
                'path' => $path,
                'media_id' => $mediaId,
                'exception' => $exception,
            ]);
        }
    }
}
