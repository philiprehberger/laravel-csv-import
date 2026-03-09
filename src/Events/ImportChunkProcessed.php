<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class ImportChunkProcessed
{
    use Dispatchable;

    public function __construct(
        public readonly string $path,
        public readonly int $chunkIndex,
        public readonly int $rowsInChunk,
        public readonly int $successCount,
        public readonly int $errorCount,
        public readonly int $skippedCount,
    ) {}
}
