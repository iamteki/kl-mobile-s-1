<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\{
    HomeController,
    CategoryController,
    ProductController,
    PackageController,
    CartController,
    CheckoutController,
    BookingController,
    AccountController
};

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');

// Categories
Route::get('/equipment/{category}', [CategoryController::class, 'show'])->name('categories.show');

// Products
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::post('/products/{product}/check-availability', [ProductController::class, 'checkAvailability'])->name('products.check-availability');

// Packages
Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{package}', [PackageController::class, 'show'])->name('packages.show');

// Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::put('/cart/update/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/remove/{item}', [CartController::class, 'remove'])->name('cart.remove');

// Checkout (authenticated)
Route::middleware(['auth'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');
    Route::get('/checkout/success/{booking}', [CheckoutController::class, 'success'])->name('checkout.success');
    
    // Account
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/dashboard', [AccountController::class, 'dashboard'])->name('dashboard');
        Route::get('/bookings', [AccountController::class, 'bookings'])->name('bookings');
        Route::get('/bookings/{booking}', [AccountController::class, 'bookingDetails'])->name('bookings.show');
        Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
        Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    });
});
