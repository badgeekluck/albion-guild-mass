<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BuildController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\AdminCheck;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/login', [AuthController::class, 'redirect'])->name('login');
Route::get('/auth/discord/callback', [AuthController::class, 'callback']);
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');


Route::middleware(['auth'])->group(function () {

    Route::get('/go/{slug}', [LinkController::class, 'showParty'])->name('party.show');
    Route::post('/go/{slug}/join', [LinkController::class, 'joinParty'])->name('party.join');
    Route::post('/go/{slug}/leave', [LinkController::class, 'leaveParty'])->name('party.leave');
    Route::post('/go/{slug}/move', [LinkController::class, 'moveMember'])->name('party.move');

    Route::post('/go/{slug}/slots', [LinkController::class, 'updateExtraSlots'])
        ->name('party.slots')
        ->middleware('auth');

    Route::get('/dashboard/attendance', [AttendanceController::class, 'index'])
        ->middleware(['auth'])
        ->name('attendance.index');

    Route::get('/dashboard/attendance/{id}', [AttendanceController::class, 'show'])->name('attendance.show');

    Route::post('/dashboard/archive/{id}', [DashboardController::class, 'archiveLink'])->name('dashboard.archive');

});

Route::middleware(['auth', AdminCheck::class])->group(function () {

    // --- DASHBOARD ---
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/create', [DashboardController::class, 'createLink'])->name('dashboard.create');
    Route::delete('/dashboard/delete/{id}', [DashboardController::class, 'deleteLink'])->name('dashboard.delete');

    // --- TEMPLATES ---
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('/templates/{id}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::put('/templates/{id}', [TemplateController::class, 'update'])->name('templates.update');
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])->name('templates.destroy');

    Route::middleware(['auth', AdminCheck::class])->group(function () {
        Route::resource('builds', BuildController::class);
    });

});


Route::get('/test-link-yarat', function () {
    $user = App\Models\User::first();
    if(!$user) return "Henüz kullanıcı yok!";

    $link = App\Models\SharedLink::create([
        'creator_id' => $user->id,
        'destination_url' => 'https://www.google.com',
        'title' => 'Test Party Link'
    ]);

    return "Link oluşturuldu! <br> <a href='/go/{$link->slug}'>" . url('/go/' . $link->slug) . "</a>";
});
