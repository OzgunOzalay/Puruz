<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SitemapController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Route;

// Catalogue
Route::get('/', [ShopController::class, 'home'])->name('shop.home');
Route::get('/urunler', [ShopController::class, 'index'])->name('shop.index');
Route::get('/kategori/{category:slug}', [ShopController::class, 'category'])->name('shop.category');
Route::get('/atolyeler', [ShopController::class, 'workshops'])->name('shop.workshops');
Route::get('/urun/{slug}', [ShopController::class, 'product'])->name('shop.product');
Route::get('/onizleme/urun/{product}', [ShopController::class, 'preview'])->name('shop.product.preview')->middleware('signed');
Route::get('/p/{id}', [ShopController::class, 'short'])->name('shop.short')->whereNumber('id');
Route::get('/link', [ShopController::class, 'linkInBio'])->name('shop.link');

// Cart
Route::get('/sepet', [CartController::class, 'show'])->name('cart.show');
Route::post('/sepet', [CartController::class, 'add'])->name('cart.add');
Route::post('/hemen-al', [CartController::class, 'buyNow'])->name('cart.buy-now');
Route::patch('/sepet/{key}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/sepet/{key}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/sepet/kupon', [CartController::class, 'applyCoupon'])->name('cart.coupon');
Route::delete('/sepet-kupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

// Checkout and payment
Route::get('/odeme', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/odeme', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:checkout');
Route::post('/odeme/tekrar/{token}', [PaymentController::class, 'retry'])->name('payment.retry')->middleware('throttle:checkout');
Route::get('/odeme/basarisiz/{token}', [PaymentController::class, 'failed'])->name('payment.failed');

// iyzico calls these cross-site: no session (a SameSite cookie would not arrive anyway) and no CSRF.
Route::withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, EncryptCookies::class, AddQueuedCookiesToResponse::class])
    ->group(function () {
        Route::post('/odeme/donus', [PaymentController::class, 'callback'])->name('payment.callback');
        Route::post('/odeme/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');
    });

// Local stand-in for the iyzico payment page (only active with PAYMENT_GATEWAY=fake).
Route::get('/odeme/test/{token}', [PaymentController::class, 'fake'])->name('payment.fake');
Route::post('/odeme/test/{token}', [PaymentController::class, 'fakeSubmit'])->name('payment.fake.submit');

// Order tracking
Route::get('/siparis/{token}', [OrderTrackingController::class, 'show'])->name('orders.track');
Route::get('/siparis/{token}/takvim.ics', [OrderTrackingController::class, 'calendar'])->name('orders.calendar');
Route::get('/siparis-sorgula', [OrderTrackingController::class, 'lookup'])->name('orders.lookup');
Route::post('/siparis-sorgula', [OrderTrackingController::class, 'sendLink'])->name('orders.lookup.send')->middleware('throttle:order-lookup');

// Content
Route::get('/sayfa/{page:slug}', [PageController::class, 'show'])->name('pages.show');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
