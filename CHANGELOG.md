# Changelog

All notable changes to `laravel-csv-import` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2026-03-16

### Changed
- Standardize composer.json: add homepage, scripts
- Add Development section to README

## [1.0.0] - 2026-03-09

### Added

- `CsvImporter` fluent entry point with `make()`, `fromUpload()`, `using()`, `chunkSize()`, `delimiter()`, `enclosure()`, `escape()`, `encoding()`, `import()`, `dryRun()`, and `importQueued()` methods.
- `ImportHandler` contract with `rules()`, `map()`, `handle()`, and `uniqueBy()` methods.
- `CsvReader` — memory-efficient chunked reader backed by `SplFileObject`. Supports configurable delimiter, enclosure, escape, and encoding.
- `ImportResult` value object: `$totalRows`, `$successCount`, `$errorCount`, `$skippedCount`, `hasErrors()`, `getErrors()`, `toArray()`.
- `RowError` value object: `$lineNumber`, `$data`, `$errors` (MessageBag).
- `ImportCsvJob` for queued imports.
- `ImportStarted`, `ImportChunkProcessed`, and `ImportCompleted` events.
- `CsvImportServiceProvider` with config publishing.
- Full test suite with Orchestra Testbench and SQLite.
- GitHub Actions workflow for PHP 8.2 / 8.3 / 8.4 against Laravel 11 and 12.
- PHPStan level 8 configuration.
- Laravel Pint code-style configuration.
