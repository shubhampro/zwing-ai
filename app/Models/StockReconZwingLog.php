<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_recon_session_id',
    'v_id',
    'site_code',
    'icode',
    'batch_no',
    'sprefcode',
    'doc_no',
    'enttype',
    'qty',
])]
class StockReconZwingLog extends Model
{
    public function session(): BelongsTo
    {
        return $this->belongsTo(StockReconSession::class, 'stock_recon_session_id');
    }
}
