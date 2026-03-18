# Laravel CSV Import

[![Tests](https://github.com/philiprehberger/laravel-csv-import/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/laravel-csv-import/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/laravel-csv-import.svg)](https://packagist.org/packages/philiprehberger/laravel-csv-import)
[![License](https://img.shields.io/github/license/philiprehberger/laravel-csv-import)](LICENSE)

Chunked CSV import with row-level validation, error collection, dry-run mode, and queue support.

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require philiprehberger/laravel-csv-import
```

The service provider is registered automatically via Laravel's package auto-discovery.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=csv-import-config
```

## Usage

### 1. Create an Import Handler

Implement `PhilipRehberger\CsvImport\Contracts\ImportHandler`:

```php
namespace App\Imports;

use App\Models\User;
use PhilipRehberger\CsvImport\Contracts\ImportHandler;

class UserImportHandler implements ImportHandler
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255'],
        ];
    }

    public function map(array $row): array
    {
        return [
            'First Name' => 'first_name',
            'Last Name'  => 'last_name',
            'Email'      => 'email',
        ];
    }

    public function handle(array $row): void
    {
        User::create($row);
    }

    public function uniqueBy(): ?string
    {
        return 'email';
    }
}
```

### 2. Run the Import

```php
use PhilipRehberger\CsvImport\CsvImporter;

$result = CsvImporter::make('/path/to/users.csv')
    ->using(UserImportHandler::class)
    ->chunkSize(500)
    ->import();

echo "Imported: {$result->successCount}";
echo "Errors:   {$result->errorCount}";

if ($result->hasErrors()) {
    foreach ($result->getErrors() as $error) {
        echo "Line {$error->lineNumber}: " . implode(', ', $error->errors->all());
    }
}
```

### Dry-Run Mode

```php
$result = CsvImporter::make('/path/to/users.csv')
    ->using(UserImportHandler::class)
    ->dryRun();
```

### Queue Support

```php
CsvImporter::make('/path/to/users.csv')
    ->using(UserImportHandler::class)
    ->chunkSize(1000)
    ->importQueued();
```

### Importing an Uploaded File

```php
$result = CsvImporter::fromUpload($request->file('csv'))
    ->using(UserImportHandler::class)
    ->import();
```

### Changing the Delimiter

```php
CsvImporter::make($path)
    ->using(MyHandler::class)
    ->delimiter(';')
    ->import();
```

## API

### CsvImporter (Fluent Builder)

| Method | Description |
|--------|-------------|
| `CsvImporter::make(string $path)` | Create an importer from a file path |
| `CsvImporter::fromUpload(UploadedFile $file)` | Create an importer from an uploaded file |
| `->using(string $handlerClass)` | Set the import handler class |
| `->chunkSize(int $size)` | Set chunk size (default: config value) |
| `->delimiter(string $delimiter)` | Set CSV delimiter |
| `->enclosure(string $enclosure)` | Set CSV enclosure character |
| `->import()` | Run import synchronously |
| `->dryRun()` | Validate all rows without persisting |
| `->importQueued()` | Dispatch import as a background job |

### ImportHandler Interface

| Method | Description |
|--------|-------------|
| `rules(): array` | Laravel validation rules for each mapped row |
| `map(array $row): array` | Map CSV headers to attribute names |
| `handle(array $row): void` | Persist a single validated row |
| `uniqueBy(): ?string` | Attribute name for duplicate detection, or `null` to disable |

### ImportResult

| Property / Method | Type | Description |
|-------------------|------|-------------|
| `$totalRows` | `int` | Total data rows in the file |
| `$successCount` | `int` | Rows successfully handled |
| `$errorCount` | `int` | Rows that failed validation or handling |
| `$skippedCount` | `int` | Rows skipped due to duplicate detection |
| `hasErrors()` | `bool` | True if errorCount > 0 |
| `getErrors()` | `RowError[]` | All collected row errors |
| `toArray()` | `array` | Serialisable summary |

### Events

| Event | Payload |
|-------|---------|
| `ImportStarted` | `$path`, `$totalRows`, `$isDryRun` |
| `ImportChunkProcessed` | `$path`, `$chunkIndex`, `$rowsInChunk`, `$successCount`, `$errorCount`, `$skippedCount` |
| `ImportCompleted` | `$path`, `$result` (ImportResult), `$isDryRun` |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## License

MIT
