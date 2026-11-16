<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CustomerAppController;
use App\Http\Controllers\Api\V1\LogisticsController;
use App\Http\Controllers\Api\V1\RiderController;
use App\Http\Controllers\Api\V1\SellerAppController;
use App\Http\Controllers\Api\V1\SellerController;
use App\Http\Controllers\Api\V1\ShoppingController;
use App\Http\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;

/*
| Versioned REST API for the customer, seller and rider mobile apps (and the logistics endpoints).
| Prefixed with /api by bootstrap/app.php. Authentication uses Sanctum bearer tokens.
| See API_DOCUMENTATION.md for every endpoint, per app.
*/
Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    // ------------------------------------------------------------------ public
    Route::get('config', [AccountController::class, 'config'])->name('config');
    Route::get('home', [CustomerAppController::class, 'home'])->name('home');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('products', [CatalogController::class, 'products'])->name('products.index');
    Route::get('products/{slug}', [CatalogController::class, 'product'])->name('products.show');
    Route::get('shops', [CatalogController::class, 'shops'])->name('shops.index');
    Route::get('shops/{slug}', [CatalogController::class, 'shop'])->name('shops.show');

    // ------------------------------------------------------------------ authentication (all apps)
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->name('password.forgot');
            Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');
        });

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('confirm-password', [AuthController::class, 'confirmPassword'])->middleware('throttle:6,1')->name('confirm-password');
        });
    });

    // ------------------------------------------------------------------ account, inbox, chat, devices (all apps)
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('account', [AccountController::class, 'show'])->name('account.show');
        Route::post('account', [AccountController::class, 'update'])->middleware('throttle:20,1')->name('account.update');
        Route::put('account/password', [AccountController::class, 'password'])->middleware('throttle:6,1')->name('account.password');
        Route::get('account/deletion', [AccountController::class, 'deletionCheck'])->name('account.deletion');
        Route::delete('account', [AccountController::class, 'destroy'])->middleware('throttle:3,1')->name('account.destroy');

        Route::post('devices', [AccountController::class, 'registerDevice'])->middleware('throttle:20,1')->name('devices.store');
        Route::delete('devices', [AccountController::class, 'unregisterDevice'])->name('devices.destroy');

        Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationsController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationsController::class, 'markRead'])->name('notifications.read');

        // Customer ↔ shop chat (customer app: scope=customer, seller app: scope=shop).
        Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
        Route::post('chats', [ChatController::class, 'start'])->middleware(['role:customer', 'throttle:20,1'])->name('chats.start');
        Route::get('chats/{conversation}', [ChatController::class, 'show'])->name('chats.show');
        Route::post('chats/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:30,1')->name('chats.send');
        Route::post('chats/{conversation}/read', [ChatController::class, 'markRead'])->name('chats.read');
    });

    // ------------------------------------------------------------------ customer app
    Route::middleware(['auth:sanctum', 'active', 'role:customer'])->group(function () {
        Route::get('customer/profile', [ShoppingController::class, 'profile'])->name('customer.profile');
        Route::put('customer/profile', [ShoppingController::class, 'updateProfile'])->name('customer.profile.update');
        Route::get('customer/addresses', [ShoppingController::class, 'addresses'])->name('customer.addresses.index');
        Route::post('customer/addresses', [ShoppingController::class, 'storeAddress'])->name('customer.addresses.store');
        Route::put('customer/addresses/{address}', [ShoppingController::class, 'updateAddress'])->name('customer.addresses.update');
        Route::delete('customer/addresses/{address}', [ShoppingController::class, 'destroyAddress'])->name('customer.addresses.destroy');
        Route::get('customer/verification', [AccountController::class, 'verification'])->name('customer.verification');
        Route::post('customer/verification', [AccountController::class, 'uploadVerification'])->middleware('throttle:10,1')->name('customer.verification.store');
        Route::get('customer/seller-application', [AccountController::class, 'sellerApplication'])->name('customer.seller-application');
        Route::post('customer/seller-application', [AccountController::class, 'applyToSell'])->middleware('throttle:5,1')->name('customer.seller-application.store');

        Route::get('cart', [ShoppingController::class, 'cart'])->name('cart.show');
        Route::post('cart/items', [ShoppingController::class, 'addToCart'])->name('cart.items.store');
        Route::patch('cart/items/{item}', [ShoppingController::class, 'updateCartItem'])->name('cart.items.update');
        Route::delete('cart/items/{item}', [ShoppingController::class, 'removeCartItem'])->name('cart.items.destroy');

        Route::post('orders', [ShoppingController::class, 'placeOrder'])->middleware('throttle:10,1')->name('orders.store');
        Route::get('orders', [ShoppingController::class, 'orders'])->name('orders.index');
        Route::get('orders/{order}', [ShoppingController::class, 'order'])->name('orders.show');
        Route::post('orders/{order}/cancel', [ShoppingController::class, 'cancelOrder'])->name('orders.cancel');
        Route::post('orders/{order}/confirm-received', [ShoppingController::class, 'confirmOrder'])->name('orders.confirm');
        Route::post('orders/{order}/refund', [ShoppingController::class, 'requestRefund'])->middleware('throttle:10,1')->name('orders.refund');
        Route::get('refunds', [ShoppingController::class, 'refunds'])->name('refunds.index');
        Route::get('reviews', [CustomerAppController::class, 'myReviews'])->name('reviews.index');
        Route::get('reviews/pending', [CustomerAppController::class, 'pendingReviews'])->name('reviews.pending');
        Route::post('order-items/{item}/review', [ShoppingController::class, 'review'])->middleware('throttle:20,1')->name('reviews.store');
    });

    // ------------------------------------------------------------------ seller app (the signed-in seller's own shop only)
    Route::prefix('seller')->name('seller.')->middleware(['auth:sanctum', 'active', 'role:seller', 'seller.shop'])->group(function () {
        Route::get('dashboard', [SellerController::class, 'dashboard'])->name('dashboard');

        Route::get('products', [SellerController::class, 'products'])->name('products.index');
        Route::post('products', [SellerController::class, 'storeProduct'])->middleware('throttle:30,1')->name('products.store');
        Route::get('products/{product}', [SellerController::class, 'product'])->name('products.show');
        Route::post('products/{product}', [SellerController::class, 'updateProduct'])->middleware('throttle:30,1')->name('products.update');
        Route::delete('products/{product}', [SellerController::class, 'destroyProduct'])->name('products.destroy');
        Route::post('products/{product}/{action}', [SellerController::class, 'productAction'])->whereIn('action', ['submit', 'unpublish', 'republish'])->name('products.action');
        Route::get('inventory', [SellerAppController::class, 'inventory'])->name('inventory');
        Route::patch('variations/{variation}/stock', [SellerController::class, 'adjustStock'])->name('variations.stock');

        Route::get('orders', [SellerController::class, 'orders'])->name('orders.index');
        Route::get('orders/{order}', [SellerController::class, 'order'])->name('orders.show');
        Route::post('orders/{order}/status', [SellerController::class, 'orderStatus'])->name('orders.status');

        Route::get('shop', [SellerAppController::class, 'shop'])->name('shop.show');
        Route::put('shop', [SellerAppController::class, 'updateShop'])->name('shop.update');
        Route::post('shop/branding', [SellerAppController::class, 'branding'])->middleware('throttle:20,1')->name('shop.branding');
        Route::post('shop/vacation', [SellerAppController::class, 'vacation'])->name('shop.vacation');

        Route::get('reviews', [SellerAppController::class, 'reviews'])->name('reviews.index');
        Route::post('reviews/{review}/reply', [SellerAppController::class, 'replyToReview'])->name('reviews.reply');

        Route::get('auto-replies', [SellerAppController::class, 'autoReplies'])->name('auto-replies.index');
        Route::post('auto-replies', [SellerAppController::class, 'storeAutoReply'])->name('auto-replies.store');
        Route::put('auto-replies/{reply}', [SellerAppController::class, 'updateAutoReply'])->name('auto-replies.update');
        Route::patch('auto-replies/{reply}/active', [SellerAppController::class, 'toggleAutoReply'])->name('auto-replies.toggle');
        Route::delete('auto-replies/{reply}', [SellerAppController::class, 'destroyAutoReply'])->name('auto-replies.destroy');

        Route::get('vouchers', [SellerAppController::class, 'vouchersIndex'])->name('vouchers.index');
        Route::post('vouchers', [SellerAppController::class, 'storeVoucher'])->name('vouchers.store');
        Route::put('vouchers/{voucher}', [SellerAppController::class, 'updateVoucher'])->name('vouchers.update');
        Route::delete('vouchers/{voucher}', [SellerAppController::class, 'destroyVoucher'])->name('vouchers.destroy');
        Route::get('promotions', [SellerAppController::class, 'promotionsIndex'])->name('promotions.index');
        Route::post('promotions', [SellerAppController::class, 'storePromotion'])->name('promotions.store');
        Route::put('promotions/{promotion}', [SellerAppController::class, 'updatePromotion'])->name('promotions.update');
        Route::delete('promotions/{promotion}', [SellerAppController::class, 'destroyPromotion'])->name('promotions.destroy');
        Route::get('referrals', [SellerAppController::class, 'referrals'])->name('referrals');

        // Money: needs POST /auth/confirm-password within the last 3 hours (423 otherwise).
        Route::prefix('finance')->name('finance.')->middleware('api.password.confirmed')->group(function () {
            Route::get('/', [SellerAppController::class, 'financeOverview'])->name('overview');
            Route::get('statement', [SellerAppController::class, 'statement'])->name('statement');
            Route::get('transactions', [SellerAppController::class, 'transactions'])->name('transactions');
            Route::get('bank-accounts', [SellerAppController::class, 'bankAccounts'])->name('bank-accounts.index');
            Route::post('bank-accounts', [SellerAppController::class, 'storeBankAccount'])->middleware('throttle:10,1')->name('bank-accounts.store');
            Route::post('bank-accounts/{account}/default', [SellerAppController::class, 'defaultBankAccount'])->name('bank-accounts.default');
            Route::delete('bank-accounts/{account}', [SellerAppController::class, 'destroyBankAccount'])->name('bank-accounts.destroy');
            Route::get('withdrawals', [SellerAppController::class, 'withdrawalsIndex'])->name('withdrawals.index');
            Route::post('withdrawals', [SellerAppController::class, 'withdraw'])->middleware('throttle:10,1')->name('withdrawals.store');
        });
    });

    // ------------------------------------------------------------------ rider app (own assignments only)
    Route::prefix('rider')->name('rider.')->middleware(['auth:sanctum', 'active', 'role:rider'])->group(function () {
        Route::get('dashboard', [RiderController::class, 'dashboard'])->name('dashboard');
        Route::post('availability', [RiderController::class, 'availability'])->name('availability');
        Route::get('profile', [RiderController::class, 'profile'])->name('profile');
        Route::put('profile', [RiderController::class, 'updateProfile'])->name('profile.update');
        Route::get('shifts', [RiderController::class, 'shifts'])->name('shifts');
        Route::get('assignments', [RiderController::class, 'assignments'])->name('assignments.index');
        Route::get('deliveries/{shipment}', [RiderController::class, 'show'])->name('deliveries.show');
        Route::post('deliveries/{shipment}/status', [RiderController::class, 'status'])->middleware('throttle:60,1')->name('deliveries.status');
        Route::post('deliveries/{shipment}/proof', [RiderController::class, 'proof'])->middleware('throttle:20,1')->name('deliveries.proof');
        Route::get('deliveries/{shipment}/proof-photo', [RiderController::class, 'proofPhoto'])->name('deliveries.proof-photo');
        Route::get('cod', [RiderController::class, 'cod'])->name('cod');
        Route::post('cod/remittances', [RiderController::class, 'remit'])->middleware('throttle:10,1')->name('cod.remit');
    });

    // ------------------------------------------------------------------ logistics partner (own shipments and riders only)
    Route::prefix('logistics')->name('logistics.')->middleware(['auth:sanctum', 'active', 'role:logistics', 'logistics.partner'])->group(function () {
        Route::get('dashboard', [LogisticsController::class, 'dashboard'])->name('dashboard');
        Route::get('shipments', [LogisticsController::class, 'shipments'])->name('shipments.index');
        Route::get('shipments/{shipment}', [LogisticsController::class, 'shipment'])->name('shipments.show');
        Route::post('shipments/{shipment}/assign', [LogisticsController::class, 'assign'])->name('shipments.assign');
        Route::post('shipments/{shipment}/unassign', [LogisticsController::class, 'unassign'])->name('shipments.unassign');
        Route::get('riders', [LogisticsController::class, 'riders'])->name('riders.index');
        Route::post('riders', [LogisticsController::class, 'storeRider'])->name('riders.store');
        Route::get('riders/{rider}', [LogisticsController::class, 'rider'])->name('riders.show');
        Route::put('riders/{rider}', [LogisticsController::class, 'updateRider'])->name('riders.update');
        Route::post('riders/{rider}/status', [LogisticsController::class, 'riderStatus'])->name('riders.status');
    });
});
