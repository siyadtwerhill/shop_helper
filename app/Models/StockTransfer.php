<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = ['shop_owner_id', 'source_type', 'source_id', 'destination_type', 'destination_id', 'status', 'note', 'created_by', 'dispatched_by', 'received_by', 'dispatched_at', 'received_at'];

    protected function casts(): array { return ['dispatched_at' => 'datetime', 'received_at' => 'datetime']; }
    public function items(): HasMany { return $this->hasMany(StockTransferItem::class); }
}
