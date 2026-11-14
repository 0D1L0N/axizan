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
use App\Http\Controllers\Api\FedapayController;
use App\Http\Controllers\Api\SupplierSettlementController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminClientController;
use App\Http\Controllers\Api\AdminStaffController;
use App\Http\Controllers\Api\AdminSlotController;

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

    // Notification serveur a serveur de Fedapay. Publique par nature : il n'y
    // a pas d'utilisateur connecte, l'authenticite vient de la signature.
    Route::post('/webhooks/fedapay', [FedapayController::class, 'webhook']);
    Route::get('/delivery-zones', [LogisticsController::class, 'zones']);
    Route::get('/delivery-slots', [LogisticsController::class, 'slots']);

    // Routes protégées (authentification requise)
    // `active` accompagne systematiquement l'authentification : un compte
    // suspendu garde un jeton valide jusqu'a trente jours, et continuerait de
    // commander si le controle ne se faisait qu'a la connexion.
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        
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
            // Le client accepte ou refuse un remplacement, tant que le panier
            // n'a pas quitté le marché.
            Route::post('/{id}/items/{itemId}/substitution', [OrderController::class, 'respondToSubstitution']);
            Route::get('/{id}/invoice', [OrderController::class, 'invoice']);
            Route::post('/{id}/reorder', [OrderController::class, 'reorder']);
            Route::post('/{id}/declare-payment', [OrderController::class, 'declarePayment']);
            Route::post('/{id}/confirm-payment', [FedapayController::class, 'confirm']);

            // Chaque demande fait SONNER un telephone, et rien n'oblige le
            // numero saisi a etre celui du client. Sans limite, la route
            // devient un outil de harcelement gratuit.
            Route::post('/{id}/request-payment', [FedapayController::class, 'request'])
                ->middleware('throttle:5,1');

            // Sondage pendant que le client confirme sur son telephone :
            // frequent par nature, mais pas illimite.
            Route::get('/{id}/payment-status', [FedapayController::class, 'status'])
                ->middleware('throttle:60,1');
            Route::patch('/{id}/status', [OrderController::class, 'updateStatus']);
        });

        // Payments
        Route::prefix('payments')->group(function () {
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
            Route::patch('/orders/{id}/release', [PreparatorController::class, 'release']);
            Route::get('/suppliers', [PreparatorController::class, 'suppliers']);
            Route::patch('/orders/{orderId}/items/{itemId}', [PreparatorController::class, 'updateItem']);
            Route::patch('/orders/{id}/ready', [PreparatorController::class, 'ready']);
            // POST + _method=PATCH : un envoi multipart ne passe pas en PATCH.
            Route::post('/orders/{id}/ready', [PreparatorController::class, 'ready']);
        });

        // Admin
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/markets', [AdminController::class, 'markets']);
            Route::get('/payments/pending', [AdminController::class, 'pendingPayments']);
            Route::post('/orders/{id}/validate-payment', [AdminController::class, 'validatePayment']);
            Route::post('/staff', [AdminController::class, 'createStaff']);

            // Clients : la fiche qu'on ouvre quand quelqu'un appelle, et les
            // commandes restées impayées après leur créneau.
            Route::get('/clients', [AdminClientController::class, 'index']);
            Route::get('/clients/{id}', [AdminClientController::class, 'show']);
            Route::patch('/clients/{id}/status', [AdminClientController::class, 'setStatus']);

            // Le personnel : qui travaille, ce qu'il tient en main, et le
            // moyen de couper l'accès de quelqu'un qui est parti.
            Route::get('/personnel', [AdminStaffController::class, 'index']);
            Route::get('/personnel/{id}', [AdminStaffController::class, 'show']);
            Route::patch('/personnel/{id}/status', [AdminStaffController::class, 'setStatus']);

            // Les créneaux : la capacité, les zones, et les jours fermés.
            // Rien ici ne touche aux commandes déjà posées.
            Route::get('/creneaux', [AdminSlotController::class, 'index']);
            Route::patch('/creneaux/{id}', [AdminSlotController::class, 'update']);
            Route::patch('/creneaux/zones/{id}', [AdminSlotController::class, 'setZoneActive']);
            Route::post('/creneaux/fermetures', [AdminSlotController::class, 'close']);
            Route::delete('/creneaux/fermetures/{id}', [AdminSlotController::class, 'reopen']);

            // Catalogue : retirer un article introuvable, corriger un prix,
            // poser une photo. Rien ne se supprime — les factures pointent dessus.
            Route::get('/catalogue', [AdminProductController::class, 'index']);
            Route::get('/catalogue/categories', [AdminProductController::class, 'categories']);
            Route::get('/catalogue/banque', [AdminProductController::class, 'library']);
            Route::post('/catalogue', [AdminProductController::class, 'store']);
            Route::patch('/catalogue/{id}', [AdminProductController::class, 'update']);
            Route::patch('/catalogue/{id}/availability', [AdminProductController::class, 'toggleAvailability']);
            Route::patch('/catalogue/{id}/archive', [AdminProductController::class, 'archive']);
            Route::post('/catalogue/{id}/photo', [AdminProductController::class, 'uploadPhoto']);
            Route::post('/catalogue/{id}/photo-banque', [AdminProductController::class, 'assignFromLibrary']);
            Route::delete('/catalogue/{id}/photo', [AdminProductController::class, 'removePhoto']);

            // Salle de contrôle : ce qui tourne, et surtout ce qui ne bouge pas.
            Route::get('/orders', [AdminOrderController::class, 'index']);
            Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
            Route::get('/orders/{id}/candidates', [AdminOrderController::class, 'candidates']);
            Route::post('/orders/{id}/release', [AdminOrderController::class, 'release']);
            Route::post('/orders/{id}/reassign', [AdminOrderController::class, 'reassign']);
            Route::post('/orders/{id}/cancel', [AdminOrderController::class, 'cancel']);
            // L'issue quand une course ne revient pas : ni libérable, ni
            // annulable par les voies ordinaires.
            Route::post('/orders/{id}/abandon-delivery', [AdminOrderController::class, 'abandonDelivery']);

            // Le point fournisseur : ce qu'Axizán doit à chaque étal.
            Route::get('/suppliers', [SupplierSettlementController::class, 'index']);
            Route::get('/suppliers/{id}/statement', [SupplierSettlementController::class, 'statement']);
            Route::post('/suppliers/{id}/settle', [SupplierSettlementController::class, 'settle']);
            Route::get('/suppliers/{id}/prices', [SupplierSettlementController::class, 'prices']);
            Route::put('/suppliers/{id}/prices', [SupplierSettlementController::class, 'updatePrices']);
        });
    });
});
