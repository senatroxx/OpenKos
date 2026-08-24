<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenant\StoreTenantDocumentRequest;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantDocument;
use App\Services\Media\MediaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantDocumentController extends Controller
{
    public function store(StoreTenantDocumentRequest $request, Tenant $tenant, MediaManager $mediaManager): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $file = $request->file('file');

        DB::transaction(function () use ($mediaManager, $tenant, $file, $request): void {
            // Keep compatibility-phase uploads readable by released code, which assumes local.
            $media = $mediaManager->store($tenant, 'documents', $file, disk: 'local');

            $document = $tenant->documents()->make([
                'media_id' => $media->id,
                'type' => $request->input('type'),
                'original_name' => $media->original_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
            ]);

            // Deprecated compatibility field; canonical storage is Media.
            $document->forceFill(['file_path' => $media->path])->saveOrFail();
        });

        return back();
    }

    public function show(Tenant $tenant, TenantDocument $document): StreamedResponse
    {
        $this->authorize('view', $tenant);

        abort_if($document->tenant_id !== $tenant->id, 404);

        if ($document->media_id !== null) {
            $media = $this->canonicalMedia($tenant, $document);

            $storage = Storage::disk($media->disk);
            abort_unless($storage->exists($media->path), 404);

            return $storage->response($media->path, $media->original_name, [
                'Content-Type' => $media->mime_type,
            ]);
        }

        $storage = Storage::disk('local');
        abort_unless($storage->exists($document->file_path), 404);

        return $storage->response($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    public function destroy(Tenant $tenant, TenantDocument $document, MediaManager $mediaManager): RedirectResponse
    {
        $this->authorize('update', $tenant);

        abort_if($document->tenant_id !== $tenant->id, 404);

        DB::transaction(function () use ($tenant, $document, $mediaManager): void {
            $media = $document->media;
            $legacyPath = $document->file_path;
            $hasCanonicalMedia = $document->media_id !== null;

            if ($hasCanonicalMedia) {
                $media = $this->canonicalMedia($tenant, $document);
            }

            $document->deleteOrFail();

            if ($hasCanonicalMedia) {
                $mediaManager->remove($media);

                return;
            }

            DB::afterCommit(function () use ($legacyPath): void {
                Storage::disk('local')->delete($legacyPath);
            });
        });

        return to_route('tenants.index');
    }

    private function canonicalMedia(Tenant $tenant, TenantDocument $document): Media
    {
        $media = $document->media;

        abort_if(
            $media === null
                || $media->mediable_type !== $tenant->getMorphClass()
                || (string) $media->mediable_id !== (string) $document->tenant_id
                || $media->collection !== 'documents',
            404,
        );

        return $media;
    }
}
