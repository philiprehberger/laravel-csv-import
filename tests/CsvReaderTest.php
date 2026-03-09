<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport\Tests;

use PhilipRehberger\CsvImport\CsvReader;
use RuntimeException;

final class CsvReaderTest extends TestCase
{
    public function test_reads_header_row_and_maps_data_rows(): void
    {
        $reader = new CsvReader($this->fixturePath('users.csv'), 100);

        $chunks = iterator_to_array($reader->chunks());

        $this->assertCount(1, $chunks);
        $rows = $chunks[0];
        $this->assertCount(4, $rows);

        $this->assertSame([
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john@example.com',
            'Age' => '30',
        ], $rows[0]);
    }

    public function test_yields_multiple_chunks_based_on_chunk_size(): void
    {
        $reader = new CsvReader($this->fixturePath('users.csv'), 2);

        $chunks = iterator_to_array($reader->chunks());

        $this->assertCount(2, $chunks);
        $this->assertCount(2, $chunks[0]);
        $this->assertCount(2, $chunks[1]);
    }

    public function test_reads_semicolon_delimited_file(): void
    {
        $reader = (new CsvReader($this->fixturePath('users_semicolon.csv'), 100))
            ->withDelimiter(';');

        $chunks = iterator_to_array($reader->chunks());

        $this->assertCount(1, $chunks);
        $this->assertCount(2, $chunks[0]);
        $this->assertSame('John', $chunks[0][0]['First Name']);
        $this->assertSame('john@example.com', $chunks[0][0]['Email']);
    }

    public function test_reads_tab_delimited_file(): void
    {
        $reader = (new CsvReader($this->fixturePath('users_tab.csv'), 100))
            ->withDelimiter("\t");

        $chunks = iterator_to_array($reader->chunks());

        $this->assertCount(1, $chunks);
        $this->assertCount(2, $chunks[0]);
        $this->assertSame('Jane', $chunks[0][1]['First Name']);
    }

    public function test_returns_no_chunks_for_header_only_file(): void
    {
        $reader = new CsvReader($this->fixturePath('empty.csv'), 100);

        $chunks = iterator_to_array($reader->chunks());

        $this->assertCount(0, $chunks);
    }

    public function test_counts_data_rows_excluding_header(): void
    {
        $reader = new CsvReader($this->fixturePath('users.csv'), 100);

        $this->assertSame(4, $reader->countRows());
    }

    public function test_count_rows_returns_zero_for_header_only_file(): void
    {
        $reader = new CsvReader($this->fixturePath('empty.csv'), 100);

        $this->assertSame(0, $reader->countRows());
    }

    public function test_throws_runtime_exception_for_missing_file(): void
    {
        $this->expectException(RuntimeException::class);

        $reader = new CsvReader('/non/existent/file.csv', 100);
        iterator_to_array($reader->chunks());
    }

    public function test_chunk_index_increments_per_chunk(): void
    {
        $reader = new CsvReader($this->fixturePath('users.csv'), 1);

        $indices = [];
        foreach ($reader->chunks() as $index => $chunk) {
            $indices[] = $index;
        }

        $this->assertSame([0, 1, 2, 3], $indices);
    }
}
