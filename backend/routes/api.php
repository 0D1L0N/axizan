<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\LogisticsController;
use App\Http\Controllers\Api\PreparatorController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\RecurringBasketController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Admin\DeliverySlotController as AdminDeliverySlotController;
use App\Http\Controllers\Admin\DeliveryZoneController as AdminDeliveryZoneController;
use App\Http\Controllers\Admin\KycController as AdminKycController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| API v1 routes
*/

Route::prefix('v1')->group(function () {
    
    // Routes publiques (pas d'authentification requise)
    //
    // L'inscription et la connexion sont bridees : sans limite, un mot de
    // passe faible tombe par force brute sans aucun obstacle. 10 tentatives
    // par minute laissent largement la place aux fautes de frappe.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    // Mot de passe oublie. Bride : ces routes acceptent une adresse arbitraire
    // et declenchent un envoi de mail — sans limite, c'est un relais de spam.
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/categories', [ProductController::class, 'categories']);
    Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
    Route::get('/delivery-zones', [LogisticsController::class, 'zones']);
    Route::get('/delivery-slots', [LogisticsController::class, 'slots']);

    // Routes protégées (authentification requise)
    Route::middleware('auth:sanctum')->group(function () {
        
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        // Panier serveur : suit le client d'un appareil a l'autre.
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/', [CartController::class, 'store']);
            Route::post('/merge', [CartController::class, 'merge']);
            Route::delete('/', [CartController::class, 'clear']);
            Route::delete('/{productId}', [CartController::class, 'destroy']);
        });

        // Paniers recurrents
        Route::apiResource('recurring-baskets', RecurringBasketController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::post('/recurring-baskets/{recurringBasket}/load', [RecurringBasketController::class, 'load']);

        // Notifications in-app
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
            Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead']);
        });

        // Orders (Client)
        Route::apiResource('addresses', AddressController::class)->except(['create', 'edit']);
        Route::prefix('orders')->group(function () {
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/{id}', [OrderController::class, 'show']);
            Route::delete('/{id}', [OrderController::class, 'cancel']);
            Route::post('/{id}/reorder', [OrderController::class, 'reorder']);
            Route::post('/{id}/declare-payment', [OrderController::class, 'declarePayment']);
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
            Route::get('/mine', [DeliveryController::class, 'mine']);
            Route::post('/{orderId}/accept', [DeliveryController::class, 'accept']);
            Route::post('/{id}/location', [DeliveryController::class, 'updateLocation']);
            Route::patch('/{id}/start', [DeliveryController::class, 'startDelivery']);
            Route::post('/{id}/confirm', [DeliveryController::class, 'confirm']);
            Route::get('/stats', [DeliveryController::class, 'stats']);
        });

        // Préparateur
        Route::prefix('preparateur')->group(function () {
            Route::get('/orders', [PreparatorController::class, 'index']);
            Route::get('/orders/mine', [PreparatorController::class, 'mine']);
            Route::patch('/orders/{id}/accept', [PreparatorController::class, 'accept']);
            Route::patch('/orders/{id}/start', [PreparatorController::class, 'start']);
            Route::get('/suppliers', [PreparatorController::class, 'suppliers']);
            Route::patch('/orders/{orderId}/items/{itemId}', [PreparatorController::class, 'updateItem']);
            Route::patch('/orders/{id}/ready', [PreparatorController::class, 'ready']);
        });

        // Admin
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/payments/pending', [AdminController::class, 'pendingPayments']);
            Route::post('/orders/{id}/validate-payment', [AdminController::class, 'validatePayment']);
            Route::post('/staff', [AdminController::class, 'createStaff']);

            // Zones et creneaux de livraison
            Route::apiResource('delivery-zones', AdminDeliveryZoneController::class);
            Route::apiResource('delivery-slots', AdminDeliverySlotController::class);

            // Verification d'identite des livreurs
            Route::get('/kyc/pending', [AdminKycController::class, 'index']);
            Route::get('/kyc/{id}/document', [AdminKycController::class, 'document']);
            Route::post('/kyc/{id}/approve', [AdminKycController::class, 'approve']);
            Route::post('/kyc/{id}/reject', [AdminKycController::class, 'reject']);
        });

        // Verification d'identite (livreur)
        Route::get('/livreur/kyc', [KycController::class, 'show']);
        Route::post('/livreur/kyc', [KycController::class, 'submit']);
    });
});
