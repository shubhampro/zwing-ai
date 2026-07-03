<?php

return [
    'request_delay_ms' => (int) env('THIRD_PARTY_REQUEST_DELAY_MS', 250),

    'http_timeout_seconds' => (int) env('THIRD_PARTY_HTTP_TIMEOUT_SECONDS', 30),

    'response_body_limit' => 2000,
];
