<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Route khusus untuk melayani gambar avatar agar terhindar dari masalah CORS di Web
Route::get('/avatar/{filename}', function ($filename) {
    if (!Storage::disk('public')->exists('avatars/' . $filename)) abort(404);
    return response()->file(storage_path('app/public/avatars/' . $filename));
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::put('/password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('categories', CategoryController::class)->except(['show']);
    Route::apiResource('transactions', TransactionController::class)->except(['show']);

    Route::get('/dashboard', [DashboardController::class, 'summary']);
    Route::get('/reports/by-category', [ReportController::class, 'byCategory']);
    Route::get('/reports/by-month', [ReportController::class, 'byMonth']);
});
