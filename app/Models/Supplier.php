<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = ['shop_owner_id', 'name', 'contact_person', 'phone', 'email', 'address', 'tax_id', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function shopOwner(): BelongsTo { return $this->belongsTo(ShopOwner::class); }
    public function purchases(): HasMany { return $this->hasMany(Purchase::class); }
}
