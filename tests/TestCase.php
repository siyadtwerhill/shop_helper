<?php

namespace Tests;

use App\Models\Permission;
use App\Models\ShopOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        if (! app()->environment('testing')
            || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'shophelper_test'
            || env('SHOPHELPER_ALLOW_DESTRUCTIVE_TEST_SETUP') !== 'yes') {
            throw new \RuntimeException(
                'Refusing RefreshDatabase: use APP_ENV=testing and the resolved mysql/shophelper_test database, '
                .'and explicitly set SHOPHELPER_ALLOW_DESTRUCTIVE_TEST_SETUP=yes.'
            );
        }

        $connection = DB::connection('mysql');
        $actualDatabase = $connection->selectOne('SELECT DATABASE() AS database_name')->database_name ?? null;
        if ($connection->getDriverName() !== 'mysql' || $actualDatabase !== 'shophelper_test') {
            throw new \RuntimeException('Refusing RefreshDatabase: the resolved MySQL database is not shophelper_test.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
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
