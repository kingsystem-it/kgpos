<?php

use Illuminate\Support\Facades\Route;



Route::middleware(['auth.basic', 'permission:kds:view'])
    ->get('/kds', fn() => view('kds.index'));

Route::middleware(['auth.basic', 'permission:settings:view'])
    ->get('/admin/ping', fn() => response()->json([
        'ok' => true,
        'user' => auth()->user()->only(['id', 'name', 'email']),
        'ts' => now()->toIso8601String(),
    ]));

Route::middleware(['auth.basic', 'permission:pos:open_tab'])
    ->get('/pos', fn() => view('pos.index'));
