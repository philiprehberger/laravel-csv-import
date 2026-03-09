<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use InvalidArgumentException;
use PhilipRehberger\CsvImport\Contracts\ImportHandler;
use PhilipRehberger\CsvImport\Events\ImportChunkProcessed;
use PhilipRehberger\CsvImport\Events\ImportCompleted;
use PhilipRehberger\CsvImport\Events\ImportStarted;
use PhilipRehberger\CsvImport\Jobs\ImportCsvJob;
use RuntimeException;

final class CsvImporter
{
    private string $path;

    private string $handlerClass;

    private int $chunkSize;

    private string $delimiter;

    private string $enclosure;

    private string $escape;

    private string $encoding;

    private function __construct(string $path)
    {
        $this->path = $path;
        $this->chunkSize = (int) config('csv-import.chunk_size', 1000);
        $this->delimiter = (string) config('csv-import.delimiter', ',');
        $this->enclosure = (string) config('csv-import.enclosure', '"');
        $this->escape = (string) config('csv-import.escape', '\\');
        $this->encoding = (string) config('csv-import.encoding', 'UTF-8');
    }

    /**
     * Create an importer from a file path on disk.
     */
    public static function make(string $path): self
    {
        if (! file_exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        return new self($path);
    }

    /**
     * Create an importer from an uploaded file.
     *
     * The file is stored in a temporary location that persists for the
     * duration of the request (or job).
     */
    public static function fromUpload(UploadedFile $file): self
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('Unable to resolve real path of uploaded file.');
        }

