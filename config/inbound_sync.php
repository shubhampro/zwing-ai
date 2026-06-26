<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MongoDB collection for inbound API sync logs
    |--------------------------------------------------------------------------
    */

    'collection' => env('INBOUND_SYNC_COLLECTION', 'inbound_apis'),

    'connection' => 'mongodb_ssh',

    /*
    |--------------------------------------------------------------------------
    | Document field mapping (inbound_apis collection)
    |--------------------------------------------------------------------------
    */

    'fields' => [
        'vendor_id' => 'v_id',
        'client_id' => 'client_id',
        'event_name' => 'client_event_name',
        'event_unique_code' => 'client_event_unique_code',
        'document_id' => '_id',
        'status' => 'status',
        'status_fallback' => 'xstatus',
        'created_at' => 'created_at',
        'event_time' => 'client_event_time',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status value mapping (case-insensitive via aggregation $in)
    |--------------------------------------------------------------------------
    */

    'status' => [
        'success' => ['success', 'SUCCESS', 'synced', 'SYNCED', 'completed', 'COMPLETED'],
        'failed' => ['failed', 'FAILED', 'fail', 'FAIL', 'error', 'ERROR'],
        'pending' => ['pending', 'PENDING', 'processing', 'PROCESSING', 'queued', 'QUEUED', 'in_progress'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum document IDs returned per event bucket
    |--------------------------------------------------------------------------
    */

    'id_list_limit' => 500,

];
