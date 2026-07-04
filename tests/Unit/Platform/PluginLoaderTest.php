<?php

use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginLoader;
use OpenKOS\Platform\Plugin\PluginManifest;

function loaderPlugin(string $id, array $dependencies = [], string $coreVersion = '*'): Plugin
{
    return new class($id, $dependencies, $coreVersion) extends Plugin
    {
        public function __construct(
            private string $id,
            private array $dependencies,
            private string $coreVersion,
        ) {}

        public function manifest(): PluginManifest
        {
            return new PluginManifest(
                id: $this->id,
                name: $this->id,
                version: '1.0.0',
                coreVersion: $this->coreVersion,
                dependencies: $this->dependencies,
            );
        }

        public function register(OpenKOSManager $platform): void {}
    };
}

function loaderIds(array $plugins): array
{
    return array_map(fn (Plugin $p) => $p->manifest()->id, $plugins);
}

describe('version compatibility', function () {
    it('accepts wildcard and matching caret/exact constraints', function () {
        $loader = new PluginLoader;

        expect($loader->satisfies('0.1.0', '*'))->toBeTrue()
            ->and($loader->satisfies('0.1.5', '^0.1'))->toBeTrue()
            ->and($loader->satisfies('0.1.0', '0.1.0'))->toBeTrue()
            ->and($loader->satisfies('1.4.0', '^1.2'))->toBeTrue();
    });

    it('rejects incompatible constraints', function () {
        $loader = new PluginLoader;

        expect($loader->satisfies('0.2.0', '^0.1'))->toBeFalse()   // 0.x minor is the boundary
            ->and($loader->satisfies('0.1.0', '0.2.0'))->toBeFalse()
            ->and($loader->satisfies('2.0.0', '^1.0'))->toBeFalse();
    });

    it('supports the full composer constraint grammar', function () {
        $loader = new PluginLoader;

        expect($loader->satisfies('1.3.0', '~1.2'))->toBeTrue()
            ->and($loader->satisfies('1.5.0', '>=1.0 <2.0'))->toBeTrue()
            ->and($loader->satisfies('2.1.0', '^1.0 || ^2.0'))->toBeTrue()
            ->and($loader->satisfies('1.9.9', '1.*'))->toBeTrue()
            ->and($loader->satisfies('2.0.0', '~1.2'))->toBeFalse();
    });

    it('throws when a plugin requires an incompatible core', function () {
        (new PluginLoader)->prepare([loaderPlugin('a', coreVersion: '^0.2')], '0.1.0');
    })->throws(InvalidArgumentException::class, 'requires core ^0.2');
});

describe('dependency resolution', function () {
    it('orders plugins after their dependencies', function () {
        $ordered = (new PluginLoader)->prepare([
            loaderPlugin('app', ['core']),
            loaderPlugin('core'),
        ], '0.1.0');

        expect(loaderIds($ordered))->toBe(['core', 'app']);
    });

    it('throws on a missing dependency', function () {
        (new PluginLoader)->prepare([loaderPlugin('app', ['missing'])], '0.1.0');
    })->throws(InvalidArgumentException::class, 'depends on missing plugin [missing]');

    it('throws on a circular dependency', function () {
        (new PluginLoader)->prepare([
            loaderPlugin('a', ['b']),
            loaderPlugin('b', ['a']),
        ], '0.1.0');
    })->throws(InvalidArgumentException::class, 'Circular plugin dependency');

    it('throws on a duplicate id', function () {
        (new PluginLoader)->prepare([loaderPlugin('a'), loaderPlugin('a')], '0.1.0');
    })->throws(InvalidArgumentException::class, 'Duplicate plugin id [a]');
});
