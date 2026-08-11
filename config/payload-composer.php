<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Max rows per query slot
    |--------------------------------------------------------------------------
    |
    | Hard cap when streaming SQL results into a payload slot. Stock audits can
    | exceed a few thousand lines, so keep this high but finite.
    |
    */
    'max_rows_per_slot' => (int) env('PAYLOAD_COMPOSER_MAX_ROWS_PER_SLOT', 100_000),
];
