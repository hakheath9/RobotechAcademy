<?php

use App\Http\Controllers\API\ApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;

Route::post('/chatbot', [ChatbotController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| API Routes — Robotech Academy
|--------------------------------------------------------------------------
| Base URL: http://localhost:8000/api
|
| Auth header: Authorization: Bearer {token}
|--------------------------------------------------------------------------
*/

// ════════════════════════════════════════════════════════
//  PUBLIC — No authentication required
// ════════════════════════════════════════════════════════

Route::post('/login',              [ApiController::class, 'login']);
Route::post('/register',           [ApiController::class, 'register']);
Route::get('/products',            [ApiController::class, 'products']);
Route::get('/products/{id}',       [ApiController::class, 'product']);
Route::get('/categories',          [ApiController::class, 'categories']);
Route::get('/brands',              [ApiController::class, 'brands']);
Route::get('/banners',             [ApiController::class, 'banners']);

// ════════════════════════════════════════════════════════
//  CUSTOMER — Bearer token required
//  Header: Authorization: Bearer {token}
// ════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout',             [ApiController::class, 'logout']);
    Route::get('/me',                  [ApiController::class, 'me']);

    // Cart
    Route::get('/cart',                [ApiController::class, 'cart']);
    Route::post('/cart',               [ApiController::class, 'addToCart']);
    Route::put('/cart/{id}',           [ApiController::class, 'updateCart']);
    Route::delete('/cart/{id}',        [ApiController::class, 'removeFromCart']);

    // Wishlist
    Route::get('/wishlist',            [ApiController::class, 'wishlist']);
    Route::post('/wishlist',           [ApiController::class, 'toggleWishlist']);

    // Coupon
    Route::post('/coupon/apply',       [ApiController::class, 'applyCoupon']);

    // Orders
    Route::get('/my-orders',           [ApiController::class, 'myOrders']);
    Route::get('/my-orders/{id}',      [ApiController::class, 'orderDetail']);
    Route::post('/orders',             [ApiController::class, 'placeOrder']);

    // Reviews
    Route::post('/products/{id}/review', [ApiController::class, 'storeReview']);
});

// ════════════════════════════════════════════════════════
//  ADMIN — Admin Bearer token required
//  The admin middleware checks isAdmin() on the user
// ════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    // Dashboard
    Route::get('/dashboard',                  [ApiController::class, 'adminDashboard']);

    // Products
    Route::post('/products',                  [ApiController::class, 'storeProduct']);
    Route::put('/products/{id}',              [ApiController::class, 'updateProduct']);
    Route::delete('/products/{id}',           [ApiController::class, 'deleteProduct']);

    // Categories
    Route::post('/categories',                [ApiController::class, 'storeCategory']);
    Route::put('/categories/{id}',            [ApiController::class, 'updateCategory']);
    Route::delete('/categories/{id}',         [ApiController::class, 'deleteCategory']);

    // Brands
    Route::post('/brands',                    [ApiController::class, 'storeBrand']);
    Route::put('/brands/{id}',                [ApiController::class, 'updateBrand']);
    Route::delete('/brands/{id}',             [ApiController::class, 'deleteBrand']);

    // Orders
    Route::get('/orders',                     [ApiController::class, 'allOrders']);
    Route::put('/orders/{id}/status',         [ApiController::class, 'updateOrderStatus']);

    // Users
    Route::get('/users',                      [ApiController::class, 'allUsers']);

    // Stock
    Route::get('/stock',                      [ApiController::class, 'stockData']);
    Route::post('/stock/{id}',                [ApiController::class, 'addStock']);
    Route::get('/stock-history',              [ApiController::class, 'stockHistory']);

    // Coupons
    Route::get('/coupons',                    [ApiController::class, 'allCoupons']);
    Route::post('/coupons',                   [ApiController::class, 'storeCoupon']);
    Route::delete('/coupons/{id}',            [ApiController::class, 'deleteCoupon']);

    // Reviews
    Route::get('/reviews',                    [ApiController::class, 'allReviews']);
    Route::put('/reviews/{id}/approve',       [ApiController::class, 'approveReview']);
    Route::delete('/reviews/{id}',            [ApiController::class, 'deleteReview']);

    // Payments
    Route::get('/payments',                   [ApiController::class, 'allPayments']);
    Route::put('/payments/{id}',              [ApiController::class, 'updatePayment']);

    // Banners
    Route::post('/banners',                   [ApiController::class, 'storeBanner']);
    Route::delete('/banners/{id}',            [ApiController::class, 'deleteBanner']);
});