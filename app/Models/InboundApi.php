<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class InboundApi extends Model
{
    protected $connection = 'mongodb_ssh';

    public $timestamps = false;

    public function getTable(): string
    {
        return (string) config('inbound_sync.collection', 'inbound_apis');
    }
}
