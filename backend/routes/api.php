<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\BorrowRequestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FixedScheduleController;
use App\Models\Organization;

// =======================
// PUBLIC ROUTES
// =======================

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// =======================
// PROTECTED ROUTES
// =======================

Route::middleware('auth:sanctum')->group(function () {

    // auth
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user()->load('organization:id,name');
    });

    // facilities
    Route::get('/facilities', [FacilityController::class, 'index']);
    Route::post('/facilities', [FacilityController::class, 'store']);
    Route::delete('/facilities/{id}', [FacilityController::class, 'destroy']);

    // borrow requests
    Route::post('/borrow-requests', [BorrowRequestController::class, 'store']);
    Route::get('/borrow-requests', [BorrowRequestController::class, 'index']);
    Route::patch('/borrow-requests/{id}/status', [BorrowRequestController::class, 'updateStatus']);

    // users
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // fixed schedules
    Route::post('/fixed-schedules', [FixedScheduleController::class, 'store']);
    Route::delete('/fixed-schedules/{id}', [FixedScheduleController::class, 'destroy']);

    // organizations
    Route::get('/organizations', function () {
        return response()->json([
            'data' => Organization::all(),
        ]);
    });

});
