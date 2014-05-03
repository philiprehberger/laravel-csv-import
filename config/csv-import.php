<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Chunk Size
    |--------------------------------------------------------------------------
    |
    | The number of rows processed per chunk. Larger chunks are faster but
    | use more memory. Smaller chunks are more memory-efficient for large files.
    |
    */

    'chunk_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | CSV Delimiter
    |--------------------------------------------------------------------------
    |
    | The character used to separate fields. Common values are ',' (comma),
    | ';' (semicolon), and "\t" (tab).
    |
    */

    'delimiter' => ',',

    /*
    |--------------------------------------------------------------------------
    | CSV Enclosure
    |--------------------------------------------------------------------------
    |
    | The character used to enclose fields that contain the delimiter or
    | special characters.
    |
    */

    'enclosure' => '"',

    /*
    |--------------------------------------------------------------------------
    | CSV Escape Character
    |--------------------------------------------------------------------------
    |
    | The character used to escape the enclosure character within a field.
    |
    */

    'escape' => '\\',

    /*
    |--------------------------------------------------------------------------
    | File Encoding
    |--------------------------------------------------------------------------
    |
    | The expected encoding of the CSV file. The package will attempt to
    | convert the file to UTF-8 if it is in a different encoding.
    |
    */

    'encoding' => 'UTF-8',

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for queued imports. Set 'connection' to null to use the
    | default queue connection defined in your queue config.
    |
    */

    'queue' => [
        'connection' => null,
        'queue' => 'default',
    ],

];
