<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vision Provider
    |--------------------------------------------------------------------------
    |
    | Supported: "openai", "anthropic", "gemini"
    |
    */

    'provider' => env('TEMPLATE_VISION_PROVIDER', 'openai'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
        'max_tokens' => (int) env('OPENAI_VISION_MAX_TOKENS', 16384),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_VISION_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => (int) env('ANTHROPIC_VISION_MAX_TOKENS', 16384),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_VISION_MODEL', 'gemini-2.5-flash'),
        'max_tokens' => (int) env('GEMINI_VISION_MAX_TOKENS', 16384),
    ],

    'timeout' => (int) env('TEMPLATE_VISION_TIMEOUT', 180),

    'max_upload_mb' => (int) env('TEMPLATE_VISION_MAX_UPLOAD_MB', 10),

];
