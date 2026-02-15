<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// 1. Ana Sayfa (Landing Page)
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/login', [AuthController::class, 'redirect'])->name('login');
Route::get('/auth/discord/callback', [AuthController::class, 'callback']);
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {

    // --- Party Link İşlemleri ---
    Route::get('/go/{slug}', [LinkController::class, 'showParty'])->name('party.show');
    Route::post('/go/{slug}/join', [LinkController::class, 'joinParty'])->name('party.join');
    Route::post('/go/{slug}/move', [LinkController::class, 'moveMember'])->name('party.move');

    // --- Dashboard ---
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/create', [DashboardController::class, 'createLink'])->name('dashboard.create');
    Route::delete('/dashboard/delete/{id}', [DashboardController::class, 'deleteLink'])->name('dashboard.delete');

    // --- Templates ---
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('/templates/{id}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::put('/templates/{id}', [TemplateController::class, 'update'])->name('templates.update');
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])->name('templates.destroy');

});

Route::get('/test-link-yarat', function () {
    $user = App\Models\User::first();
    if(!$user) return "Henüz kullanıcı yok!";

    $link = App\Models\SharedLink::create([
        'creator_id' => $user->id,
        'destination_url' => 'https://www.google.com' // Burası artık kullanılmıyor ama hata vermesin diye kalsın
    ]);

    return "Link oluşturuldu! <br> <a href='/go/{$link->slug}'>" . url('/go/' . $link->slug) . "</a>";
});
