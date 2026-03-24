<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter;

use NewSong\SermonFormatter\Console\Commands\BulkProcessCommand;
use NewSong\SermonFormatter\Console\Commands\TestClaudeCommand;
use NewSong\SermonFormatter\Fieldtypes\SermonSource;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $fieldtypes = [
        SermonSource::class,
    ];

    protected $commands = [
        TestClaudeCommand::class,
        BulkProcessCommand::class,
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $vite = [
        'input' => [
            'resources/js/addon.js',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    protected $viewNamespace = 'sermon-formatter';

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            __DIR__.'/../config/sermon-formatter.php',
            'sermon-formatter'
        );
    }

    public function bootAddon(): void
    {
        $this->registerNavigation();
        $this->registerPermissions();
        $this->registerLoggingChannel();
        $this->publishAssets();
        $this->loadMigrations();
    }

    protected function registerLoggingChannel(): void
    {
        if (! config('sermon-formatter.logging.enabled', true)) {
            return;
        }

        $this->app['config']->set('logging.channels.sermon-formatter', [
            'driver' => 'daily',
            'path' => storage_path('logs/sermon-formatter.log'),
            'level' => config('sermon-formatter.logging.level', 'info'),
            'days' => 14,
        ]);
    }

    protected function registerNavigation(): void
    {
        Nav::extend(function ($nav) {
            $nav->create('Sermon Formatter')
                ->section('Tools')
                ->can('view sermon formatter')
                ->icon('file-content-list')
                ->route('sermon-formatter.dashboard')
                ->children([
                    $nav->item('Dashboard')
                        ->route('sermon-formatter.dashboard')
                        ->can('view sermon formatter'),
                    $nav->item('Formatting Specs')
                        ->route('sermon-formatter.specs')
                        ->can('manage sermon formatter settings'),
                    $nav->item('Processing Logs')
                        ->route('sermon-formatter.logs')
                        ->can('view sermon formatter'),
                ]);
        });
    }

    protected function registerPermissions(): void
    {
        Permission::group('sermon-formatter', 'Sermon Formatter', function () {
            Permission::register('view sermon formatter')
                ->label('View Sermon Formatter');
            Permission::register('process sermons')
                ->label('Process Sermon Documents');
            Permission::register('manage sermon formatter settings')
                ->label('Manage Sermon Formatter Settings');
        });
    }

    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../config/sermon-formatter.php' => config_path('sermon-formatter.php'),
        ], 'sermon-formatter-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'sermon-formatter-migrations');

        $this->publishes([
            __DIR__.'/../resources/fieldsets' => resource_path('fieldsets/vendor/sermon-formatter'),
        ], 'sermon-formatter-fieldsets');
    }

    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
