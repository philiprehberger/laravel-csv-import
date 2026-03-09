<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PhilipRehberger\CsvImport\CsvImportServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            CsvImportServiceProvider::class,
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
    }

    protected function fixturePath(string $filename): string
    {
        return __DIR__.'/fixtures/'.$filename;
    }
}
