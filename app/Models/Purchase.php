<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = ['shop_owner_id', 'supplier_id', 'location_type', 'location_id', 'status', 'reference', 'total_amount', 'note', 'created_by', 'received_by', 'received_at'];

    protected function casts(): array { return ['total_amount' => 'decimal:2', 'received_at' => 'datetime']; }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseItem::class); }
}