        return new self($path);
    }

    /**
     * Set the import handler class.
     *
     * The class must implement ImportHandler.
     */
    public function using(string $handlerClass): self
    {
        if (! class_exists($handlerClass)) {
            throw new InvalidArgumentException("Handler class not found: {$handlerClass}");
        }

        if (! is_a($handlerClass, ImportHandler::class, true)) {
            throw new InvalidArgumentException(
                "{$handlerClass} must implement ".ImportHandler::class,
            );
        }

        $clone = clone $this;
        $clone->handlerClass = $handlerClass;

        return $clone;
    }

    /**
     * Set the number of rows processed per chunk.
     */
    public function chunkSize(int $size): self
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1.');
        }

        $clone = clone $this;
        $clone->chunkSize = $size;

        return $clone;
    }

    /**
     * Set the CSV field delimiter character.
     */
    public function delimiter(string $delimiter): self
    {
        $clone = clone $this;
        $clone->delimiter = $delimiter;

        return $clone;
    }

    /**
     * Set the CSV field enclosure character.
     */
    public function enclosure(string $enclosure): self
    {
        $clone = clone $this;
        $clone->enclosure = $enclosure;

        return $clone;
    }

    /**
     * Set the CSV escape character.
     */
    public function escape(string $escape): self
    {
        $clone = clone $this;
        $clone->escape = $escape;

        return $clone;
    }

    /**
     * Set the expected file encoding.
     */
    public function encoding(string $encoding): self
    {
        $clone = clone $this;
        $clone->encoding = $encoding;

        return $clone;
    }

    /**
     * Run the import synchronously, persisting valid rows.
     */
    public function import(): ImportResult
    {
        return $this->run(dryRun: false);
    }

    /**
     * Run in dry-run mode: validate every row but do not persist anything.
     */
    public function dryRun(): ImportResult
    {
        return $this->run(dryRun: true);
    }

    /**
     * Dispatch a queued job to run the import in the background.
     */
    public function importQueued(): void
    {
        $this->assertHandlerSet();

        $job = new ImportCsvJob(
            path: $this->path,
            handlerClass: $this->handlerClass,
            chunkSize: $this->chunkSize,
            delimiter: $this->delimiter,
            enclosure: $this->enclosure,
            escape: $this->escape,
            encoding: $this->encoding,
        );

        $connection = config('csv-import.queue.connection');
        $queue = config('csv-import.queue.queue', 'default');

        dispatch($job)->onConnection($connection)->onQueue($queue);
    }

    /**
     * Internal import runner used by both import() and dryRun().
     */
    private function run(bool $dryRun): ImportResult
    {
        $this->assertHandlerSet();

        /** @var ImportHandler $handler */
        $handler = new $this->handlerClass;

        $reader = $this->buildReader();
        $result = new ImportResult;

        $totalRows = $reader->countRows();
        $result->totalRows = $totalRows;

        Event::dispatch(new ImportStarted($this->path, $totalRows, $dryRun));

        // Track unique values seen in this import run for duplicate detection.
        $seenUniqueValues = [];
        $uniqueColumn = $handler->uniqueBy();

        // Line number starts at 2 (line 1 is the header).
        $lineNumber = 1;

        foreach ($reader->chunks() as $chunkIndex => $chunk) {
            $chunkSuccess = 0;
            $chunkErrors = 0;
            $chunkSkipped = 0;

            foreach ($chunk as $rawRow) {
                $lineNumber++;

                // Apply column mapping.
                $mappedRow = $this->applyMapping($handler, $rawRow);

                // Duplicate detection.
                if ($uniqueColumn !== null && isset($mappedRow[$uniqueColumn])) {
                    $uniqueValue = $mappedRow[$uniqueColumn];

                    if (in_array($uniqueValue, $seenUniqueValues, strict: true)) {
                        $result->skippedCount++;
                        $chunkSkipped++;

                        continue;
                    }

                    $seenUniqueValues[] = $uniqueValue;
                }

                // Validate the mapped row.
                $validator = Validator::make($mappedRow, $handler->rules());

                if ($validator->fails()) {
                    $result->addError(new RowError(
                        lineNumber: $lineNumber,
                        data: $rawRow,
                        errors: $validator->errors(),
                    ));
                    $chunkErrors++;

                    continue;
                }

                // Persist (skipped in dry-run mode).
                if (! $dryRun) {
                    try {
                        $handler->handle($mappedRow);
                    } catch (\Throwable $e) {
                        $bag = new MessageBag;
                        $bag->add('handler', $e->getMessage());

                        $result->addError(new RowError(
                            lineNumber: $lineNumber,
                            data: $rawRow,
                            errors: $bag,
                        ));
                        $chunkErrors++;

                        continue;
                    }
                }

                $result->successCount++;
                $chunkSuccess++;
            }

            Event::dispatch(new ImportChunkProcessed(
                path: $this->path,
                chunkIndex: $chunkIndex,
                rowsInChunk: count($chunk),
                successCount: $chunkSuccess,
                errorCount: $chunkErrors,
                skippedCount: $chunkSkipped,
            ));
        }

        Event::dispatch(new ImportCompleted($this->path, $result, $dryRun));

        return $result;
    }

    /**
     * Apply the handler's column mapping to a raw CSV row.
     *
     * The handler's map() receives the raw row keyed by CSV headers and must
     * return a header => attribute mapping. If the mapping is empty the raw
     * row is returned unchanged.
     *
     * @param  array<string, string>  $rawRow
     * @return array<string, string>
     */
    private function applyMapping(ImportHandler $handler, array $rawRow): array
    {
        $mapping = $handler->map($rawRow);

        if (empty($mapping)) {
            return $rawRow;
        }

        $mapped = [];

        foreach ($mapping as $csvHeader => $attribute) {
            if (array_key_exists($csvHeader, $rawRow)) {
                $mapped[$attribute] = $rawRow[$csvHeader];
            }
        }

        return $mapped;
    }

    private function buildReader(): CsvReader
    {
        return (new CsvReader($this->path, $this->chunkSize))
            ->withDelimiter($this->delimiter)
            ->withEnclosure($this->enclosure)
            ->withEscape($this->escape)
            ->withEncoding($this->encoding);
    }

    private function assertHandlerSet(): void
    {
        if (! isset($this->handlerClass)) {
            throw new RuntimeException(
                'No import handler set. Call ->using(YourHandler::class) before importing.',
            );
        }
    }
}
