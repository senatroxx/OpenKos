<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Platform\BuildInfo;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AboutController extends Controller
{
    private const PRODUCT_NAME = 'OpenKOS';

    private const REPOSITORY_URL = 'https://github.com/senatroxx/OpenKos';

    private const LICENSE_NAME = 'Apache License 2.0';

    private const COPYRIGHT = 'OpenKOS contributors';

    public function __construct(
        private BuildInfo $buildInfo,
    ) {}

    public function edit(): InertiaResponse
    {
        return Inertia::render('settings/about', [
            'build' => $this->buildInfo->toArray(),
            'product' => [
                'name' => self::PRODUCT_NAME,
                'repositoryUrl' => self::REPOSITORY_URL,
                'licenseName' => self::LICENSE_NAME,
                'copyright' => self::COPYRIGHT,
                'logoUrl' => '/assets/brand/openkos-logo.svg',
            ],
        ]);
    }

    public function license(): Response
    {
        $licensePath = base_path('LICENSE');

        abort_unless(is_file($licensePath), 404);

        $license = file_get_contents($licensePath);

        abort_if($license === false, 404);

        return response($license, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
