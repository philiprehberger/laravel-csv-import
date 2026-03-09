<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport\Events;

use Illuminate\Foundation\Events\Dispatchable;
use PhilipRehberger\CsvImport\ImportResult;

final class ImportCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $path,
        public readonly ImportResult $result,
        public readonly bool $isDryRun,
    ) {}
}
