<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| API v1 routes
*/

Route::prefix('v1')->group(function () {
    
    // Routes publiques (pas d'authentification requise)
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/categories', [ProductController::class, 'categories']);

    // Routes protégées (authentification requise)
    Route::middleware('auth:sanctum')->group(function () {
        
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);

        // Orders (Client)
        Route::prefix('orders')->group(function () {
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/{id}', [OrderController::class, 'show']);
            Route::delete('/{id}', [OrderController::class, 'cancel']);
            Route::patch('/{id}/status', [OrderController::class, 'updateStatus']);
        });

        // Payments
        Route::prefix('payments')->group(function () {
            Route::post('/initiate', [PaymentController::class, 'initiate']);
            Route::get('/{id}/status', [PaymentController::class, 'checkStatus']);
            Route::get('/history', [PaymentController::class, 'history']);
        });

        // Delivery (Livreur)
        Route::prefix('deliveries')->group(function () {
            Route::get('/ready-orders', [DeliveryController::class, 'listReadyOrders']);
            Route::post('/{orderId}/accept', [DeliveryController::class, 'accept']);
            Route::post('/{id}/location', [DeliveryController::class, 'updateLocation']);
            Route::patch('/{id}/start', [DeliveryController::class, 'startDelivery']);
            Route::post('/{id}/confirm', [DeliveryController::class, 'confirm']);
            Route::get('/stats', [DeliveryController::class, 'stats']);
        });

        // Preparateur routes (à implémenter)
        // Route::prefix('preparateur')->group(function () {
        //     Route::get('/orders', [PreparatorController::class, 'list']);
        //     Route::patch('/orders/{id}/accept', [PreparatorController::class, 'accept']);
        //     Route::patch('/orders/{id}/ready', [PreparatorController::class, 'markReady']);
        // });

        // Admin routes (à implémenter)
        // Route::middleware('admin')->prefix('admin')->group(function () {
        //     Route::get('/dashboard', [AdminController::class, 'dashboard']);
        //     Route::resource('/products', ProductController::class);
        //     Route::resource('/menus', MenuController::class);
        // });
    });
});
