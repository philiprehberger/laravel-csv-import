<?php

declare(strict_types=1);

namespace PhilipRehberger\CsvImport\Contracts;

interface ImportHandler
{
    /**
     * Return Laravel validation rules for a single CSV row.
     *
     * The keys should match the mapped attribute names returned by map().
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Map CSV column headers to attribute names.
     *
     * Return an associative array of CSV header => attribute name.
     * If an empty array is returned, the CSV headers are used as-is.
     *
     * Example:
     *   return ['First Name' => 'first_name', 'Email Address' => 'email'];
     *
     * @return array<string, string>
     */
    public function map(array $row): array;

    /**
     * Persist a single validated and mapped row.
     *
     * This method is only called in non-dry-run mode. Any exception thrown
     * here will be caught and recorded as a row error.
     */
    public function handle(array $row): void;

    /**
     * Return the mapped attribute name to use for duplicate detection.
     *
     * When a non-null value is returned, rows whose value for this attribute
     * already exists (as determined by the handler's own check inside handle())
     * can be skipped. Return null to disable duplicate detection.
     */
    public function uniqueBy(): ?string;
}
