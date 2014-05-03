<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport;

use Illuminate\Support\MessageBag;

final class RowError
{
    public function __construct(
        public readonly int $lineNumber,
        public readonly array $data,
        public readonly MessageBag $errors,
    ) {}

    /**
     * Return a plain array representation of this error.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'line_number' => $this->lineNumber,
            'data' => $this->data,
            'errors' => $this->errors->toArray(),
        ];
    }
}
