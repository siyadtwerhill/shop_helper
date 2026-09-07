<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'ShopPilot API Backend',
        'status' => 'operational',
        'sanctum' => 'installed'
    ]);
});
