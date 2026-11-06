<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = ['shop_owner_id', 'branch_id', 'sale_id', 'receipt_number', 'receipt_type', 'status', 'issued_at', 'issued_by', 'document_snapshot'];

    protected $casts = ['issued_at' => 'datetime', 'document_snapshot' => 'array'];

    public function sale() { return $this->belongsTo(Sale::class); }
    public function shopOwner() { return $this->belongsTo(ShopOwner::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function issuedBy() { return $this->belongsTo(User::class, 'issued_by'); }
    public function printLogs() { return $this->hasMany(ReceiptPrintLog::class); }
}