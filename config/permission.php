<?php

return [
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_morph_key' => 'role_id',
        'permission_morph_key' => 'permission_id',
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    'teams' => false,

    'display_permission' => 'name',
    'display_role' => 'name',

    'cache' => [
        'expiration_time' => null,
        'key' => 'spatie.permission.cache',
        'store' => null,
    ],

    'super_admin' => [
        'name' => 'super-admin',
    ],
];