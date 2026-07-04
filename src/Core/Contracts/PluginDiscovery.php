<?php

namespace OpenKOS\Core\Contracts;

use OpenKOS\Platform\Plugin\Plugin;

/**
 * Seam for future plugin discovery (e.g. composer package scanning).
 * For now plugins are listed explicitly in config/platform.php.
 */
interface PluginDiscovery
{
    /**
     * @return array<class-string<Plugin>>
     */
    public function discover(): array;
}
