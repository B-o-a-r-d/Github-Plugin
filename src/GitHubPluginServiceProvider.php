<?php

namespace Board\PluginGithub;

use Board\PluginSdk\PluginRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-discovered by Laravel (see composer.json `extra.laravel.providers`).
 * Its only job is to register the GitHub plugin into the host's registry, so
 * `composer require board/plugin-github` makes the Power-Up available with no
 * host code changes.
 */
class GitHubPluginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The host app binds PluginRegistry as a singleton. Guard so the package
        // stays loadable even outside a Board host (e.g. isolated package tests).
        if ($this->app->bound(PluginRegistry::class)) {
            $this->app->make(PluginRegistry::class)->register(new GitHubPlugin);
        }
    }
}
