<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\BorrowRequestController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route tanpa login
Route::post('/login', [AuthController::class, 'login']);

// Route wajib login (dibungkus middleware sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
// API
Route::get('/user', function (Request $request) {return $request->user();});

// fixed schedule
Route::get('/facilities', [FacilityController::class, 'index']);

// borrow system
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) { return $request->user(); });
    Route::get('/facilities', [FacilityController::class, 'index']);
    
// Tambahkan ini untuk menerima form pengajuan
Route::post('/borrow-requests', [BorrowRequestController::class, 'store']);
    Route::get('/borrow-requests', [BorrowRequestController::class, 'index']);
    Route::patch('/borrow-requests/{id}/status', [BorrowRequestController::class, 'updateStatus']);

// Manajemen Akun (Oleh MPK)
Route::get('/users', [App\Http\Controllers\UserController::class, 'index']);
    Route::post('/users', [App\Http\Controllers\UserController::class, 'store']);
    Route::patch('/users/{id}/reset-password', [App\Http\Controllers\UserController::class, 'resetPassword']);
    Route::delete('/users/{id}', [App\Http\Controllers\UserController::class, 'destroy']);
    Route::post('/facilities', [App\Http\Controllers\FacilityController::class, 'store']);
    Route::delete('/facilities/{id}', [App\Http\Controllers\FacilityController::class, 'destroy']);
    Route::post('/fixed-schedules', [App\Http\Controllers\FixedScheduleController::class, 'store']);
    Route::delete('/fixed-schedules/{id}', [App\Http\Controllers\FixedScheduleController::class, 'destroy']);

Route::get('/organizations', function () {
        return response()->json(['data' => \App\Models\Organization::all()]);
        });
});
});