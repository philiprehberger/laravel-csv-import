<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport;

use Illuminate\Support\ServiceProvider;

final class CsvImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/csv-import.php',
            'csv-import',
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/csv-import.php' => config_path('csv-import.php'),
            ], 'csv-import-config');
        }
    }
}
