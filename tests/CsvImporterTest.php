<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport\Tests;

use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use PhilipRehberger\CsvImport\CsvImporter;
use PhilipRehberger\CsvImport\Events\ImportChunkProcessed;
use PhilipRehberger\CsvImport\Events\ImportCompleted;
use PhilipRehberger\CsvImport\Events\ImportStarted;
use PhilipRehberger\CsvImport\RowError;
use PhilipRehberger\CsvImport\Tests\Support\NoMappingHandler;
use PhilipRehberger\CsvImport\Tests\Support\UserImportHandler;
use PhilipRehberger\CsvImport\Tests\Support\UserImportHandlerWithUnique;
use RuntimeException;

final class CsvImporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        UserImportHandler::reset();
        UserImportHandlerWithUnique::reset();
        NoMappingHandler::reset();
    }

    // -----------------------------------------------------------------------
    // Happy path — synchronous import
    // -----------------------------------------------------------------------

    public function test_successful_import_persists_all_valid_rows(): void
    {
        $result = CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->import();

        $this->assertFalse($result->hasErrors());
        $this->assertSame(4, $result->successCount);
        $this->assertSame(4, $result->totalRows);
        $this->assertSame(0, $result->errorCount);
        $this->assertSame(0, $result->skippedCount);
        $this->assertCount(4, UserImportHandler::$persisted);
    }

    public function test_import_maps_columns_to_attributes(): void
    {
        CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->import();

        $first = UserImportHandler::$persisted[0];
        $this->assertArrayHasKey('first_name', $first);
        $this->assertArrayHasKey('email', $first);
        $this->assertArrayNotHasKey('First Name', $first);
        $this->assertSame('John', $first['first_name']);
        $this->assertSame('john@example.com', $first['email']);
    }

    // -----------------------------------------------------------------------
    // Validation / error collection
    // -----------------------------------------------------------------------

    public function test_validation_errors_are_collected_with_correct_line_numbers(): void
    {
        $result = CsvImporter::make($this->fixturePath('users_invalid.csv'))
            ->using(UserImportHandler::class)
            ->import();

        $this->assertTrue($result->hasErrors());

        // Line 3 (Jane row — missing last name and invalid email and non-integer age)
        // Line 4 (Bob row — missing first name and negative age)
        $this->assertSame(2, $result->errorCount);

        $errors = $result->getErrors();
        $lineNumbers = array_map(fn ($e) => $e->lineNumber, $errors);
        sort($lineNumbers);
        $this->assertSame([3, 4], $lineNumbers);
    }

    public function test_valid_rows_are_still_imported_when_some_rows_fail(): void
    {
        $result = CsvImporter::make($this->fixturePath('users_invalid.csv'))
            ->using(UserImportHandler::class)
            ->import();

        // users_invalid.csv has 4 data rows; 2 valid, 2 invalid.
        $this->assertSame(2, $result->successCount);
        $this->assertSame(2, $result->errorCount);
        $this->assertCount(2, UserImportHandler::$persisted);
    }

    public function test_row_error_contains_data_and_message_bag(): void
    {
        $result = CsvImporter::make($this->fixturePath('users_invalid.csv'))
            ->using(UserImportHandler::class)
            ->import();

        $error = $result->getErrors()[0];
        $this->assertInstanceOf(RowError::class, $error);
        $this->assertFalse($error->errors->isEmpty());
        $this->assertIsArray($error->data);
    }

    // -----------------------------------------------------------------------
    // Dry-run mode
    // -----------------------------------------------------------------------

    public function test_dry_run_does_not_persist_any_rows(): void
    {
        $result = CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->dryRun();

        $this->assertFalse($result->hasErrors());
        $this->assertSame(4, $result->successCount);
        $this->assertCount(0, UserImportHandler::$persisted);
    }

    public function test_dry_run_still_collects_validation_errors(): void
    {
        $result = CsvImporter::make($this->fixturePath('users_invalid.csv'))
            ->using(UserImportHandler::class)
            ->dryRun();

        $this->assertTrue($result->hasErrors());
        $this->assertSame(2, $result->errorCount);
        $this->assertCount(0, UserImportHandler::$persisted);
    }

    // -----------------------------------------------------------------------
    // Chunked processing
    // -----------------------------------------------------------------------

    public function test_chunked_processing_imports_all_rows(): void
    {
        $result = CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->chunkSize(2)
            ->import();

        $this->assertSame(4, $result->successCount);
        $this->assertCount(4, UserImportHandler::$persisted);
    }

    public function test_chunk_size_one_processes_each_row_separately(): void
    {
        Event::fake();

        CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->chunkSize(1)
            ->import();

        Event::assertDispatched(ImportChunkProcessed::class, 4);
    }

    // -----------------------------------------------------------------------
    // Column mapping
    // -----------------------------------------------------------------------

    public function test_no_mapping_handler_uses_raw_csv_headers(): void
    {
        $result = CsvImporter::make($this->fixturePath('users.csv'))
            ->using(NoMappingHandler::class)
            ->import();

        $this->assertFalse($result->hasErrors());
        $first = NoMappingHandler::$persisted[0];
        $this->assertArrayHasKey('First Name', $first);
        $this->assertArrayHasKey('Email', $first);
    }

    // -----------------------------------------------------------------------
    // Delimiter support
    // -----------------------------------------------------------------------

    public function test_semicolon_delimiter_imports_correctly(): void
    {
        $result = CsvImporter::make($this->fixturePath('users_semicolon.csv'))
            ->using(UserImportHandler::class)
            ->delimiter(';')
            ->import();

        $this->assertFalse($result->hasErrors());
        $this->assertSame(2, $result->successCount);
        $this->assertCount(2, UserImportHandler::$persisted);
    }

    public function test_tab_delimiter_imports_correctly(): void
    {
        $result = CsvImporter::make($this->fixturePath('users_tab.csv'))
            ->using(UserImportHandler::class)
            ->delimiter("\t")
            ->import();

        $this->assertFalse($result->hasErrors());
        $this->assertSame(2, $result->successCount);
    }

    // -----------------------------------------------------------------------
    // Empty file handling
    // -----------------------------------------------------------------------

    public function test_empty_file_returns_zero_counts(): void
    {
        $result = CsvImporter::make($this->fixturePath('empty.csv'))
            ->using(UserImportHandler::class)
            ->import();

        $this->assertSame(0, $result->totalRows);
        $this->assertSame(0, $result->successCount);
        $this->assertSame(0, $result->errorCount);
        $this->assertFalse($result->hasErrors());
    }

    // -----------------------------------------------------------------------
    // Duplicate detection
    // -----------------------------------------------------------------------

    public function test_duplicate_rows_are_skipped_when_unique_by_is_set(): void
    {
        $result = CsvImporter::make($this->fixturePath('users_duplicates.csv'))
            ->using(UserImportHandlerWithUnique::class)
            ->import();

        // 4 rows total; john@example.com appears twice — the second should be skipped.
        $this->assertSame(3, $result->successCount);
        $this->assertSame(1, $result->skippedCount);
        $this->assertSame(0, $result->errorCount);
        $this->assertCount(3, UserImportHandlerWithUnique::$persisted);
    }

    // -----------------------------------------------------------------------
    // Events
    // -----------------------------------------------------------------------

    public function test_import_fires_started_and_completed_events(): void
    {
        Event::fake();

        CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->import();

        Event::assertDispatched(ImportStarted::class, function (ImportStarted $event) {
            return $event->totalRows === 4 && $event->isDryRun === false;
        });

        Event::assertDispatched(ImportCompleted::class, function (ImportCompleted $event) {
            return $event->isDryRun === false && $event->result->successCount === 4;
        });
    }

    public function test_dry_run_fires_events_with_is_dry_run_true(): void
    {
        Event::fake();

        CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->dryRun();

        Event::assertDispatched(ImportStarted::class, fn (ImportStarted $e) => $e->isDryRun === true);
        Event::assertDispatched(ImportCompleted::class, fn (ImportCompleted $e) => $e->isDryRun === true);
    }

    public function test_chunk_processed_event_is_fired_once_per_chunk(): void
    {
        Event::fake();

        CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->chunkSize(4)
            ->import();

        Event::assertDispatched(ImportChunkProcessed::class, 1);
    }

    // -----------------------------------------------------------------------
    // Chunk complete callback
    // -----------------------------------------------------------------------

    public function test_on_chunk_complete_callback_receives_correct_counts(): void
    {
        $calls = [];

        $result = CsvImporter::make($this->fixturePath('users_invalid.csv'))
            ->using(UserImportHandler::class)
            ->chunkSize(4)
            ->onChunkComplete(function (int $chunkIndex, int $processedRows, int $successCount, int $errorCount) use (&$calls) {
                $calls[] = compact('chunkIndex', 'processedRows', 'successCount', 'errorCount');
            })
            ->import();

        $this->assertCount(1, $calls);
        $this->assertSame(0, $calls[0]['chunkIndex']);
        $this->assertSame(4, $calls[0]['processedRows']);
        $this->assertSame(2, $calls[0]['successCount']);
        $this->assertSame(2, $calls[0]['errorCount']);
    }

    public function test_on_chunk_complete_callback_fires_once_per_chunk(): void
    {
        $callCount = 0;

        CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->chunkSize(2)
            ->onChunkComplete(function () use (&$callCount) {
                $callCount++;
            })
            ->import();

        $this->assertSame(2, $callCount);
    }

    // -----------------------------------------------------------------------
    // Column transforms
    // -----------------------------------------------------------------------

    public function test_transform_column_applies_transform_before_validation(): void
    {
        $result = CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->transformColumn('first_name', fn (string $value) => strtoupper($value))
            ->transformColumn('email', fn (string $value) => strtolower($value))
            ->import();

        $this->assertFalse($result->hasErrors());
        $this->assertSame(4, $result->successCount);
        $this->assertSame('JOHN', UserImportHandler::$persisted[0]['first_name']);
        $this->assertSame('john@example.com', UserImportHandler::$persisted[0]['email']);
        $this->assertSame('ALICE', UserImportHandler::$persisted[3]['first_name']);
    }

    public function test_transform_column_ignores_missing_columns(): void
    {
        $result = CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->transformColumn('nonexistent', fn (string $value) => strtoupper($value))
            ->import();

        $this->assertFalse($result->hasErrors());
        $this->assertSame(4, $result->successCount);
    }

    // -----------------------------------------------------------------------
    // Guard clauses and fluent API
    // -----------------------------------------------------------------------

    public function test_throws_when_no_handler_is_set(): void
    {
        $this->expectException(RuntimeException::class);

        CsvImporter::make($this->fixturePath('users.csv'))->import();
    }

    public function test_throws_when_handler_class_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CsvImporter::make($this->fixturePath('users.csv'))
            ->using('App\\NonExistentHandler');
    }

    public function test_throws_when_handler_does_not_implement_interface(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CsvImporter::make($this->fixturePath('users.csv'))
            ->using(\stdClass::class);
    }

    public function test_throws_when_chunk_size_is_less_than_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CsvImporter::make($this->fixturePath('users.csv'))->chunkSize(0);
    }

    public function test_throws_for_missing_file(): void
    {
        $this->expectException(RuntimeException::class);

        CsvImporter::make('/non/existent/file.csv');
    }

    // -----------------------------------------------------------------------
    // ImportResult value object
    // -----------------------------------------------------------------------

    public function test_import_result_to_array_contains_expected_keys(): void
    {
        $result = CsvImporter::make($this->fixturePath('users.csv'))
            ->using(UserImportHandler::class)
            ->import();

        $array = $result->toArray();

        $this->assertArrayHasKey('total_rows', $array);
        $this->assertArrayHasKey('success_count', $array);
        $this->assertArrayHasKey('error_count', $array);
        $this->assertArrayHasKey('skipped_count', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertSame([], $array['errors']);
    }

    public function test_import_result_errors_array_contains_row_error_data(): void
    {
        $result = CsvImporter::make($this->fixturePath('users_invalid.csv'))
            ->using(UserImportHandler::class)
            ->import();

        $array = $result->toArray();

        $this->assertCount(2, $array['errors']);
        $this->assertArrayHasKey('line_number', $array['errors'][0]);
        $this->assertArrayHasKey('data', $array['errors'][0]);
        $this->assertArrayHasKey('errors', $array['errors'][0]);
    }
}
