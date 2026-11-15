<?php

use App\Http\Controllers\Admin;
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

    Route::prefix('seller')->name('seller.')->middleware('role:seller')->group(function () {
        Route::get('/', [Seller\Dashboard::class, 'index'])->name('dashboard');
    });

    Route::prefix('logistics')->name('logistics.')->middleware('role:logistics')->group(function () {
        Route::get('/', [Logistics\Dashboard::class, 'index'])->name('dashboard');
    });

    Route::prefix('rider')->name('rider.')->middleware('role:rider')->group(function () {
        Route::get('/', [Rider\Dashboard::class, 'index'])->name('dashboard');
    });
});

// Anything unmatched renders the Inertia 404 page.
Route::fallback(function () {
    return Inertia::render('Errors/NotFound')->toResponse(request())->setStatusCode(404);
});
