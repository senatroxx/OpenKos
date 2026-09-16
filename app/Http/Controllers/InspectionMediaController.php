<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inspection\StoreInspectionPhotoRequest;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\Media;
use App\Services\Media\MediaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspectionMediaController extends Controller
{
    public function store(
        StoreInspectionPhotoRequest $request,
        Inspection $inspection,
        InspectionItem $item,
        MediaManager $mediaManager,
    ): RedirectResponse {
        $this->authorize('update', $inspection);
        $item = $this->ownedItem($inspection, $item);

        abort_if($inspection->isCompleted(), 422, __('Completed inspections cannot receive new photos.'));

        $mediaManager->storeAtEnd($item, 'photos', $request->validated('file'));

        return back();
    }

    public function show(Inspection $inspection, InspectionItem $item, Media $media): StreamedResponse
    {
        $this->authorize('view', $inspection);
        $item = $this->ownedItem($inspection, $item);
        $media = $item->media()->where('collection', 'photos')->whereKey($media->id)->firstOrFail();
        $storage = Storage::disk($media->disk);

        abort_unless($storage->exists($media->path), 404);

        return $storage->response($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
        ]);
    }

    public function destroy(
        Inspection $inspection,
        InspectionItem $item,
        Media $media,
        MediaManager $mediaManager,
    ): RedirectResponse {
        $this->authorize('update', $inspection);
        $item = $this->ownedItem($inspection, $item);

        abort_if($inspection->isCompleted(), 422, __('Completed inspection photos are immutable.'));

        $media = $item->media()->where('collection', 'photos')->whereKey($media->id)->firstOrFail();
        $mediaManager->remove($media);

        return back();
    }

    private function ownedItem(Inspection $inspection, InspectionItem $item): InspectionItem
    {
        return $inspection->items()->whereKey($item->id)->firstOrFail();
    }
}
