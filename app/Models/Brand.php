<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ShopOwner;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = ['shop_owner_id', 'name', 'slug'];
    public function shopOwner() { return $this->belongsTo(ShopOwner::class); }
    public function products() { return $this->hasMany(Product::class); }
}
