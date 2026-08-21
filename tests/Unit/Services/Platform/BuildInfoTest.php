<?php

use App\Services\Platform\BuildInfo;
use Tests\TestCase;

uses(TestCase::class);

test('configured build metadata is exposed without resolving git metadata', function () {
    config([
        'app.build.version' => 'v0.2.0-alpha.1',
        'app.build.channel' => 'prerelease',
        'app.build.commit_sha' => 'abc123456789',
    ]);

    expect((new BuildInfo)->toArray())->toBe([
        'version' => '0.2.0-alpha.1',
        'channel' => 'prerelease',
        'commitSha' => 'abc123456789',
    ]);
});
