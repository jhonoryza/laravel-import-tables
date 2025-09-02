<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Jhonoryza\LaravelImportTables\ServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
    }
}
