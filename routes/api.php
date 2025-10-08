<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\KdsController;
// --- SANITY CHECK (temporário) ---
//Route::get('ping-open', fn() => response()->json(['ok'=>true, 'file'=>'routes/api.php']));

Route::middleware('auth.basic')->group(function () {
    // Categories
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::put('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

    // Products
    Route::get('products', [ProductController::class, 'index']);
    Route::post('products', [ProductController::class, 'store']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);

    // Open Orders (comandas abertas)
    Route::get('orders/open', [OrderController::class, 'open']);

    // Orders
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/items', [OrderController::class, 'addItem']);
    Route::post('orders/{order}/send',  [OrderController::class, 'send']);

    // KDS
    Route::get('kds/queue', [KdsController::class, 'queue']);
    Route::post('kds/items/{item}/prepared', [KdsController::class, 'markPrepared']);
    Route::post('kds/items/{item}/served',   [KdsController::class, 'markServed']);
});
