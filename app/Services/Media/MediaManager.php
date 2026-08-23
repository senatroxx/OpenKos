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
        $this->validateOwner($owner);
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
                'mediable_id' => $owner->getKey(),
                'collection' => $collection,
                'disk' => $diskName,
                'path' => $path,
                'mime_type' => $mimeType,
                'size' => $size,
                'original_name' => $originalName,
                'position' => $position,
                'metadata' => $metadata,
            ]),
        );
    }

    public function replace(
        Media $media,
        UploadedFile $file,
        ?string $disk = null,
        ?array $metadata = null,
    ): Media {
        [$mimeType, $size, $originalName] = $this->fileMetadata($file);
        $diskName = $this->resolveDisk($disk ?? $media->disk);
        $storage = Storage::disk($diskName);
        $path = $this->storeFile($storage, $file);

        $oldDiskName = (string) $media->disk;
        $oldPath = (string) $media->path;
        $mediaId = $media->getKey();

        return $this->persistWithRollbackCleanup(
            $storage,
            $diskName,
            $path,
            function () use ($media, $diskName, $path, $mimeType, $size, $originalName, $metadata, $oldDiskName, $oldPath, $mediaId): Media {
                $media->forceFill([
                    'disk' => $diskName,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'original_name' => $originalName,
                    'metadata' => $metadata ?? $media->metadata,
                ])->saveOrFail();

                DB::afterCommit(function () use ($oldDiskName, $oldPath, $mediaId): void {
                    $this->deleteFileByDiskName($oldDiskName, $oldPath, $mediaId);
                });

                return $media;
            },
        );
    }

    public function remove(Media $media): void
    {
        $diskName = (string) $media->disk;
        $path = (string) $media->path;
        $mediaId = $media->getKey();

        DB::transaction(function () use ($media, $diskName, $path, $mediaId): void {
            $media->deleteOrFail();

            DB::afterCommit(function () use ($diskName, $path, $mediaId): void {
                $this->deleteFileByDiskName($diskName, $path, $mediaId);
            });
        });
    }

    public function removeForOwner(Model $owner, ?string $collection = null): void
    {
        $this->validateOwner($owner);

        $media = Media::query()
            ->whereMorphedTo('mediable', $owner)
            ->when(
                $collection !== null,
                fn (Builder $query) => $query->where('collection', $this->validateCollection($collection)),
            )
            ->get();

        foreach ($media as $item) {
            $this->remove($item);
        }
    }

    public function reorder(Model $owner, string $collection, array $mediaIds): void
    {
        $this->validateOwner($owner);
        $collection = $this->validateCollection($collection);
        $mediaIds = array_values($mediaIds);
        $normalizedIds = array_map(static fn (mixed $id): string => (string) $id, $mediaIds);

        if (count($normalizedIds) !== count(array_unique($normalizedIds))) {
            throw new InvalidArgumentException('Media IDs must be unique.');
        }

        $media = $this->forCollection($owner, $collection)
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

        DB::transaction(function () use ($media, $normalizedIds): void {
            foreach ($normalizedIds as $position => $mediaId) {
                $media->get($mediaId)->forceFill(['position' => $position])->saveOrFail();
            }
        });
    }

    public function forCollection(Model $owner, string $collection): Builder
    {
        $this->validateOwner($owner);

        return Media::query()
            ->whereMorphedTo('mediable', $owner)
            ->where('collection', $this->validateCollection($collection))
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $persist
     * @return TValue
     */
    private function persistWithRollbackCleanup(
        FilesystemAdapter $storage,
        string $diskName,
        string $path,
        Closure $persist,
    ): mixed {
        $connection = DB::connection();
        $initialLevel = $connection->transactionLevel();
        $transactionStarted = false;
        $cleanupRegistered = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $connection->afterRollBack(function () use ($storage, $diskName, $path): void {
                $this->deleteFile($storage, $diskName, $path);
            });
            $cleanupRegistered = true;

            $result = $persist();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if (! $transactionStarted) {
                $this->deleteFile($storage, $diskName, $path);
            } elseif ($connection->transactionLevel() > $initialLevel) {
                try {
                    $connection->rollBack();
                } catch (Throwable $rollbackException) {
                    report($rollbackException);
                }

                if (! $cleanupRegistered) {
                    $this->deleteFile($storage, $diskName, $path);
                }
            }

            throw $exception;
        }
    }

    private function validateOwner(Model $owner): void
    {
        if ($owner->getKey() === null) {
            throw new InvalidArgumentException('Media owners must be persisted before attaching media.');
        }
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
