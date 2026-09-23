<?php

namespace App\Services\PublicPortal;

use App\Business\PublicPortal\PublicPortalMetadataComposer;
use App\Data\PublicPortal\PublicPortalMetadataData;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

final class PublicPortalMetadataResolver
{
    private const SOCIAL_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const DEFAULT_SITE_NAME = 'OpenKOS';

    private const DEFAULT_HOMEPAGE_TITLE = 'Find your next place';

    private const DEFAULT_HOMEPAGE_DESCRIPTION = 'Discover available properties and rental options that fit your needs.';

    public function __construct(
        private PublicPortalMetadataComposer $composer,
    ) {}

    public function homepage(string $canonical, ?string $fallbackImage = null): PublicPortalMetadataData
    {
        $settings = $this->settings();
        $siteName = $this->siteName($settings);

        return $this->composer->compose(
            title: $this->value($settings['public_homepage_title']) ?? self::DEFAULT_HOMEPAGE_TITLE,
            description: $settings['public_homepage_description'],
            fallbackDescription: self::DEFAULT_HOMEPAGE_DESCRIPTION,
            canonical: $canonical,
            siteName: $siteName,
            image: $this->image($settings, $fallbackImage),
        );
    }

    public function property(
        string $propertyName,
        ?string $description,
        string $canonical,
        ?string $fallbackImage = null,
    ): PublicPortalMetadataData {
        $settings = $this->settings();
        $siteName = $this->siteName($settings);

        return $this->composer->compose(
            title: $propertyName.' | '.$siteName,
            description: $description,
            fallbackDescription: 'Discover this property and its rental options.',
            canonical: $canonical,
            siteName: $siteName,
            image: $this->image($settings, $fallbackImage),
        );
    }

    public function unitType(
        string $unitTypeName,
        string $propertyName,
        ?string $description,
        string $canonical,
        ?string $fallbackImage = null,
    ): PublicPortalMetadataData {
        $settings = $this->settings();
        $siteName = $this->siteName($settings);

        return $this->composer->compose(
            title: $unitTypeName.' | '.$propertyName.' | '.$siteName,
            description: $description,
            fallbackDescription: 'Discover this rental option and its availability.',
            canonical: $canonical,
            siteName: $siteName,
            image: $this->image($settings, $fallbackImage),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        return Setting::some([
            'public_site_name',
            'site_name',
            'public_homepage_title',
            'public_homepage_description',
            'public_og_image_path',
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function siteName(array $settings): string
    {
        return $this->composer->siteName(
            is_string($settings['public_site_name'] ?? null) ? $settings['public_site_name'] : null,
            is_string($settings['site_name'] ?? null) ? $settings['site_name'] : null,
            self::DEFAULT_SITE_NAME,
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function image(array $settings, ?string $fallbackImage): ?string
    {
        $configuredPath = $this->value($settings['public_og_image_path'] ?? null);

        if ($configuredPath !== null) {
            try {
                $disk = Storage::disk((string) config('filesystems.default', 'local'));

                if ($disk->exists($configuredPath) && in_array($disk->mimeType($configuredPath), self::SOCIAL_IMAGE_MIMES, true)) {
                    return route('branding.asset', [
                        'asset' => 'og-image',
                        'v' => sha1($configuredPath),
                    ]);
                }
            } catch (\Throwable) {
                // Fall through to the public listing image.
            }
        }

        return $this->value($fallbackImage);
    }

    private function value(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
