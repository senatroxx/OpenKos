<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Property;
use App\Models\UnitType;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicListingMediaController extends Controller
{
    public function show(Media $media): StreamedResponse
    {
        abort_unless($media->collection === 'photos', 404);

        $owner = $media->mediable;
        abort_unless($owner instanceof Property || $owner instanceof UnitType, 404);

        $isPublic = $owner instanceof Property
            ? $this->isPublicProperty($owner)
            : $this->isPublicUnitType($owner);

        abort_unless($isPublic, 404);

        $storage = Storage::disk((string) $media->disk);
        abort_unless($storage->exists($media->path), 404);

        return $storage->response($media->path, $media->original_name, [
            'Cache-Control' => 'no-store',
            'Content-Type' => $media->mime_type,
        ]);
    }

    private function isPublicProperty(Property $property): bool
    {
        return ! $property->trashed()
            && $property->is_active
            && $property->is_published
            && filled($property->public_slug);
    }

    private function isPublicUnitType(UnitType $unitType): bool
    {
        $property = $unitType->property;

        return $unitType->is_active
            && $unitType->is_published
            && filled($unitType->public_slug)
            && $property instanceof Property
            && $this->isPublicProperty($property);
    }
}
