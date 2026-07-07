<?php

namespace Board\PluginGithub\Tests;

use Board\PluginGithub\GitHubPluginServiceProvider;
use Board\PluginSdk\PluginRegistry;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [GitHubPluginServiceProvider::class];
    }

    /**
     * Bind the host's PluginRegistry before the package provider boots so the
     * plugin registers itself exactly as it would inside a Board host.
     */
    protected function defineEnvironment($app): void
    {
        $app->singleton(PluginRegistry::class, fn (): PluginRegistry => new PluginRegistry);
    }
}
