<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport;

use Generator;
use RuntimeException;
use SplFileObject;

final class CsvReader
{
    private string $delimiter;

    private string $enclosure;

    private string $escape;

    private string $encoding;

    public function __construct(
        private readonly string $path,
        private readonly int $chunkSize,
    ) {
        $this->delimiter = config('csv-import.delimiter', ',');
        $this->enclosure = config('csv-import.enclosure', '"');
        $this->escape = config('csv-import.escape', '\\');
        $this->encoding = config('csv-import.encoding', 'UTF-8');
    }

    public function withDelimiter(string $delimiter): self
    {
        $clone = clone $this;
        $clone->delimiter = $delimiter;

        return $clone;
    }

    public function withEnclosure(string $enclosure): self
    {
        $clone = clone $this;
        $clone->enclosure = $enclosure;

        return $clone;
    }

    public function withEscape(string $escape): self
    {
        $clone = clone $this;
        $clone->escape = $escape;

        return $clone;
    }

    public function withEncoding(string $encoding): self
    {
        $clone = clone $this;
        $clone->encoding = $encoding;

        return $clone;
    }

    /**
     * Yields chunks of associative arrays keyed by the CSV header row.
     *
     * Each yielded value is an array of rows, where each row is an
     * associative array mapping header name => field value.
     *
     * @return Generator<int, array<int, array<string, string>>>
     *
     * @throws RuntimeException
     */
    public function chunks(): Generator
    {
        if (! file_exists($this->path)) {
            throw new RuntimeException("CSV file not found: {$this->path}");
        }

        $file = new SplFileObject($this->path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        // Read and normalise the header row.
        $headers = $this->readHeaders($file);

        if (empty($headers)) {
            return;
        }

        $chunk = [];
        $chunkIndex = 0;

        while (! $file->eof()) {
            /** @var array<int, string>|false $fields */
            $fields = $file->fgetcsv($this->delimiter, $this->enclosure, $this->escape);

            if ($fields === false || $fields === [null]) {
                continue;
            }

            // Skip rows that are entirely empty.
            $filtered = array_filter($fields, fn ($v) => $v !== null && $v !== '');
            if (empty($filtered)) {
                continue;
            }

            $row = $this->buildRow($headers, $fields);
            $chunk[] = $row;

            if (count($chunk) >= $this->chunkSize) {
                yield $chunkIndex => $chunk;
                $chunk = [];
                $chunkIndex++;
            }
        }

        if (! empty($chunk)) {
            yield $chunkIndex => $chunk;
        }
    }

    /**
     * Return the total number of data rows (excluding the header).
     */
    public function countRows(): int
    {
        if (! file_exists($this->path)) {
            throw new RuntimeException("CSV file not found: {$this->path}");
        }

        $file = new SplFileObject($this->path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        $count = 0;
        $firstLine = true;

        while (! $file->eof()) {
            /** @var array<int, string>|false $fields */
            $fields = $file->fgetcsv($this->delimiter, $this->enclosure, $this->escape);

            if ($fields === false || $fields === [null]) {
                continue;
            }

            $filtered = array_filter($fields, fn ($v) => $v !== null && $v !== '');
            if (empty($filtered)) {
                continue;
            }

            if ($firstLine) {
                $firstLine = false;

                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Read and return the header row as a normalised array of strings.
     *
     * @return string[]
     */
    private function readHeaders(SplFileObject $file): array
    {
        while (! $file->eof()) {
            /** @var array<int, string>|false $fields */
            $fields = $file->fgetcsv($this->delimiter, $this->enclosure, $this->escape);

            if ($fields === false || $fields === [null]) {
                continue;
            }

            $filtered = array_filter($fields, fn ($v) => $v !== null && $v !== '');
            if (empty($filtered)) {
                continue;
            }

            return array_map(fn (string $h) => $this->normalise($h), $fields);
        }

        return [];
    }

    /**
     * Build an associative row from headers and fields, normalising encoding.
     *
     * @param  string[]  $headers
     * @param  array<int, string|null>  $fields
     * @return array<string, string>
     */
    private function buildRow(array $headers, array $fields): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $value = $fields[$index] ?? '';
            $row[$header] = $this->normalise((string) $value);
        }

        return $row;
    }

    /**
     * Normalise a string value: convert encoding to UTF-8 and trim whitespace.
     */
    private function normalise(string $value): string
    {
        if ($this->encoding !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $value = mb_convert_encoding($value, 'UTF-8', $this->encoding);
        }

        // Strip UTF-8 BOM if present on the very first character.
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }

        return trim($value);
    }
}
