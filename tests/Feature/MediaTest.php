<?php

use App\Models\Media;
use App\Models\Property;
use App\Services\Media\MediaManager;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
});

it('stores media metadata and bytes for a polymorphic owner', function () {
    $property = Property::factory()->create();

    $media = app(MediaManager::class)->store(
        $property,
        'photos',
        UploadedFile::fake()->create('front-house.jpg', 1, 'image/jpeg'),
        position: 2,
        metadata: ['alt' => 'Front of house'],
    );

    expect($property->media()->sole()->is($media))->toBeTrue()
        ->and($media->mediable->is($property))->toBeTrue()
        ->and($media->collection)->toBe('photos')
        ->and($media->disk)->toBe('local')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->size)->toBeGreaterThan(0)
        ->and($media->original_name)->toBe('front-house.jpg')
        ->and($media->path)->not->toContain('front-house.jpg');

    Storage::disk('local')->assertExists($media->path);
});

it('uses the persisted owner identity when the owner model is dirty', function () {
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $manager = app(MediaManager::class);
    $persistedOwnerId = $property->getRawOriginal('id');
    $property->setAttribute('id', $otherProperty->id);

    $media = $manager->store(
        $property,
        'photos',
        UploadedFile::fake()->create('front-house.jpg', 1, 'image/jpeg'),
    );

    expect($media->mediable_id)->toBe($persistedOwnerId)
        ->and($manager->forCollection($property, 'photos')->sole()->is($media))->toBeTrue();
});

it('uses the configured default filesystem disk', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    $property = Property::factory()->create();

    $media = app(MediaManager::class)->store(
        $property,
        'photos',
        UploadedFile::fake()->create('front-house.jpg', 1, 'image/jpeg'),
    );

    expect($media->disk)->toBe('public');
    Storage::disk('public')->assertExists($media->path);
});

it('retrieves and reorders media within an owner collection', function () {
    $property = Property::factory()->create();
    $manager = app(MediaManager::class);

    $first = $manager->store($property, 'photos', UploadedFile::fake()->create('first.jpg', 1, 'image/jpeg'));
    $second = $manager->store($property, 'photos', UploadedFile::fake()->create('second.jpg', 1, 'image/jpeg'));
    $document = $manager->store($property, 'documents', UploadedFile::fake()->create('lease.pdf', 1, 'application/pdf'));

    $manager->reorder($property, 'photos', [$second->id, $first->id]);

    expect($manager->forCollection($property, 'photos')->pluck('id')->all())
        ->toBe([$second->id, $first->id])
        ->and($second->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1)
        ->and($document->fresh()->position)->toBe(0);
});

