<?php

namespace Jhonoryza\LaravelImportTables;

use Illuminate\Support\ServiceProvider as SupportServiceProvider;

class ServiceProvider extends SupportServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/import-tables.php',
            'import-tables'
        );
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole()) return;

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if (method_exists($this, 'publishesMigrations')) {
            $this->publishesMigrations([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'laravel-import-tables');
        }

        $this->publishes([
            __DIR__ . '/../config/import-tables.php' => config_path('import-tables.php'),
        ], 'import-tables');
    }
}

