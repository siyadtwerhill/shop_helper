<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptPrintLog extends Model
{
    protected $fillable = ['shop_owner_id', 'receipt_id', 'printed_by', 'action', 'printed_at'];
    protected $casts = ['printed_at' => 'datetime'];
    public function receipt() { return $this->belongsTo(Receipt::class); }
    public function printedBy() { return $this->belongsTo(User::class, 'printed_by'); }
}