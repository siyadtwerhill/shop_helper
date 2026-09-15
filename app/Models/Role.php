<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'description', 'shop_owner_id', 'team_id'];

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class);
    }
}
