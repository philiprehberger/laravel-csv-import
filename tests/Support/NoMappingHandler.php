<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport\Tests\Support;

use PhilipRehberger\CsvImport\Contracts\ImportHandler;

/**
 * A handler that does NOT provide a column mapping, so raw CSV headers are used.
 */
final class NoMappingHandler implements ImportHandler
{
    /** @var array<int, array<string, string>> */
    public static array $persisted = [];

    public static function reset(): void
    {
        self::$persisted = [];
    }

    public function rules(): array
    {
        return [
            'First Name' => ['required', 'string'],
            'Email' => ['required', 'email'],
        ];
    }

    public function map(array $row): array
    {
        // Return empty array to signal "no mapping; use raw headers".
        return [];
    }

    public function handle(array $row): void
    {
        self::$persisted[] = $row;
    }

    public function uniqueBy(): ?string
    {
        return null;
    }
}
