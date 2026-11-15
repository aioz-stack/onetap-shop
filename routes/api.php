<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\LogisticsController;
use App\Http\Controllers\Api\V1\RiderController;
use App\Http\Controllers\Api\V1\SellerController;
use App\Http\Controllers\Api\V1\ShoppingController;
use App\Http\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;

/*
| Versioned REST API for the future mobile apps. Prefixed with /api by bootstrap/app.php.
| Authentication uses Sanctum bearer tokens.
*/
Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
        });

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    // Public catalog.
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('products', [CatalogController::class, 'products'])->name('products.index');
    Route::get('products/{slug}', [CatalogController::class, 'product'])->name('products.show');
    Route::get('shops', [CatalogController::class, 'shops'])->name('shops.index');
    Route::get('shops/{slug}', [CatalogController::class, 'shop'])->name('shops.show');

    // Customer.
    Route::middleware(['auth:sanctum', 'active', 'role:customer'])->group(function () {
        Route::get('customer/profile', [ShoppingController::class, 'profile'])->name('customer.profile');
        Route::put('customer/profile', [ShoppingController::class, 'updateProfile'])->name('customer.profile.update');
        Route::get('customer/addresses', [ShoppingController::class, 'addresses'])->name('customer.addresses.index');
        Route::post('customer/addresses', [ShoppingController::class, 'storeAddress'])->name('customer.addresses.store');
        Route::put('customer/addresses/{address}', [ShoppingController::class, 'updateAddress'])->name('customer.addresses.update');
        Route::delete('customer/addresses/{address}', [ShoppingController::class, 'destroyAddress'])->name('customer.addresses.destroy');

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
        Route::post('order-items/{item}/review', [ShoppingController::class, 'review'])->middleware('throttle:20,1')->name('reviews.store');
    });

    // Seller Center: the signed-in seller's own shop only.
    Route::prefix('seller')->name('seller.')->middleware(['auth:sanctum', 'active', 'role:seller', 'seller.shop'])->group(function () {
        Route::get('dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
        Route::get('products', [SellerController::class, 'products'])->name('products.index');
        Route::post('products', [SellerController::class, 'storeProduct'])->middleware('throttle:30,1')->name('products.store');
        Route::get('products/{product}', [SellerController::class, 'product'])->name('products.show');
        Route::post('products/{product}', [SellerController::class, 'updateProduct'])->middleware('throttle:30,1')->name('products.update');
        Route::delete('products/{product}', [SellerController::class, 'destroyProduct'])->name('products.destroy');
        Route::post('products/{product}/{action}', [SellerController::class, 'productAction'])->whereIn('action', ['submit', 'unpublish', 'republish'])->name('products.action');
        Route::patch('variations/{variation}/stock', [SellerController::class, 'adjustStock'])->name('variations.stock');
        Route::get('orders', [SellerController::class, 'orders'])->name('orders.index');
        Route::get('orders/{order}', [SellerController::class, 'order'])->name('orders.show');
        Route::post('orders/{order}/status', [SellerController::class, 'orderStatus'])->name('orders.status');
    });

    // Logistics partner: own shipments and riders only.
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

    // Inbox for every signed-in role.
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationsController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationsController::class, 'markRead'])->name('notifications.read');
    });

    // Rider app: own assignments only.
    Route::prefix('rider')->name('rider.')->middleware(['auth:sanctum', 'active', 'role:rider'])->group(function () {
        Route::get('dashboard', [RiderController::class, 'dashboard'])->name('dashboard');
        Route::post('availability', [RiderController::class, 'availability'])->name('availability');
        Route::get('assignments', [RiderController::class, 'assignments'])->name('assignments.index');
        Route::get('deliveries/{shipment}', [RiderController::class, 'show'])->name('deliveries.show');
        Route::post('deliveries/{shipment}/status', [RiderController::class, 'status'])->middleware('throttle:60,1')->name('deliveries.status');
        Route::post('deliveries/{shipment}/proof', [RiderController::class, 'proof'])->middleware('throttle:20,1')->name('deliveries.proof');
        Route::get('deliveries/{shipment}/proof-photo', [RiderController::class, 'proofPhoto'])->name('deliveries.proof-photo');
        Route::get('cod', [RiderController::class, 'cod'])->name('cod');
        Route::post('cod/remittances', [RiderController::class, 'remit'])->middleware('throttle:10,1')->name('cod.remit');
    });
});
