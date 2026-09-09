<?php

namespace App\Http\Controllers;

use App\Http\Requests\Media\ReorderGalleryMediaRequest;
use App\Http\Requests\Media\StoreGalleryMediaRequest;
use App\Http\Requests\Media\UpdateGalleryMediaRequest;
use App\Models\Media;
use App\Models\Property;
use App\Models\UnitType;
use App\Services\Media\MediaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitTypeMediaController extends Controller
{
    public function store(StoreGalleryMediaRequest $request, Property $property, UnitType $unitType, MediaManager $mediaManager): RedirectResponse
    {
        $this->authorizeUnitType($property, $unitType);

        $lastPosition = $mediaManager->forCollection($unitType, 'photos')->max('position');
        $position = $lastPosition === null ? 0 : ((int) $lastPosition) + 1;
        $validated = $request->validated();

        $mediaManager->store(
            $unitType,
            'photos',
            $validated['file'],
            position: $position,
            metadata: [
                'alt' => $validated['alt'] ?? null,
                'caption' => $validated['caption'] ?? null,
            ],
        );

        return back();
    }

    public function update(UpdateGalleryMediaRequest $request, Property $property, UnitType $unitType, Media $media, MediaManager $mediaManager): RedirectResponse
    {
        $this->authorizeUnitType($property, $unitType);
        $media = $this->ownedMedia($unitType, $media);
        $metadata = $media->metadata ?? [];

        foreach (['alt', 'caption'] as $key) {
            if ($request->has($key)) {
                $metadata[$key] = $request->validated($key);
            }
        }

        $mediaManager->updateMetadata($media, $metadata);

        return back();
    }

    public function reorder(ReorderGalleryMediaRequest $request, Property $property, UnitType $unitType, MediaManager $mediaManager): RedirectResponse
    {
        $this->authorizeUnitType($property, $unitType);

        try {
            $mediaManager->reorder($unitType, 'photos', $request->validated('media_ids'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['media_ids' => $exception->getMessage()]);
        }

        return back();
    }

    public function destroy(Property $property, UnitType $unitType, Media $media, MediaManager $mediaManager): RedirectResponse
    {
        $this->authorizeUnitType($property, $unitType);
        $media = $this->ownedMedia($unitType, $media);

        $mediaManager->remove($media);
        $this->normalizeRemaining($unitType, $mediaManager);

        return back();
    }

    public function show(Request $request, Property $property, UnitType $unitType, Media $media): StreamedResponse
    {
        $this->authorizeUnitType($property, $unitType, false);
        $media = $this->ownedMedia($unitType, $media);
        $storage = Storage::disk($media->disk);
        abort_unless($storage->exists($media->path), 404);

        return $storage->response($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
        ]);
    }

    private function authorizeUnitType(Property $property, UnitType $unitType, bool $update = true): void
    {
        abort_unless($unitType->property_id === $property->id, 404);
        $this->authorize($update ? 'update' : 'view', $unitType);
    }

    private function ownedMedia(UnitType $unitType, Media $media): Media
    {
        return $unitType->media()
            ->where('collection', 'photos')
            ->whereKey($media->id)
            ->firstOrFail();
    }

    private function normalizeRemaining(UnitType $unitType, MediaManager $mediaManager): void
    {
        $ids = $mediaManager->forCollection($unitType, 'photos')->pluck('id')->all();

        if ($ids !== []) {
            $mediaManager->reorder($unitType, 'photos', $ids);
        }
    }
}
