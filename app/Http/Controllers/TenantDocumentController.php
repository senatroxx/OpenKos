<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantDocumentRequest;
use App\Models\Tenant;
use App\Models\TenantDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantDocumentController extends Controller
{
    public function store(StoreTenantDocumentRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $uuid = (string) Str::uuid();
        $path = $file->storeAs(
            'tenant-documents/'.$tenant->id,
            $uuid.'.'.$extension,
            'local'
        );

        $tenant->documents()->create([
            'type' => $request->input('type'),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return to_route('tenants.index');
    }

    public function show(Tenant $tenant, TenantDocument $document): StreamedResponse
    {
        $this->authorize('view', $tenant);

        abort_if($document->tenant_id !== $tenant->id, 404);

        return Storage::disk('local')->response($document->file_path, $document->original_name);
    }

    public function destroy(Tenant $tenant, TenantDocument $document): RedirectResponse
    {
        $this->authorize('update', $tenant);

        abort_if($document->tenant_id !== $tenant->id, 404);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return to_route('tenants.index');
    }
}
