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
    AccountController,
    PageController
};

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');

// Categories
Route::get('/equipment', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/equipment/{category}', [CategoryController::class, 'show'])->name('categories.show');

// Products
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::post('/products/{product}/check-availability', [ProductController::class, 'checkAvailability'])->name('products.check-availability');

// Packages
Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{package}', [PackageController::class, 'show'])->name('packages.show');
Route::post('/packages/{package}/check-availability', [PackageController::class, 'checkAvailability'])->name('packages.check-availability');

// Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/add-package', [CartController::class, 'addPackage'])->name('cart.add-package');
Route::put('/cart/update/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/remove/{item}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon'])->name('cart.apply-coupon');
Route::delete('/cart/remove-coupon', [CartController::class, 'removeCoupon'])->name('cart.remove-coupon');

// Quick Booking (for direct package booking)
Route::post('/booking/create', [BookingController::class, 'create'])->name('booking.create');

// Search
Route::get('/search', [ProductController::class, 'search'])->name('search');

// Static Pages
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/services', [PageController::class, 'services'])->name('services');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])->name('contact.submit');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/sitemap', [PageController::class, 'sitemap'])->name('sitemap');



// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Checkout
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/event-details', [CheckoutController::class, 'storeEventDetails'])->name('checkout.event-details');
    Route::post('/checkout/customer-info', [CheckoutController::class, 'storeCustomerInfo'])->name('checkout.customer-info');
    Route::get('/checkout/payment', [CheckoutController::class, 'payment'])->name('checkout.payment');
    Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');
    Route::get('/checkout/success/{booking}', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::post('/checkout/create-payment-intent', [CheckoutController::class, 'createPaymentIntent'])->name('checkout.create-payment-intent');
    
    // Account
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/dashboard', [AccountController::class, 'dashboard'])->name('dashboard');
        Route::get('/bookings', [AccountController::class, 'bookings'])->name('bookings');
        Route::get('/bookings/{booking}', [AccountController::class, 'bookingDetails'])->name('bookings.show');
        Route::post('/bookings/{booking}/cancel', [AccountController::class, 'cancelBooking'])->name('bookings.cancel');
        Route::get('/bookings/{booking}/invoice', [AccountController::class, 'downloadInvoice'])->name('bookings.invoice');
        Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
        Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
        Route::put('/profile/password', [AccountController::class, 'updatePassword'])->name('profile.password');
    });
});

// Admin routes (if needed for frontend)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Redirect to Filament admin panel
    Route::get('/', function () {
        return redirect('/admin/dashboard');
    });
});

// API routes for AJAX calls (if not using api.php)
Route::prefix('ajax')->name('ajax.')->group(function () {
    Route::post('/search', [HomeController::class, 'search'])->name('search');
    Route::post('/filter-products', [ProductController::class, 'filter'])->name('filter-products');
    Route::post('/load-more-products', [ProductController::class, 'loadMore'])->name('load-more-products');
    Route::get('/cart-count', [CartController::class, 'getCount'])->name('cart.count');
});