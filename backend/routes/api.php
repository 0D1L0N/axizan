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

        // Livreur routes (mobile/portal)
        Route::prefix('livreur')->group(function () {
            Route::middleware(function ($request, $next) {
                $user = $request->user();
                if (! $user || $user->role !== 'livreur') {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
                return $next($request);
            })->group(function () {
                Route::get('/deliveries', [App\Http\Controllers\Livreur\DeliveryController::class, 'index']);
                Route::post('/deliveries/{id}/accept', [App\Http\Controllers\Livreur\DeliveryController::class, 'accept']);
                Route::post('/deliveries/{id}/location', [App\Http\Controllers\Livreur\DeliveryController::class, 'updateLocation']);
                Route::post('/deliveries/{id}/confirm', [App\Http\Controllers\Livreur\DeliveryController::class, 'confirm']);
                Route::post('/kyc', [App\Http\Controllers\Livreur\OnboardingController::class, 'submitKyc']);
            });
        });

        // Preparateur routes (à implémenter)
        Route::prefix('preparateur')->group(function () {
            Route::middleware(function ($request, $next) {
                $user = $request->user();
                if (! $user || $user->role !== 'preparateur') {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
                return $next($request);
            })->group(function () {
                Route::get('/orders', [App\Http\Controllers\Preparateur\OrderController::class, 'index']);
                Route::post('/orders/{id}/accept', [App\Http\Controllers\Preparateur\OrderController::class, 'accept']);
                Route::post('/orders/{id}/start', [App\Http\Controllers\Preparateur\OrderController::class, 'startPreparing']);
                Route::post('/orders/{id}/ready', [App\Http\Controllers\Preparateur\OrderController::class, 'markReady']);
                Route::post('/stock/adjust', [App\Http\Controllers\Preparateur\OrderController::class, 'adjustStock']);
                Route::post('/orders/{id}/substitute', [App\Http\Controllers\Preparateur\OrderItemController::class, 'substitute']);
            });
        });

        // Admin routes (Delivery zone management)
        Route::prefix('admin')->group(function () {
            Route::middleware(function ($request, $next) {
                $user = $request->user();
                if (! $user || $user->role !== 'admin') {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
                return $next($request);
            })->group(function () {
                Route::get('/delivery-zones', [App\Http\Controllers\Admin\DeliveryZoneController::class, 'index']);
                Route::post('/delivery-zones', [App\Http\Controllers\Admin\DeliveryZoneController::class, 'store']);
                Route::get('/delivery-zones/{deliveryZone}', [App\Http\Controllers\Admin\DeliveryZoneController::class, 'show']);
                Route::put('/delivery-zones/{deliveryZone}', [App\Http\Controllers\Admin\DeliveryZoneController::class, 'update']);
                Route::delete('/delivery-zones/{deliveryZone}', [App\Http\Controllers\Admin\DeliveryZoneController::class, 'destroy']);
                // Delivery slots management
                Route::get('/delivery-slots', [App\Http\Controllers\Admin\DeliverySlotController::class, 'index']);
                Route::post('/delivery-slots', [App\Http\Controllers\Admin\DeliverySlotController::class, 'store']);
                Route::get('/delivery-slots/{deliverySlot}', [App\Http\Controllers\Admin\DeliverySlotController::class, 'show']);
                Route::put('/delivery-slots/{deliverySlot}', [App\Http\Controllers\Admin\DeliverySlotController::class, 'update']);
                Route::delete('/delivery-slots/{deliverySlot}', [App\Http\Controllers\Admin\DeliverySlotController::class, 'destroy']);
                // KYC review
                Route::get('/kyc/pending', [App\Http\Controllers\Admin\KycController::class, 'index']);
                Route::post('/kyc/{id}/approve', [App\Http\Controllers\Admin\KycController::class, 'approve']);
                Route::post('/kyc/{id}/reject', [App\Http\Controllers\Admin\KycController::class, 'reject']);
            });
        });
    });
});
