<?php

namespace App\Data\PublicPortal;

final readonly class PublicPortalMetadataData
{
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $siteName,
        public ?string $image,
        /** @var array{title: string, description: string, url: string, type: string, image: ?string} */
        public array $openGraph,
        /** @var array{card: string, title: string, description: string, image: ?string} */
        public array $twitter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'siteName' => $this->siteName,
            'image' => $this->image,
            'openGraph' => $this->openGraph,
            'twitter' => $this->twitter,
        ];
    }
}
