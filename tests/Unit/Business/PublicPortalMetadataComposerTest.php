<?php

use App\Business\PublicPortal\PublicPortalMetadataComposer;

it('resolves blank names through the configured fallbacks', function () {
    $composer = new PublicPortalMetadataComposer;

    expect($composer->siteName('  ', 'Installation', 'OpenKOS'))->toBe('Installation')
        ->and($composer->siteName(null, ' ', 'OpenKOS'))->toBe('OpenKOS');
});

it('composes one consistent metadata contract for social tags', function () {
    $metadata = (new PublicPortalMetadataComposer)->compose(
        title: 'Homepage',
        description: 'Description',
        fallbackDescription: 'Fallback',
        canonical: '/',
        siteName: 'Public Name',
        image: null,
    );

    expect($metadata->toArray())->toMatchArray([
        'title' => 'Homepage',
        'description' => 'Description',
        'canonical' => '/',
        'siteName' => 'Public Name',
        'image' => null,
        'openGraph' => [
            'title' => 'Homepage',
            'description' => 'Description',
            'siteName' => 'Public Name',
            'url' => '/',
            'type' => 'website',
            'image' => null,
        ],
        'twitter' => [
            'card' => 'summary',
            'title' => 'Homepage',
            'description' => 'Description',
            'image' => null,
        ],
    ]);
});
