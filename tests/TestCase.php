<?php

namespace Tests;

use App\Models\Permission;
use App\Models\ShopOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
            throw new \RuntimeException('Refusing to run tests against a non-test database.');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    protected function grant(\App\Models\User $user, \App\Models\ShopOwner $shop, string ...$names): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($shop->id);

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $user->givePermissionTo($names);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $registrar->forgetCachedPermissions();
    }
}
