<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'price_monthly', 'max_staff', 'max_branches', 'max_products', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean'];

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class);
    }

    public function hasModule(string $slug): bool
    {
        return $this->modules()->where('slug', $slug)->exists();
    }

    public function shopOwners()
    {
        return $this->hasMany(ShopOwner::class);
    }
}
