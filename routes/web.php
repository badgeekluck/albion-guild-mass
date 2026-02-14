<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\AuthController;

Route::get('/login', [AuthController::class, 'redirect'])->name('login');
Route::get('/auth/discord/callback', [AuthController::class, 'callback']);
Route::get('/logout', [AuthController::class, 'logout']);

Route::middleware(['auth'])->group(function () {
    Route::get('/go/{slug}', [LinkController::class, 'handleRedirect']);
});

// Hızlı Test Rotası
Route::get('/test-link-yarat', function () {
    // Veritabanındaki ilk kullanıcıyı (seni) bulur
    $user = App\Models\User::first();

    // Google'a giden bir link oluşturur
    $link = App\Models\SharedLink::create([
        'creator_id' => $user->id,
        'destination_url' => 'https://www.google.com'
    ]);

    return "Linkin oluşturuldu! Kopyala ve test et: <br> <a href='/go/{$link->slug}'>" . url('/go/' . $link->slug) . "</a>";
});

Route::get('/', function () {
    return 'Ana Sayfa - Giriş yapıldıysa buradasın.';
});
