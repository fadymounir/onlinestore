<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Account\ReviewController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\WishlistController;
use App\Http\Middleware\AddressesGate;
use Illuminate\Support\Facades\Route;

/*
|---------------------------------------------------------------------------
| Storefront — Arabic only, RTL
|---------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/products', [CatalogController::class, 'index'])->name('catalog');
Route::get('/category/{category:slug}', [CatalogController::class, 'category'])->name('category');
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('product');

// Admin-authored content: about, privacy, terms. Kept under a /page prefix
// rather than at the root so a new page can never shadow a real route.
Route::get('/page/{page:slug}', [PageController::class, 'show'])->name('page');

// Cart works for guests (session) and customers (database) alike.
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/add/{variant}', [CartController::class, 'add'])->name('add');
    Route::patch('/update/{variant}', [CartController::class, 'update'])->name('update');
    Route::delete('/remove/{variant}', [CartController::class, 'remove'])->name('remove');
    Route::post('/coupon', [CartController::class, 'applyCoupon'])->name('coupon.apply');
    Route::delete('/coupon', [CartController::class, 'removeCoupon'])->name('coupon.remove');
});

/*
|---------------------------------------------------------------------------
| Guests only
|---------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Forgot-password cycle. The GET reset route must be named
    // `password.reset` — that is the name the mailed link is built from.
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

/*
|---------------------------------------------------------------------------
| Customers — checkout is accounts-only by design
|---------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
    Route::post('/wishlist/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

    Route::post('/product/{product}/review', [ReviewController::class, 'store'])->name('review.store');

    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        // Re-uploading a rejected deposit transfer receipt.
        Route::post('/orders/{order}/deposit-receipt', [OrderController::class, 'uploadReceipt'])
            ->name('orders.deposit-receipt');

        // The whole address book answers to the `customer_addresses_enabled`
        // switch: with it off the shop takes delivery details itself, so
        // there is nothing here for a customer to keep.
        Route::middleware(AddressesGate::class)->group(function () {
            Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
            Route::get('/addresses/{address}/edit', [AddressController::class, 'edit'])->name('addresses.edit');
            Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
            Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
            Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
        });
    });
});
