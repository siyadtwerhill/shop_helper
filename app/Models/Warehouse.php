<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = ['shop_owner_id', 'name', 'code', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function shopOwner(): BelongsTo { return $this->belongsTo(ShopOwner::class); }
    public function stocks(): HasMany { return $this->hasMany(WarehouseProductStock::class); }
}
