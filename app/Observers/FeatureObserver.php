<?php

namespace App\Observers;

use App\Models\Feature;
use Spatie\Permission\Models\Permission;

class FeatureObserver
{
    public function created(Feature $feature): void
    {
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::create([
                'name' => "{$feature->slug}.{$action}",
                'feature_id' => $feature->id,
                'action' => $action,
                'guard_name' => 'web',
            ]);
        }
    }

    public function deleting(Feature $feature): void
    {
        $feature->permissions()->delete();
    }
}