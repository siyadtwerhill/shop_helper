<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'description', 'shop_owner_id', 'team_id', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class);
    }
}
