<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TemplateController;
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

Route::middleware(['auth'])->group(function () {
    Route::get('/go/{slug}', [LinkController::class, 'showParty']);

    Route::post('/go/{slug}/join', [LinkController::class, 'joinParty']);

    Route::post('/go/{slug}/move', [LinkController::class, 'moveMember']);

    // DASHBOARD ROTALARI
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/create', [DashboardController::class, 'createLink'])->name('dashboard.create');
    Route::delete('/dashboard/delete/{id}', [DashboardController::class, 'deleteLink'])->name('dashboard.delete');

    Route::get('/', function () {
        if (auth()->check() && in_array(auth()->user()->role, ['admin', 'content-creator'])) {
            return redirect()->route('dashboard');
        }
        return "Albion Party Builder - Giriş yapıldı ama yetkiniz yok.";
    });

    // TEMPLATE MANAGEMENT
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('/templates/{id}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::put('/templates/{id}', [TemplateController::class, 'update'])->name('templates.update');
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])->name('templates.destroy');
});

Route::get('/', function () {
    return 'Ana Sayfa - Giriş yapıldıysa buradasın.';
});
