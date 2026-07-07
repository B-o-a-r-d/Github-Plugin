<?php

namespace Board\PluginGithub;

use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\PluginServiceProvider;

/**
 * Auto-discovered by Laravel (see composer.json `extra.laravel.providers`).
 * The SDK base provider registers the plugin into the host registry and loads
 * this package's translations under the `github::` namespace.
 */
class GitHubPluginServiceProvider extends PluginServiceProvider
{
    protected function plugin(): Plugin
    {
        return new GitHubPlugin;
    }
}
