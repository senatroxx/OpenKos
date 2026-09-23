<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateBranding;
use App\Actions\Settings\UpdateSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBrandingRequest;
use App\Http\Requests\Settings\UpdatePublicPortalRequest;
use App\Models\Setting;
use App\Services\PublicPortal\PublicPortalMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class PublicPortalController extends Controller
{
    private const SOCIAL_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private UpdateSettings $updateSettings,
        private UpdateBranding $updateBranding,
        private PublicPortalMetadataResolver $metadata,
    ) {}

    public function edit(): Response
    {
        $hasSocialImage = $this->hasSocialImage();
        $homepageMetadata = $this->metadata->homepage(route('public.portal.index', absolute: false));

        return Inertia::render('public-portal/seo', [
            'settings' => Setting::some([
                'public_site_name',
                'public_homepage_title',
                'public_homepage_description',
            ]),
            'resolved' => [
                'siteName' => $homepageMetadata->siteName,
                'homepageTitle' => $homepageMetadata->title,
                'homepageDescription' => $homepageMetadata->description,
            ],
            'socialImageUrl' => $hasSocialImage ? $this->socialImageUrl() : null,
            'hasSocialImage' => $hasSocialImage,
        ]);
    }

    public function overview(): Response
    {
        $homepageMetadata = $this->metadata->homepage(route('public.portal.index', absolute: false));

        return Inertia::render('public-portal/overview', [
            'resolved' => [
                'siteName' => $homepageMetadata->siteName,
                'homepageTitle' => $homepageMetadata->title,
                'homepageDescription' => $homepageMetadata->description,
            ],
            'hasSocialImage' => $this->hasSocialImage(),
        ]);
    }

    public function update(UpdatePublicPortalRequest $request): RedirectResponse
    {
        $this->updateSettings->execute($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Public portal settings updated.')]);

        return back();
    }

    public function updateSocialImage(UpdateBrandingRequest $request): RedirectResponse
    {
        $this->updateBranding->execute('og-image', $request->file('file'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Social image updated.')]);

        return back();
    }

    public function removeSocialImage(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        $this->updateBranding->remove('og-image', $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default social image restored.')]);

        return back();
    }

    private function hasSocialImage(): bool
    {
        $path = Setting::get('public_og_image_path');

        if (! is_string($path) || ! filled($path)) {
            return false;
        }

        try {
            $disk = Storage::disk((string) config('filesystems.default', 'local'));

            return $disk->exists($path) && in_array($disk->mimeType($path), self::SOCIAL_IMAGE_MIMES, true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function socialImageUrl(): string
    {
        return route('branding.asset', [
            'asset' => 'og-image',
            'v' => sha1((string) Setting::get('public_og_image_path')),
        ]);
    }
}