it('rejects duplicate or foreign media IDs during reorder', function () {
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $manager = app(MediaManager::class);
    $media = $manager->store($property, 'photos', UploadedFile::fake()->create('photo.jpg', 1, 'image/jpeg'));
    $foreign = $manager->store($otherProperty, 'photos', UploadedFile::fake()->create('foreign.jpg', 1, 'image/jpeg'));

    expect(fn () => $manager->reorder($property, 'photos', [$media->id, $media->id]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $manager->reorder($property, 'photos', [$foreign->id]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $manager->reorder($property, 'photos', [null]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $manager->reorder($property, 'photos', []))
        ->toThrow(InvalidArgumentException::class);
});

it('replaces a file while preserving media identity and ownership', function () {
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $manager = app(MediaManager::class);
    $media = $manager->store(
        $property,
        'photos',
        UploadedFile::fake()->create('old.jpg', 1, 'image/jpeg'),
        position: 3,
        metadata: ['alt' => 'Old'],
    );
    $oldPath = $media->path;
    $media->forceFill([
        'mediable_id' => $otherProperty->id,
        'collection' => 'mutated',
        'position' => 99,
        'metadata' => ['alt' => 'Dirty'],
    ]);

    $replaced = $manager->replace(
        $media,
        UploadedFile::fake()->create('new.webp', 2, 'image/webp'),
    );

    expect($replaced->id)->toBe($media->id)
        ->and($replaced->mediable_id)->toBe($property->id)
        ->and($replaced->collection)->toBe('photos')
        ->and($replaced->position)->toBe(3)
        ->and($replaced->mime_type)->toBe('image/webp')
        ->and($replaced->metadata)->toBe(['alt' => 'Old']);

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($replaced->path);

    $cleared = $manager->replace(
        $replaced,
        UploadedFile::fake()->create('cleared.webp', 2, 'image/webp'),
        metadata: null,
    );

    expect($cleared->fresh()->metadata)->toBeNull();
});

it('cleans up the new file when initial persistence fails', function () {
    $property = Property::factory()->create();
    $event = 'eloquent.creating: '.Media::class;

    Event::listen($event, function (Media $media): void {
        throw new RuntimeException('Simulated media persistence failure.');
    });

    try {
        expect(fn () => app(MediaManager::class)->store(
            $property,
            'photos',
            UploadedFile::fake()->create('failed.jpg', 1, 'image/jpeg'),
        ))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }

    expect(Media::query()->count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('media');
});

it('keeps the old file when replacement persistence fails', function () {
    $property = Property::factory()->create();
    $manager = app(MediaManager::class);
    $media = $manager->store($property, 'photos', UploadedFile::fake()->create('old.jpg', 1, 'image/jpeg'));
    $oldPath = $media->path;
    $event = 'eloquent.updating: '.Media::class;

    Event::listen($event, function (Media $media): void {
        throw new RuntimeException('Simulated media replacement failure.');
    });

    try {
        expect(fn () => $manager->replace(
            $media,
            UploadedFile::fake()->create('new.jpg', 1, 'image/jpeg'),
        ))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }

    expect($media->fresh()->path)->toBe($oldPath);
    Storage::disk('local')->assertExists($oldPath);
    expect(Storage::disk('local')->allFiles('media'))->toHaveCount(1);
});

it('retains the file when a committed media transaction reports an exception', function () {
    $property = Property::factory()->create();
    $event = TransactionCommitted::class;

    Event::listen($event, function (): void {
        throw new RuntimeException('Simulated post-commit failure.');
    });

    try {
        expect(fn () => app(MediaManager::class)->store(
            $property,
            'photos',
            UploadedFile::fake()->create('committed.jpg', 1, 'image/jpeg'),
        ))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }

    $media = Media::query()->sole();

    expect($media->path)->not->toBeEmpty();
    Storage::disk('local')->assertExists($media->path);
});

it('cleans the file when the root media transaction fails before commit', function () {
    $property = Property::factory()->create();
    DB::commit();
    $event = TransactionCommitting::class;

    Event::listen($event, function (): void {
        throw new RuntimeException('Simulated pre-commit failure.');
    });

    try {
        expect(fn () => app(MediaManager::class)->store(
            $property,
            'photos',
            UploadedFile::fake()->create('rolled-back.jpg', 1, 'image/jpeg'),
        ))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
        $property->delete();
    }

    expect(Media::query()->count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('media');
});

it('removes media rows and files, including when the file is already missing', function () {
    $property = Property::factory()->create();
    $manager = app(MediaManager::class);
    $media = $manager->store($property, 'photos', UploadedFile::fake()->create('photo.jpg', 1, 'image/jpeg'));
    $path = $media->path;

    $manager->remove($media);

    expect(Media::query()->find($media->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);

    $missing = Media::factory()->for($property, 'mediable')->create([
        'collection' => 'photos',
        'path' => 'media/missing.jpg',
    ]);

    $manager->remove($missing);

    expect(Media::query()->find($missing->id))->toBeNull();
});

it('preserves media through owner soft deletion until explicit cleanup', function () {
    $property = Property::factory()->create();
    $manager = app(MediaManager::class);
    $media = $manager->store($property, 'photos', UploadedFile::fake()->create('photo.jpg', 1, 'image/jpeg'));
    $path = $media->path;

    $property->delete();

    expect(Media::query()->find($media->id))->not->toBeNull();
    Storage::disk('local')->assertExists($path);

    $manager->removeForOwner($property);

    expect(Media::query()->find($media->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('rolls back all owner media rows when bulk cleanup fails', function () {
    $property = Property::factory()->create();
    $manager = app(MediaManager::class);
    $first = $manager->store($property, 'photos', UploadedFile::fake()->create('first.jpg', 1, 'image/jpeg'));
    $second = $manager->store($property, 'photos', UploadedFile::fake()->create('second.jpg', 1, 'image/jpeg'));
    $event = 'eloquent.deleting: '.Media::class;
    $deletions = 0;

    Event::listen($event, function () use (&$deletions): void {
        if (++$deletions === 2) {
            throw new RuntimeException('Simulated bulk cleanup failure.');
        }
    });

    try {
        expect(fn () => $manager->removeForOwner($property))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }

    expect(Media::query()->whereKey([$first->id, $second->id])->count())->toBe(2);
    Storage::disk('local')->assertExists($first->path);
    Storage::disk('local')->assertExists($second->path);
});

it('rejects unsafe collection names', function (string $collection) {
    $property = Property::factory()->create();

    expect(fn () => app(MediaManager::class)->store(
        $property,
        $collection,
        UploadedFile::fake()->create('file.bin', 1, 'application/octet-stream'),
    ))->toThrow(InvalidArgumentException::class);
})->with(['photo gallery', '../photos', 'photos/extra', '']);
