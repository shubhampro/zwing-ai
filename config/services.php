<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'model_ai' => [
        'url' => env('MODEL_AI_URL', 'http://127.0.0.1:8000'),
        'default_dataset_key' => env('MODEL_AI_DEFAULT_DATASET_KEY', 'store_credit_notes'),
        'default_train_targets' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MODEL_AI_DEFAULT_TRAIN_TARGETS', 'is_redeemed,payment_mode,customer_name')),
        ))),
    ],

    'zwing_to_erp' => [
        'base_url' => env('ZWING_TO_ERP_BASE_URL', 'https://connect.gozwing.com'),
        'username' => env('ZWING_TO_ERP_USERNAME'),
        'password' => env('ZWING_TO_ERP_PASSWORD'),
    ],

];
