<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\BorrowRequestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FixedScheduleController;
use Illuminate\Support\Facades\Artisan;

Route::get('/migrate-db', function () {
    Artisan::call('migrate --force');

    return Artisan::output();
});
Route::get('/seed-db', function () {
    Artisan::call('db:seed --force');

    return Artisan::output();
});
// =======================
// PUBLIC ROUTES
// =======================

Route::post('/login', [AuthController::class, 'login']);

Route::get('/test', function () {
    return response()->json([
        'message' => 'API OK'
    ]);
});

Route::get('/dns-test', function () {
    return gethostbyname('ep-rapid-bar-aq1ugwbd.c-8.us-east-1.aws.neon.tech');
});

// =======================
// PROTECTED ROUTES
// =======================

Route::middleware('auth:sanctum')->group(function () {

    // auth
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
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
            'data' => \App\Models\Organization::all()
        ]);
    });

});