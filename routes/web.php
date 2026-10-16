<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\WishlistController;
use Illuminate\Support\Facades\Route;

// ── Language ──────────────────────────────────────────────
Route::get('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

// ── Auth ──────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login']);
    Route::get('/register',       [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',      [AuthController::class, 'sendOtp'])->name('register.send-otp');
    Route::get('/register/otp',   [AuthController::class, 'showOtp'])->name('register.otp');
    Route::post('/register/otp',  [AuthController::class, 'verifyOtp'])->name('register.verify-otp');
    Route::post('/register/resend',[AuthController::class, 'resendOtp'])->name('register.resend-otp');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Google OAuth ──────────────────────────────────────────
Route::get('/auth/google',          [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// ── Shop (Public) ─────────────────────────────────────────
Route::get('/',         [HomeController::class, 'index'])->name('home');
Route::get('/products', [ProductController::class, 'index'])->name('products');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('product.detail');

// ── Shop (Auth Required) ──────────────────────────────────
Route::middleware('auth')->group(function () {

    // Cart
    Route::get('/cart',              [CartController::class, 'index'])->name('cart');
    Route::post('/cart/add',         [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/update',      [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
    Route::delete('/cart/clear',     [CartController::class, 'clear'])->name('cart.clear');
    Route::post('/cart/coupon',      [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
    Route::delete('/cart/coupon',    [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

    // Checkout & Orders
    Route::get('/checkout',          [OrderController::class, 'checkout'])->name('checkout');
    Route::post('/checkout',         [OrderController::class, 'placeOrder'])->name('order.place');
    Route::get('/order/success/{id}',          [OrderController::class, 'success'])->name('order.success');

    // Payment routes
    Route::get('/payment/aba/{orderId}',            [\App\Http\Controllers\Shop\PaymentController::class, 'showAba'])->name('payment.aba');
    Route::post('/payment/aba/success/{orderId}',   [\App\Http\Controllers\Shop\PaymentController::class, 'abaSuccess'])->name('payment.aba.success');
    Route::get('/payment/khqr/{orderId}',           [\App\Http\Controllers\Shop\PaymentController::class, 'showKhqr'])->name('payment.khqr');
    Route::post('/payment/khqr/check/{orderId}',    [\App\Http\Controllers\Shop\PaymentController::class, 'checkKhqr'])->name('payment.khqr.check');

    // Bank Transfer
    Route::get('/payment/bank/{orderId}',           [\App\Http\Controllers\Shop\PaymentController::class, 'showBank'])->name('payment.bank');
    Route::post('/payment/bank/slip/{orderId}',     [\App\Http\Controllers\Shop\PaymentController::class, 'bankSlip'])->name('payment.bank.slip');
    Route::get('/my-orders',         [OrderController::class, 'myOrders'])->name('my.orders');
    Route::get('/my-orders/{id}',    [OrderController::class, 'detail'])->name('order.detail');

    // Wishlist
    Route::get('/wishlist',                  [WishlistController::class, 'index'])->name('wishlist');
    Route::post('/wishlist/toggle/{id}',     [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::delete('/wishlist/remove/{id}',   [WishlistController::class, 'remove'])->name('wishlist.remove');
    Route::post('/wishlist/to-cart/{id}',    [WishlistController::class, 'moveToCart'])->name('wishlist.to-cart');

    // Reviews
    Route::post('/products/{id}/review',     [ProductController::class, 'review'])->name('product.review');
});

// ── Coupon API ────────────────────────────────────────────
Route::get('/coupon/check', function() {
    $code   = strtoupper(trim(request('code')));
    $coupon = \App\Models\Coupon::where('code', $code)
        ->where('is_active', true)
        ->where(function($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
        ->first();
    if (!$coupon) return response()->json(['valid' => false]);
    return response()->json([
        'valid' => true,
        'type'  => $coupon->type,
        'value' => $coupon->value,
        'code'  => $coupon->code,
    ]);
})->name('coupon.check');

// ── Admin ─────────────────────────────────────────────────
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

    // Dashboard
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Products
    Route::get('/products',              [AdminController::class, 'products'])->name('products');
    Route::get('/products/create',       [AdminController::class, 'createProduct'])->name('product.create');
    Route::post('/products',             [AdminController::class, 'storeProduct'])->name('product.store');
    Route::get('/products/{id}/edit',    [AdminController::class, 'editProduct'])->name('product.edit');
    Route::put('/products/{id}',         [AdminController::class, 'updateProduct'])->name('product.update');
    Route::delete('/products/{id}',      [AdminController::class, 'deleteProduct'])->name('product.delete');

    // Categories
    Route::get('/categories',            [AdminController::class, 'categories'])->name('categories');
    Route::get('/categories/create',     [AdminController::class, 'createCategory'])->name('category.create');
    Route::post('/categories',           [AdminController::class, 'storeCategory'])->name('category.store');
    Route::get('/categories/{id}/edit',  [AdminController::class, 'editCategory'])->name('category.edit');
    Route::put('/categories/{id}',       [AdminController::class, 'updateCategory'])->name('category.update');
    Route::delete('/categories/{id}',    [AdminController::class, 'deleteCategory'])->name('category.delete');

    // Brands
    Route::get('/brands',                [AdminController::class, 'brands'])->name('brands');
    Route::get('/brands/create',         [AdminController::class, 'createBrand'])->name('brand.create');
    Route::post('/brands',               [AdminController::class, 'storeBrand'])->name('brand.store');
    Route::get('/brands/{id}/edit',      [AdminController::class, 'editBrand'])->name('brand.edit');
    Route::put('/brands/{id}',           [AdminController::class, 'updateBrand'])->name('brand.update');
    Route::delete('/brands/{id}',        [AdminController::class, 'deleteBrand'])->name('brand.delete');

    // Orders
    Route::get('/orders',                [AdminController::class, 'orders'])->name('orders');
    Route::get('/orders/{id}',           [AdminController::class, 'orderDetail'])->name('order.detail');
    Route::post('/orders/{id}/status',   [AdminController::class, 'updateStatus'])->name('order.status');

    // Payments
    Route::get('/payments',              [AdminController::class, 'payments'])->name('payments');

    // Coupons
    Route::get('/coupons',               [AdminController::class, 'coupons'])->name('coupons');
    Route::get('/coupons/create',        [AdminController::class, 'createCoupon'])->name('coupon.create');
    Route::post('/coupons',              [AdminController::class, 'storeCoupon'])->name('coupon.store');
    Route::get('/coupons/{id}/edit',     [AdminController::class, 'editCoupon'])->name('coupon.edit');
    Route::put('/coupons/{id}',          [AdminController::class, 'updateCoupon'])->name('coupon.update');
    Route::delete('/coupons/{id}',       [AdminController::class, 'deleteCoupon'])->name('coupon.delete');

    // Banners
    Route::get('/banners',               [AdminController::class, 'banners'])->name('banners');
    Route::get('/banners/create',        [AdminController::class, 'createBanner'])->name('banner.create');
    Route::post('/banners',              [AdminController::class, 'storeBanner'])->name('banner.store');
    Route::get('/banners/{id}/edit',     [AdminController::class, 'editBanner'])->name('banner.edit');
    Route::put('/banners/{id}',          [AdminController::class, 'updateBanner'])->name('banner.update');
    Route::post('/banners/{id}/toggle',  [AdminController::class, 'toggleBanner'])->name('banner.toggle');
    Route::delete('/banners/{id}',       [AdminController::class, 'deleteBanner'])->name('banner.delete');

    // Stock
    Route::get('/stock',                 [AdminController::class, 'stock'])->name('stock');
    Route::get('/stock/{id}/add',        [AdminController::class, 'addStockForm'])->name('stock.add');
    Route::post('/stock/{id}',           [AdminController::class, 'addStock'])->name('stock.store');
    Route::get('/stock-history',         [AdminController::class, 'stockHistory'])->name('stock.history');

    // Users
    Route::get('/users',                        [AdminController::class, 'users'])->name('users');
    Route::post('/users/{id}/toggle',           [AdminController::class, 'toggleUser'])->name('user.toggle');
    Route::post('/users/{id}/promote',          [AdminController::class, 'promoteUser'])->name('user.promote');
    Route::post('/users/{id}/reset-password',   [AdminController::class, 'resetUserPassword'])->name('user.reset-password');
    Route::delete('/users/{id}',                [AdminController::class, 'deleteUser'])->name('user.delete');

    // Reviews
    Route::get('/reviews',               [AdminController::class, 'reviews'])->name('reviews');
    Route::post('/reviews/{id}/approve', [AdminController::class, 'approveReview'])->name('review.approve');
    Route::delete('/reviews/{id}',       [AdminController::class, 'deleteReview'])->name('review.delete');

    // Reports
    Route::get('/reports',               [ReportController::class, 'index'])->name('reports');
    Route::get('/reports/excel',         [ReportController::class, 'exportExcel'])->name('reports.excel');

    // Settings
    Route::get('/settings',              [AdminSettingController::class, 'index'])->name('settings');
    Route::post('/settings/password',    [AdminSettingController::class, 'changePassword'])->name('settings.password');
});