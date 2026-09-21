<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ShopOwner;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['shop_owner_id', 'name', 'slug', 'parent_id'];

    public function shopOwner() { return $this->belongsTo(ShopOwner::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function parent() { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children() { return $this->hasMany(Category::class, 'parent_id'); }
}
