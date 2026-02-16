<?php

use App\Http\Controllers\Api\Admin\LeaveEntitlementsController;
use App\Http\Controllers\Api\LeaveRequestsController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

// OAuth routes
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);
Route::post('/auth/{provider}/token', [SocialAuthController::class, 'loginWithToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);

    Route::apiResource('leave-requests', LeaveRequestsController::class)->only(['index', 'store', 'show']);
    Route::patch('leave-requests/{leave_request}/cancel', [LeaveRequestsController::class, 'cancel']);
});

// Admin Routes
Route::middleware('auth:sanctum', 'role:admin')->group(function () {
    Route::apiResource('leave-entitlements', LeaveEntitlementsController::class);

    Route::patch('leave-requests/{leave_request}/decide', [LeaveRequestsController::class, 'decide']);
});
