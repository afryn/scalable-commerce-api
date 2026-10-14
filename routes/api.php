<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\User\CartController;

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\User\OrderController;
use App\Http\Controllers\Api\RazorpayWebhookController;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\Api\PaymentCheckoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



// =========== ADMIN =====================
Route::prefix('admin')->group(function () {
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::group(['prefix' => 'inventory'], function () {
            Route::post('{product}/stock', [InventoryController::class, 'addStock']);
        });

        Route::apiResource('products', ProductController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('users', AdminUserController::class);
    });
    Route::post('register', [AdminController::class, 'register']);
    Route::post('login', [AdminController::class, 'login']);
});

// =========== USER =====================
Route::prefix('user')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('orders', OrderController::class);
        Route::apiResource('carts', CartController::class);

        //payment
        Route::post('payments/{orderId}/initiate', [PaymentController::class, 'initiate']);
    });
    Route::post('register', [UserController::class, 'register']);
    Route::post('login', [UserController::class, 'login']);
});

Route::get('test-checkout/{orderId}', [PaymentCheckoutController::class, 'show']);
Route::post('webhooks/razorpay', [RazorpayWebhookController::class, 'handle']);
