<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport;

final class ImportResult
{
    /** @var RowError[] */
    private array $errors = [];

    public int $totalRows = 0;

    public int $successCount = 0;

    public int $errorCount = 0;

    public int $skippedCount = 0;

    public function addError(RowError $error): void
    {
        $this->errors[] = $error;
        $this->errorCount++;
    }

    /**
     * @return RowError[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'skipped_count' => $this->skippedCount,
            'errors' => array_map(
                fn (RowError $e) => $e->toArray(),
                $this->errors,
            ),
        ];
    }
}
