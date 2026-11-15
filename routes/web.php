<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ConfirmPassword;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\CRUD;
use App\Http\Controllers\Customer;
use App\Http\Controllers\Dashboard;
use App\Http\Controllers\Logistics;
use App\Http\Controllers\Rider;
use App\Http\Controllers\Seller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public storefront.
Route::get('/', [Customer\Home::class, 'index'])->name('home');
Route::get('/search', [Customer\Catalog::class, 'search'])->name('search');
Route::get('/products/{slug}', [Customer\Catalog::class, 'product'])->name('products.show');
Route::get('/shops/{slug}', [Customer\Catalog::class, 'shop'])->name('shops.show');

// Shopping and My Account (customers, including sellers who also buy).
Route::middleware(['auth', 'active', 'role:customer'])->group(function () {
    Route::get('/cart', [Customer\Cart::class, 'index'])->name('cart.index');
    Route::post('/cart/items', [Customer\Cart::class, 'store'])->name('cart.store');
    Route::patch('/cart/items/{item}', [Customer\Cart::class, 'update'])->name('cart.update');
    Route::delete('/cart/items/{item}', [Customer\Cart::class, 'destroy'])->name('cart.destroy');
    Route::post('/cart/select', [Customer\Cart::class, 'select'])->name('cart.select');

    Route::get('/checkout', [Customer\Checkout::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [Customer\Checkout::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/checkout/success/{orderNumber}', [Customer\Checkout::class, 'success'])->name('checkout.success');

    Route::prefix('account')->name('account.')->group(function () {
        Route::redirect('/', '/account/orders');
        Route::get('profile', [Customer\Account\Profile::class, 'edit'])->name('profile');
        Route::post('profile', [Customer\Account\Profile::class, 'update'])->name('profile.update');

        Route::get('addresses', [Customer\Account\Addresses::class, 'index'])->name('addresses.index');
        Route::post('addresses', [Customer\Account\Addresses::class, 'store'])->name('addresses.store');
        Route::put('addresses/{address}', [Customer\Account\Addresses::class, 'update'])->name('addresses.update');
        Route::delete('addresses/{address}', [Customer\Account\Addresses::class, 'destroy'])->name('addresses.destroy');
        Route::post('addresses/{address}/default', [Customer\Account\Addresses::class, 'makeDefault'])->name('addresses.default');

        Route::get('orders', [Customer\Account\Orders::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Customer\Account\Orders::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/cancel', [Customer\Account\Orders::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/confirm', [Customer\Account\Orders::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/refund', [Customer\Account\Orders::class, 'refund'])->name('orders.refund');
        Route::post('items/{item}/review', [Customer\Account\Orders::class, 'review'])->name('items.review');
        Route::get('cancellations', [Customer\Account\Orders::class, 'cancellations'])->name('cancellations');
        Route::get('refunds', [Customer\Account\Orders::class, 'refunds'])->name('refunds');
        Route::get('reviews', [Customer\Account\Orders::class, 'reviews'])->name('reviews');

        Route::get('notifications', [Customer\Account\Notifications::class, 'index'])->name('notifications');
        Route::get('notifications/{id}', [Customer\Account\Notifications::class, 'open'])->name('notifications.open');
        Route::post('notifications/read-all', [Customer\Account\Notifications::class, 'readAll'])->name('notifications.read-all');

        Route::get('chat', [Customer\Account\Chat::class, 'index'])->name('chat.index');
        Route::get('chat/{conversation}', [Customer\Account\Chat::class, 'index'])->name('chat.show');
        Route::post('chat/{conversation}', [Customer\Account\Chat::class, 'send'])->middleware('throttle:30,1')->name('chat.send');
        Route::post('chat/shops/{shop}', [Customer\Account\Chat::class, 'start'])->name('chat.start');

        Route::get('security', [Customer\Account\Security::class, 'edit'])->name('security');
        Route::put('security/password', [Customer\Account\Security::class, 'password'])->name('security.password');
        Route::post('security/sign-out-others', [Customer\Account\Security::class, 'signOutOthers'])->name('security.sign-out-others');
        Route::get('verification', [Customer\Account\Security::class, 'verification'])->name('verification');
        Route::post('verification', [Customer\Account\Security::class, 'uploadVerification'])->name('verification.store');
        Route::get('sell', [Customer\Account\Security::class, 'sellerApplication'])->name('sell');
        Route::post('sell', [Customer\Account\Security::class, 'applyToSell'])->name('sell.store');
    });
});

// Guests only.
Route::middleware('guest')->group(function () {
    Route::get('/login', [Login::class, 'showLoginForm'])->name('login');
    Route::post('/login', [Login::class, 'login'])->name('login.submit');
    Route::get('/register', [Register::class, 'create'])->name('register');
    Route::post('/register', [Register::class, 'store'])->middleware('throttle:auth')->name('register.store');
});

// Any signed-in, active user.
Route::middleware(['auth', 'active'])->group(function () {
    // Sends each user to their own panel.
    Route::get('/dashboard', [Dashboard::class, 'index'])->name('dashboard');
    Route::post('/logout', [Login::class, 'logout'])->name('logout');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', [Admin\Dashboard::class, 'index'])->name('dashboard');

        // People
        Route::resource('users', Admin\Users::class)->except('destroy');
        Route::post('users/{user}/suspend', [Admin\Users::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/reactivate', [Admin\Users::class, 'reactivate'])->name('users.reactivate');

        Route::get('verifications', [Admin\Verifications::class, 'index'])->name('verifications.index');
        Route::get('verifications/{document}/file', [Admin\Verifications::class, 'file'])->name('verifications.file');
        Route::post('verifications/{document}/approve', [Admin\Verifications::class, 'approve'])->name('verifications.approve');
        Route::post('verifications/{document}/reject', [Admin\Verifications::class, 'reject'])->name('verifications.reject');

        Route::get('sellers', [Admin\Sellers::class, 'index'])->name('sellers.index');
        Route::get('sellers/{seller}', [Admin\Sellers::class, 'show'])->name('sellers.show');
        foreach (['approve', 'reject', 'suspend', 'reinstate'] as $action) {
            Route::post("sellers/{seller}/{$action}", [Admin\Sellers::class, $action])->name("sellers.{$action}");
        }

        // Catalog
        Route::get('products', [Admin\Products::class, 'index'])->name('products.index');
        Route::get('products/{product}', [Admin\Products::class, 'show'])->name('products.show')->withTrashed();
        foreach (['approve', 'reject', 'unpublish', 'flag', 'resolve'] as $action) {
            Route::post("products/{product}/{$action}", [Admin\Products::class, $action])->name("products.{$action}");
        }

        Route::get('categories', [Admin\Categories::class, 'index'])->name('categories.index');
        Route::post('categories', [Admin\Categories::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [Admin\Categories::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [Admin\Categories::class, 'destroy'])->name('categories.destroy');

        // Orders
        Route::get('orders', [Admin\Orders::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Admin\Orders::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/cancel', [Admin\Orders::class, 'cancel'])->name('orders.cancel');

        Route::get('refunds', [Admin\Refunds::class, 'index'])->name('refunds.index');
        Route::post('refunds/{refund}/approve', [Admin\Refunds::class, 'approve'])->name('refunds.approve');
        Route::post('refunds/{refund}/reject', [Admin\Refunds::class, 'reject'])->name('refunds.reject');

        // Logistics
        Route::prefix('logistics')->name('logistics.')->group(function () {
            Route::get('partners', [Admin\LogisticsPartners::class, 'index'])->name('partners.index');
            Route::get('partners/create', [Admin\LogisticsPartners::class, 'create'])->name('partners.create');
            Route::post('partners', [Admin\LogisticsPartners::class, 'store'])->name('partners.store');
            Route::get('partners/{partner}', [Admin\LogisticsPartners::class, 'show'])->name('partners.show');
            Route::put('partners/{partner}', [Admin\LogisticsPartners::class, 'update'])->name('partners.update');
            Route::post('partners/{partner}/areas', [Admin\LogisticsPartners::class, 'storeArea'])->name('partners.areas.store');
            Route::put('partners/{partner}/areas/{area}', [Admin\LogisticsPartners::class, 'updateArea'])->name('partners.areas.update');
            Route::delete('partners/{partner}/areas/{area}', [Admin\LogisticsPartners::class, 'destroyArea'])->name('partners.areas.destroy');

            Route::get('riders', [Admin\Riders::class, 'index'])->name('riders.index');
            Route::post('riders/{rider}/suspend', [Admin\Riders::class, 'suspend'])->name('riders.suspend');
            Route::post('riders/{rider}/activate', [Admin\Riders::class, 'activate'])->name('riders.activate');

            Route::get('shipments', [Admin\Shipments::class, 'index'])->name('shipments.index');
            Route::post('shipments/{shipment}/route', [Admin\Shipments::class, 'route'])->name('shipments.route');
            Route::get('cod', [Admin\Cod::class, 'index'])->name('cod.index');
        });

        // Finance
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [Admin\Finance::class, 'overview'])->name('overview');
            Route::get('transactions', [Admin\Finance::class, 'transactions'])->name('transactions');
            Route::get('transactions/export', [Admin\Finance::class, 'exportTransactions'])->name('transactions.export');
            Route::get('reports', [Admin\Finance::class, 'reports'])->name('reports');
            Route::get('reports/export', [Admin\Finance::class, 'exportReport'])->name('reports.export');

            Route::get('withdrawals', [Admin\Withdrawals::class, 'index'])->name('withdrawals.index');
            Route::post('withdrawals/{withdrawal}/approve', [Admin\Withdrawals::class, 'approve'])->name('withdrawals.approve');
            Route::post('withdrawals/{withdrawal}/pay', [Admin\Withdrawals::class, 'pay'])->name('withdrawals.pay');
            Route::post('withdrawals/{withdrawal}/reject', [Admin\Withdrawals::class, 'reject'])->name('withdrawals.reject');
        });

        // System
        Route::get('settings/{group?}', [Admin\Settings::class, 'edit'])->name('settings.edit');
        Route::put('settings/{group}', [Admin\Settings::class, 'update'])->name('settings.update');
        Route::get('audit-logs', [Admin\AuditLogs::class, 'index'])->name('audit-logs.index');
    });

    // Starter user CRUD, kept for admins until Phase 3 replaces it with full user management.
    Route::resource('crud', CRUD::class)->names('crud')->middleware('role:admin');

    // Re-enter password before sensitive pages (Laravel's password.confirm middleware).
    Route::get('/confirm-password', [ConfirmPassword::class, 'show'])->name('password.confirm');
    Route::post('/confirm-password', [ConfirmPassword::class, 'store'])->middleware('throttle:6,1')->name('password.confirm.store');

    Route::prefix('seller')->name('seller.')->middleware(['role:seller', 'seller.shop'])->group(function () {
        Route::get('/', [Seller\Dashboard::class, 'index'])->name('dashboard');

        Route::get('products', [Seller\Products::class, 'index'])->name('products.index');
        Route::get('products/create', [Seller\Products::class, 'create'])->name('products.create');
        Route::post('products', [Seller\Products::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [Seller\Products::class, 'edit'])->name('products.edit');
        Route::post('products/{product}', [Seller\Products::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [Seller\Products::class, 'destroy'])->name('products.destroy');
        foreach (['submit', 'unpublish', 'republish'] as $action) {
            Route::post("products/{product}/{$action}", [Seller\Products::class, $action])->name("products.{$action}");
        }
        Route::get('inventory', [Seller\Products::class, 'inventory'])->name('inventory');
        Route::post('inventory/{variation}', [Seller\Products::class, 'adjustStock'])->name('inventory.adjust');

        Route::get('orders', [Seller\Orders::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Seller\Orders::class, 'show'])->name('orders.show');
        foreach (['accept', 'ready', 'cancel'] as $action) {
            Route::post("orders/{order}/{$action}", [Seller\Orders::class, $action])->name("orders.{$action}");
        }

        Route::get('shop', [Seller\ShopSettings::class, 'edit'])->name('shop.edit');
        Route::put('shop', [Seller\ShopSettings::class, 'update'])->name('shop.update');
        Route::post('shop/branding', [Seller\ShopSettings::class, 'branding'])->name('shop.branding');
        Route::put('shop/vacation', [Seller\ShopSettings::class, 'vacation'])->name('shop.vacation');

        Route::get('chat', [Seller\CustomerService::class, 'inbox'])->name('chat.index');
        Route::get('chat/{conversation}', [Seller\CustomerService::class, 'inbox'])->name('chat.show');
        Route::post('chat/{conversation}', [Seller\CustomerService::class, 'send'])->middleware('throttle:60,1')->name('chat.send');
        Route::get('auto-replies', [Seller\CustomerService::class, 'autoReplies'])->name('auto-replies.index');
        Route::post('auto-replies', [Seller\CustomerService::class, 'storeAutoReply'])->name('auto-replies.store');
        Route::put('auto-replies/{reply}', [Seller\CustomerService::class, 'updateAutoReply'])->name('auto-replies.update');
        Route::delete('auto-replies/{reply}', [Seller\CustomerService::class, 'destroyAutoReply'])->name('auto-replies.destroy');
        Route::get('reviews', [Seller\CustomerService::class, 'reviews'])->name('reviews.index');
        Route::post('reviews/{review}/reply', [Seller\CustomerService::class, 'replyToReview'])->name('reviews.reply');

        Route::get('vouchers', [Seller\Marketing::class, 'vouchers'])->name('vouchers.index');
        Route::post('vouchers', [Seller\Marketing::class, 'storeVoucher'])->name('vouchers.store');
        Route::put('vouchers/{voucher}', [Seller\Marketing::class, 'updateVoucher'])->name('vouchers.update');
        Route::delete('vouchers/{voucher}', [Seller\Marketing::class, 'destroyVoucher'])->name('vouchers.destroy');
        Route::get('promotions', [Seller\Marketing::class, 'promotions'])->name('promotions.index');
        Route::post('promotions', [Seller\Marketing::class, 'storePromotion'])->name('promotions.store');
        Route::put('promotions/{promotion}', [Seller\Marketing::class, 'updatePromotion'])->name('promotions.update');
        Route::delete('promotions/{promotion}', [Seller\Marketing::class, 'destroyPromotion'])->name('promotions.destroy');
        Route::get('referrals', [Seller\Marketing::class, 'referrals'])->name('referrals');

        // Money pages require a recent password confirmation.
        Route::prefix('finance')->name('finance.')->middleware('password.confirm')->group(function () {
            Route::get('/', [Seller\Finance::class, 'overview'])->name('overview');
            Route::get('statement', [Seller\Finance::class, 'statement'])->name('statement');
            Route::get('transactions', [Seller\Finance::class, 'transactions'])->name('transactions');
            Route::post('bank-accounts', [Seller\Finance::class, 'storeBankAccount'])->name('bank-accounts.store');
            Route::post('bank-accounts/{account}/default', [Seller\Finance::class, 'defaultBankAccount'])->name('bank-accounts.default');
            Route::delete('bank-accounts/{account}', [Seller\Finance::class, 'destroyBankAccount'])->name('bank-accounts.destroy');
            Route::post('withdrawals', [Seller\Finance::class, 'withdraw'])->middleware('throttle:10,1')->name('withdrawals.store');
        });
    });

    Route::prefix('logistics')->name('logistics.')->middleware(['role:logistics', 'logistics.partner'])->group(function () {
        Route::get('/', [Logistics\Dashboard::class, 'index'])->name('dashboard');

        Route::get('shipments', [Logistics\Shipments::class, 'index'])->name('shipments.index');
        Route::get('shipments/{shipment}', [Logistics\Shipments::class, 'show'])->name('shipments.show');
        Route::post('shipments/{shipment}/assign', [Logistics\Shipments::class, 'assign'])->name('shipments.assign');
        Route::post('shipments/{shipment}/unassign', [Logistics\Shipments::class, 'unassign'])->name('shipments.unassign');
        Route::post('shipments/{shipment}/reschedule', [Logistics\Shipments::class, 'reschedule'])->name('shipments.reschedule');
        Route::post('shipments/{shipment}/return', [Logistics\Shipments::class, 'returnToSeller'])->name('shipments.return');

        Route::get('riders', [Logistics\Riders::class, 'index'])->name('riders.index');
        Route::get('riders/create', [Logistics\Riders::class, 'create'])->name('riders.create');
        Route::post('riders', [Logistics\Riders::class, 'store'])->name('riders.store');
        Route::get('riders/{rider}', [Logistics\Riders::class, 'show'])->name('riders.show');
        Route::get('riders/{rider}/edit', [Logistics\Riders::class, 'edit'])->name('riders.edit');
        Route::put('riders/{rider}', [Logistics\Riders::class, 'update'])->name('riders.update');
        Route::post('riders/{rider}/approve', [Logistics\Riders::class, 'approve'])->name('riders.approve');
        Route::post('riders/{rider}/suspend', [Logistics\Riders::class, 'suspend'])->name('riders.suspend');

        Route::get('coverage', [Logistics\Operations::class, 'coverage'])->name('coverage');
        Route::post('coverage', [Logistics\Operations::class, 'storeArea'])->name('coverage.store');
        Route::put('coverage/{area}', [Logistics\Operations::class, 'updateArea'])->name('coverage.update');
        Route::delete('coverage/{area}', [Logistics\Operations::class, 'destroyArea'])->name('coverage.destroy');

        Route::get('cod', [Logistics\Operations::class, 'cod'])->name('cod');
        Route::get('remittances', [Logistics\Operations::class, 'remittances'])->name('remittances');
        Route::post('remittances/{remittance}/confirm', [Logistics\Operations::class, 'confirmRemittance'])->name('remittances.confirm');
        Route::get('earnings', [Logistics\Operations::class, 'earnings'])->name('earnings');
        Route::get('earnings/export', [Logistics\Operations::class, 'exportEarnings'])->name('earnings.export');
    });

    Route::prefix('rider')->name('rider.')->middleware('role:rider')->group(function () {
        Route::get('/', [Rider\Dashboard::class, 'index'])->name('dashboard');
    });
});

// Anything unmatched renders the Inertia 404 page.
Route::fallback(function () {
    return Inertia::render('Errors/NotFound')->toResponse(request())->setStatusCode(404);
});
