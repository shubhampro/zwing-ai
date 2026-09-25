<?php

return [

    'cli' => env('SALESFORCE_CLI', 'sf'),

    'org' => env('SALESFORCE_ORG', 'ginesys'),

    'query_timeout' => (int) env('SALESFORCE_QUERY_TIMEOUT', 180),

];
