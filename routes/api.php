<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\KdsController;

Route::middleware(['auth.basic','permission:categories:manage'])->group(function () {
    Route::apiResource('categories', CategoryController::class);
});

Route::middleware(['auth.basic','permission:products:manage'])->group(function () {
    Route::apiResource('products', ProductController::class);
});

Route::middleware(['auth.basic','permission:pos:open_tab'])->group(function () {
    Route::post('orders', [OrderController::class,'store']);
    Route::get('orders/{order}', [OrderController::class,'show']);
    Route::post('orders/{order}/items', [OrderController::class,'addItem'])->middleware('permission:pos:add_item');
    Route::post('orders/{order}/send',  [OrderController::class,'send'])->middleware('permission:pos:send_to_kds');
});

Route::middleware(['auth.basic','permission:kds:view'])
    ->get('kds/queue', [KdsController::class,'queue']);

Route::middleware(['auth.basic','permission:kds:update_status'])->group(function () {
    Route::post('kds/items/{item}/prepared', [KdsController::class,'markPrepared']);
    Route::post('kds/items/{item}/served',   [KdsController::class,'markServed']);
});
