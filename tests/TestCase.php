<?php

namespace NewSong\SermonFormatter\Tests;

use NewSong\SermonFormatter\ServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('sermon-formatter.anthropic.api_key', 'test-key');
        $app['config']->set('sermon-formatter.anthropic.model', 'claude-sonnet-4-20250514');
    }
}
