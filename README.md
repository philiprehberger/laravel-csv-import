# laravel-csv-import

[![Tests](https://github.com/philiprehberger/laravel-csv-import/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/laravel-csv-import/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/laravel-csv-import.svg)](https://packagist.org/packages/philiprehberger/laravel-csv-import)
[![PHP Version Require](https://img.shields.io/packagist/php-v/philiprehberger/laravel-csv-import.svg)](https://packagist.org/packages/philiprehberger/laravel-csv-import)
[![License](https://img.shields.io/github/license/philiprehberger/laravel-csv-import.svg)](LICENSE)

Chunked CSV import for Laravel with row-level validation, error collection, dry-run mode, and queue support.

## Features

- Memory-efficient chunked reading via `SplFileObject`
- Row-level validation using Laravel's built-in Validator
- Detailed error collection with line numbers and `MessageBag` errors
- Dry-run mode to validate without persisting
- Column mapping (CSV headers to model attributes)
- Duplicate row detection via configurable unique column
- Queue support via `importQueued()`
- Events fired on start, chunk completion, and finish
- Configurable delimiter, enclosure, escape character, and encoding
- Zero dependencies beyond the Laravel framework components

## Requirements

- PHP ^8.2
- Laravel ^11.0 or ^12.0

## Installation

Install via Composer:

```bash
composer require philiprehberger/laravel-csv-import
```

The service provider is registered automatically via Laravel's package auto-discovery.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=csv-import-config
```

## Quick Start

### 1. Create an Import Handler

Implement `PhilipRehberger\CsvImport\Contracts\ImportHandler`:

```php
<?php

namespace App\Imports;

use App\Models\User;
use PhilipRehberger\CsvImport\Contracts\ImportHandler;

class UserImportHandler implements ImportHandler
{
    /**
     * Laravel validation rules applied to each mapped row.
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255'],
            'age'        => ['nullable', 'integer', 'min:0', 'max:150'],
        ];
    }

    /**
     * Map CSV column headers to attribute names.
     *
     * Keys are the exact CSV header strings; values are the attribute names
     * that your rules() and handle() methods use.
     */
    public function map(array $row): array
    {
        return [
            'First Name' => 'first_name',
            'Last Name'  => 'last_name',
            'Email'      => 'email',
            'Age'        => 'age',
        ];
    }

    /**
     * Persist a single validated, mapped row.
     *
     * Only called in non-dry-run mode. Any exception thrown here is caught
     * and recorded as a row error.
     */
    public function handle(array $row): void
    {
        User::create($row);
    }

    /**
     * Return the attribute name to use for in-memory duplicate detection,
     * or null to disable it.
     */
    public function uniqueBy(): ?string
    {
        return 'email';
    }
}
```

### 2. Run the Import

```php
use App\Imports\UserImportHandler;
use PhilipRehberger\CsvImport\CsvImporter;

$result = CsvImporter::make('/path/to/users.csv')
    ->using(UserImportHandler::class)
    ->chunkSize(500)
    ->import();

echo "Imported: {$result->successCount}";
echo "Skipped:  {$result->skippedCount}";
echo "Errors:   {$result->errorCount}";

if ($result->hasErrors()) {
    foreach ($result->getErrors() as $error) {
        echo "Line {$error->lineNumber}: " . implode(', ', $error->errors->all());
    }
}
```

### Importing an Uploaded File

```php
use PhilipRehberger\CsvImport\CsvImporter;

public function store(Request $request): RedirectResponse
{
    $result = CsvImporter::fromUpload($request->file('csv'))
        ->using(UserImportHandler::class)
        ->import();

    return redirect()->back()->with('result', $result->toArray());
}
```

### Dry-Run Mode

Validates every row and collects errors without persisting anything:

```php
$result = CsvImporter::make('/path/to/users.csv')
    ->using(UserImportHandler::class)
    ->dryRun();

if ($result->hasErrors()) {
    // show errors to the user before they commit to the real import
}
```

### Queue Support

Dispatch the import as a background job:

```php
CsvImporter::make('/path/to/users.csv')
    ->using(UserImportHandler::class)
    ->chunkSize(1000)
    ->importQueued();
```

The job fires the same events as the synchronous import. Listen for
`ImportCompleted` to react when the job finishes.

### Changing the Delimiter

```php
// Semicolon-separated
CsvImporter::make($path)
    ->using(MyHandler::class)
    ->delimiter(';')
    ->import();

// Tab-separated
CsvImporter::make($path)
    ->using(MyHandler::class)
    ->delimiter("\t")
    ->import();
```

## Events

| Event | Payload |
|-------|---------|
| `ImportStarted` | `$path`, `$totalRows`, `$isDryRun` |
| `ImportChunkProcessed` | `$path`, `$chunkIndex`, `$rowsInChunk`, `$successCount`, `$errorCount`, `$skippedCount` |
| `ImportCompleted` | `$path`, `$result` (ImportResult), `$isDryRun` |

```php
use PhilipRehberger\CsvImport\Events\ImportCompleted;

Event::listen(ImportCompleted::class, function (ImportCompleted $event) {
    Log::info('Import finished', $event->result->toArray());
});
```

## Configuration

After publishing, edit `config/csv-import.php`:

```php
return [
    'chunk_size' => 1000,
    'delimiter'  => ',',
    'enclosure'  => '"',
    'escape'     => '\\',
    'encoding'   => 'UTF-8',
    'queue'      => [
        'connection' => null,   // null = default connection
        'queue'      => 'default',
    ],
];
```

All config values can be overridden at call time using the fluent API:

```php
CsvImporter::make($path)
    ->using(MyHandler::class)
    ->delimiter(';')
    ->enclosure("'")
    ->chunkSize(250)
    ->import();
```

## ImportResult Reference

| Property / Method | Type | Description |
|-------------------|------|-------------|
| `$totalRows` | `int` | Total data rows in the file |
| `$successCount` | `int` | Rows successfully handled |
| `$errorCount` | `int` | Rows that failed validation or handling |
| `$skippedCount` | `int` | Rows skipped due to duplicate detection |
| `hasErrors()` | `bool` | True if errorCount > 0 |
| `getErrors()` | `RowError[]` | All collected row errors |
| `toArray()` | `array` | Serialisable summary |

Each `RowError` exposes:

| Property | Type | Description |
|----------|------|-------------|
| `$lineNumber` | `int` | 1-based line number in the file (header = 1) |
| `$data` | `array` | The raw CSV row (before mapping) |
| `$errors` | `MessageBag` | Laravel validation messages |

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT. See [LICENSE](LICENSE).
