<?php

namespace App\Http\Controllers;

use App\Http\Requests\Media\ReorderGalleryMediaRequest;
use App\Http\Requests\Media\StoreGalleryMediaRequest;
use App\Http\Requests\Media\UpdateGalleryMediaRequest;
use App\Models\Media;
use App\Models\Property;
use App\Services\Media\MediaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyMediaController extends Controller
{
    public function store(StoreGalleryMediaRequest $request, Property $property, MediaManager $mediaManager): RedirectResponse
    {
        $this->authorize('update', $property);

        $lastPosition = $mediaManager->forCollection($property, 'photos')->max('position');
        $position = $lastPosition === null ? 0 : ((int) $lastPosition) + 1;
        $validated = $request->validated();

        $mediaManager->store(
            $property,
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

    public function update(UpdateGalleryMediaRequest $request, Property $property, Media $media, MediaManager $mediaManager): RedirectResponse
    {
        $this->authorize('update', $property);
        $media = $this->ownedMedia($property, $media);
        $metadata = $media->metadata ?? [];

        foreach (['alt', 'caption'] as $key) {
            if ($request->has($key)) {
                $metadata[$key] = $request->validated($key);
            }
        }

        $mediaManager->updateMetadata($media, $metadata);

        return back();
    }

    public function reorder(ReorderGalleryMediaRequest $request, Property $property, MediaManager $mediaManager): RedirectResponse
    {
        $this->authorize('update', $property);

        try {
            $mediaManager->reorder($property, 'photos', $request->validated('media_ids'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['media_ids' => $exception->getMessage()]);
        }

        return back();
    }

    public function destroy(Property $property, Media $media, MediaManager $mediaManager): RedirectResponse
    {
        $this->authorize('update', $property);
        $media = $this->ownedMedia($property, $media);

        $mediaManager->remove($media);
        $this->normalizeRemaining($property, $mediaManager);

        return back();
    }

    public function show(Request $request, Property $property, Media $media): StreamedResponse
    {
        $this->authorize('view', $property);
        $media = $this->ownedMedia($property, $media);
        $storage = Storage::disk($media->disk);
        abort_unless($storage->exists($media->path), 404);

        return $storage->response($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
        ]);
    }

    private function ownedMedia(Property $property, Media $media): Media
    {
        return $property->media()
            ->where('collection', 'photos')
            ->whereKey($media->id)
            ->firstOrFail();
    }

    private function normalizeRemaining(Property $property, MediaManager $mediaManager): void
    {
        $ids = $mediaManager->forCollection($property, 'photos')->pluck('id')->all();

        if ($ids !== []) {
            $mediaManager->reorder($property, 'photos', $ids);
        }
    }
}
