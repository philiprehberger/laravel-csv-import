<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use PhilipRehberger\CsvImport\Contracts\ImportHandler;
use PhilipRehberger\CsvImport\CsvReader;
use PhilipRehberger\CsvImport\Events\ImportChunkProcessed;
use PhilipRehberger\CsvImport\Events\ImportCompleted;
use PhilipRehberger\CsvImport\Events\ImportStarted;
use PhilipRehberger\CsvImport\ImportResult;
use PhilipRehberger\CsvImport\RowError;

final class ImportCsvJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $path,
        public readonly string $handlerClass,
        public readonly int $chunkSize,
        public readonly string $delimiter,
        public readonly string $enclosure,
        public readonly string $escape,
        public readonly string $encoding,
    ) {}

    public function handle(): void
    {
        /** @var ImportHandler $handler */
        $handler = new $this->handlerClass;

        $reader = (new CsvReader($this->path, $this->chunkSize))
            ->withDelimiter($this->delimiter)
            ->withEnclosure($this->enclosure)
            ->withEscape($this->escape)
            ->withEncoding($this->encoding);

        $result = new ImportResult;
        $totalRows = $reader->countRows();
        $result->totalRows = $totalRows;

        Event::dispatch(new ImportStarted($this->path, $totalRows, false));

        $seenUniqueValues = [];
        $uniqueColumn = $handler->uniqueBy();
        $lineNumber = 1;

        foreach ($reader->chunks() as $chunkIndex => $chunk) {
            $chunkSuccess = 0;
            $chunkErrors = 0;
            $chunkSkipped = 0;

            foreach ($chunk as $rawRow) {
                $lineNumber++;

                $mappedRow = $this->applyMapping($handler, $rawRow);

                if ($uniqueColumn !== null && isset($mappedRow[$uniqueColumn])) {
                    $uniqueValue = $mappedRow[$uniqueColumn];

                    if (in_array($uniqueValue, $seenUniqueValues, strict: true)) {
                        $result->skippedCount++;
                        $chunkSkipped++;

                        continue;
                    }

                    $seenUniqueValues[] = $uniqueValue;
                }

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

        Event::dispatch(new ImportCompleted($this->path, $result, false));
    }

    /**
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
}
