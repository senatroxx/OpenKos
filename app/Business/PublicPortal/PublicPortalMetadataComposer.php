<?php

namespace App\Business\PublicPortal;

use App\Data\PublicPortal\PublicPortalMetadataData;

final class PublicPortalMetadataComposer
{
    public function siteName(?string $publicSiteName, ?string $siteName, string $default): string
    {
        return $this->valueOr($publicSiteName, $this->valueOr($siteName, $default));
    }

    public function compose(
        string $title,
        ?string $description,
        string $fallbackDescription,
        string $canonical,
        string $siteName,
        ?string $image,
    ): PublicPortalMetadataData {
        $resolvedDescription = $this->valueOr($description, $fallbackDescription);

        return new PublicPortalMetadataData(
            title: $title,
            description: $resolvedDescription,
            canonical: $canonical,
            siteName: $siteName,
            image: $image,
            openGraph: [
                'title' => $title,
                'description' => $resolvedDescription,
                'url' => $canonical,
                'type' => 'website',
                'image' => $image,
            ],
            twitter: [
                'card' => $image === null ? 'summary' : 'summary_large_image',
                'title' => $title,
                'description' => $resolvedDescription,
                'image' => $image,
            ],
        );
    }

    private function valueOr(?string $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value === '' ? $fallback : $value;
    }
}
