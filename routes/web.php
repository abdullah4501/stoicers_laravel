<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Public API routes (no CSRF)
Route::middleware('api')->prefix('api')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected API routes
Route::middleware(['api', 'auth:sanctum'])->prefix('api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Keep your web page routes
Route::get('/', function () {
    return view('welcome');
});
