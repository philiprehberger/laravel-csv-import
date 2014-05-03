<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class ImportStarted
{
    use Dispatchable;

    public function __construct(
        public readonly string $path,
        public readonly int $totalRows,
        public readonly bool $isDryRun,
    ) {}
}
