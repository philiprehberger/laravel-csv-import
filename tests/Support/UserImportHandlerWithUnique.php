<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport\Tests\Support;

use PhilipRehberger\CsvImport\Contracts\ImportHandler;

/**
 * Like UserImportHandler but with duplicate detection enabled on the email column.
 */
final class UserImportHandlerWithUnique implements ImportHandler
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
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'age' => ['required', 'integer', 'min:0'],
        ];
    }

    public function map(array $row): array
    {
        return [
            'First Name' => 'first_name',
            'Last Name' => 'last_name',
            'Email' => 'email',
            'Age' => 'age',
        ];
    }

    public function handle(array $row): void
    {
        self::$persisted[] = $row;
    }

    public function uniqueBy(): ?string
    {
        return 'email';
    }
}
