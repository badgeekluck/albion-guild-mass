<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LinkController;

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // 2. Link İşlemleri
    Route::get('/links', [LinkController::class, 'index']);
    Route::post('/create-link', [LinkController::class, 'store']);

});
