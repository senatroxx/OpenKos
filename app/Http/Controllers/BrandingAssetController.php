<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BrandingAssetController extends Controller
{
    private const ASSETS = [
        'logo' => [
            'setting' => 'branding_logo_path',
            'fallback' => 'assets/brand/openkos-logo.svg',
            'fallback_mime' => 'image/svg+xml',
            'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'favicon' => [
            'setting' => 'branding_favicon_path',
            'fallback' => 'favicon.ico',
            'fallback_mime' => 'image/x-icon',
            'mimes' => ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'],
        ],
    ];

    public function __invoke(string $asset): BinaryFileResponse|StreamedResponse
    {
        $definition = self::ASSETS[$asset] ?? abort(404);
        $fallback = public_path($definition['fallback']);

        try {
            $disk = Storage::disk((string) config('filesystems.default', 'local'));
            $path = Setting::get($definition['setting']);

            if (is_string($path) && filled($path) && $disk->exists($path)) {
                $mimeType = $disk->mimeType($path);

                if (is_string($mimeType) && in_array($mimeType, $definition['mimes'], true)) {
                    return $this->storedResponse($disk, $path, $mimeType);
                }
            }
        } catch (Throwable) {
            // Fall through to the bundled asset when custom storage is unavailable.
        }

        abort_unless(is_file($fallback), 404);

        return response()->file($fallback, [
            'Content-Type' => $definition['fallback_mime'],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function storedResponse(FilesystemAdapter $disk, string $path, string $mimeType): StreamedResponse
    {
        return $disk->response($path, null, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
