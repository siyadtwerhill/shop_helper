<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);

// One login endpoint for every role — role is read from the user row,
// not guessed by the frontend across three separate endpoints.
Route::post('/login', [AuthController::class, 'login']);

Route::post('/shop-owner/register', [AuthController::class, 'shopOwnerRegister']);
Route::post('/staff/register', [AuthController::class, 'registerStaff']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::get('/users/role/{role}', [UserController::class, 'getUsersByRole']);

    Route::middleware('superadmin')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });
});
