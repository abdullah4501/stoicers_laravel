<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;




Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('customer')->group(function () {
    Route::post('/register', [CustomerController::class, 'register']);
    Route::post('/login', [CustomerController::class, 'login']);

    Route::middleware('auth:customer-api')->group(function () {  // Use customer-api guard
        Route::get('/me', [CustomerController::class, 'me']);
        Route::post('/logout', [CustomerController::class, 'logout']);
    });
});

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// Protected write endpoints (choose the guard you want)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
});

Route::post('/orders', [OrderController::class, 'store'])/*->middleware('throttle:30,1')*/;

// Only logged-in customers can READ/UPDATE/DELETE their orders
Route::middleware('auth:customer-api')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->whereNumber('order');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->whereNumber('order');
});