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
