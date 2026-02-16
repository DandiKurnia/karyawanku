<?php

use App\Http\Controllers\Api\Admin\LeaveEntitlementsController;
use App\Http\Controllers\Api\Admin\LeaveRequestsController as AdminLeaveRequestsController;
use App\Http\Controllers\Api\Employee\LeaveRequestsController as EmployeeLeaveRequestsController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public Routes

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);
Route::post('/auth/{provider}/token', [SocialAuthController::class, 'loginWithToken']);

// Authenticated Routes

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);

    // Employee
    Route::middleware('role:employee')->group(function () {
        Route::apiResource('leave-requests', EmployeeLeaveRequestsController::class)
            ->only(['index', 'store', 'show']);
        Route::patch('leave-requests/{leave_request}/cancel', [EmployeeLeaveRequestsController::class, 'cancel']);
        Route::get('my-leave-quota', [EmployeeLeaveRequestsController::class, 'myQuota']);
    });

    // Admin
    Route::middleware('role:admin')->prefix('admin')->as('admin.')->group(function () {
        Route::apiResource('leave-entitlements', LeaveEntitlementsController::class);
        Route::apiResource('leave-requests', AdminLeaveRequestsController::class)
            ->only(['index', 'show']);
        Route::patch('leave-requests/{leave_request}/decide', [AdminLeaveRequestsController::class, 'decide'])
            ->name('leave-requests.decide');
    });
});
