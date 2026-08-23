<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenant\StoreTenantDocumentRequest;
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
            $media = $mediaManager->store($tenant, 'documents', $file);

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
            abort_if($document->media === null, 404);

            $storage = Storage::disk($document->media->disk);
            abort_unless($storage->exists($document->media->path), 404);

            return $storage->response($document->media->path, $document->media->original_name, [
                'Content-Type' => $document->media->mime_type,
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

        DB::transaction(function () use ($document, $mediaManager): void {
            $media = $document->media;
            $legacyPath = $document->file_path;
            $hasCanonicalMedia = $document->media_id !== null;

            $document->deleteOrFail();

            if ($hasCanonicalMedia) {
                abort_if($media === null, 404);
                $mediaManager->remove($media);

                return;
            }

            DB::afterCommit(function () use ($legacyPath): void {
                Storage::disk('local')->delete($legacyPath);
            });
        });

        return to_route('tenants.index');
    }
}
