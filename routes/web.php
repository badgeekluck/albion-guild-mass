<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuildController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevLoginController;
use App\Http\Controllers\ItemIconController;
use App\Http\Controllers\PartyClaimController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\RegearController;
use App\Http\Controllers\RegearHistoryController;
use App\Http\Controllers\RegearSettingsController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Item icons, cached locally on first request (public/item-icons/*.png is then served by nginx).
Route::get('/item-icons/{uniqueName}.png', ItemIconController::class)->name('item-icon');

// --- Auth ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'redirect'])->name('login');
    Route::get('/auth/discord/callback', [AuthController::class, 'callback'])->name('auth.discord.callback');

    // Local testing only (APP_ENV=local + DEV_LOGIN_ENABLED=true), 404 otherwise.
    Route::get('/dev/login', [DevLoginController::class, 'index'])->name('dev.login');
    Route::post('/dev/login', [DevLoginController::class, 'store'])->name('dev.login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// --- Guild members ---
Route::middleware('auth')->group(function () {
    Route::prefix('/go/{link}')->name('party.')->group(function () {
        Route::get('/', [PartyController::class, 'show'])->name('show');
        Route::get('/board', [PartyController::class, 'board'])->name('board');
        Route::post('/join', [PartyController::class, 'join'])->name('join')->middleware('throttle:party-signup');
        Route::post('/leave', [PartyController::class, 'leave'])->name('leave')->middleware('throttle:party-signup');
        Route::post('/move', [PartyController::class, 'move'])->name('move')->middleware('throttle:party-arrange');
        Route::post('/slots', [PartyController::class, 'updateExtraSlots'])->name('slots')->middleware('throttle:party-arrange');
        Route::post('/ping', [PartyController::class, 'pingRoster'])->name('ping')->middleware('throttle:party-ping');
        Route::post('/situation', [PartyController::class, 'setSituation'])->name('situation')->middleware('throttle:party-arrange');
        Route::post('/registration', [PartyController::class, 'registration'])->name('registration')->middleware('throttle:party-arrange');
        Route::post('/claim', [PartyClaimController::class, 'claim'])->name('claim')->middleware('throttle:party-signup');

        Route::get('/activity', [PartyController::class, 'activity'])->name('activity');
        Route::get('/regear', [RegearController::class, 'show'])->name('regear');
        Route::post('/regear/window', [RegearController::class, 'window'])->name('regear.window');
        Route::post('/regear/rules', [RegearController::class, 'rules'])->name('regear.rules');
        Route::post('/regear/scan', [RegearController::class, 'scan'])->name('regear.scan');
        Route::post('/regear/approve-clean', [RegearController::class, 'approveClean'])->name('regear.approve-clean');
        Route::post('/regear/paid', [RegearController::class, 'markPaid'])->name('regear.paid');
        Route::post('/regear/notify', [RegearController::class, 'notify'])->name('regear.notify')->middleware('throttle:party-ping');
        Route::post('/regear/{claim}', [RegearController::class, 'decide'])->name('regear.decide');
        Route::post('/release', [PartyClaimController::class, 'release'])->name('release')->middleware('throttle:party-signup');
        Route::post('/claims/{attendee}/confirm', [PartyClaimController::class, 'confirm'])->name('claims.confirm')->middleware('throttle:party-arrange');
        Route::post('/claims/{attendee}/reject', [PartyClaimController::class, 'reject'])->name('claims.reject')->middleware('throttle:party-arrange');
        Route::post('/attendees/{attendee}/bench', [PartyClaimController::class, 'bench'])->name('attendees.bench')->middleware('throttle:party-arrange');
        Route::post('/attendees/{attendee}/kick', [PartyClaimController::class, 'kick'])->name('attendees.kick')->middleware('throttle:party-arrange');
        Route::post('/save-template', [PartyController::class, 'saveAsTemplate'])->name('save-template');
        Route::post('/apply-template', [PartyController::class, 'applyTemplate'])->name('apply-template')->middleware('throttle:party-arrange');
        Route::post('/attendance/{attendee}', [PartyController::class, 'toggleAttendance'])->name('attendance');
    });

    Route::get('/dashboard/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/dashboard/attendance/{user}', [AttendanceController::class, 'show'])->name('attendance.show');

    // Allowed for the creator/admin via policy (a creator may have lost the staff role).
    Route::post('/dashboard/archive/{link}', [DashboardController::class, 'archive'])->name('dashboard.archive');
});

// --- Staff (admin / content-creator) ---
Route::middleware(['auth', 'staff'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/create', [DashboardController::class, 'store'])->name('dashboard.create');
    Route::delete('/dashboard/delete/{link}', [DashboardController::class, 'destroy'])->name('dashboard.delete');

    Route::resource('templates', TemplateController::class)->except(['show', 'create']);
    Route::post('/builds/import', [BuildController::class, 'import'])->name('builds.import');
    Route::post('/builds/{build}/duplicate', [BuildController::class, 'duplicate'])->name('builds.duplicate');

    Route::get('/admin/activity', [AdminController::class, 'activity'])->name('admin.activity');
    Route::get('/admin/server', [AdminController::class, 'server'])->name('admin.server');
    Route::get('/admin/server/stats', [AdminController::class, 'serverStats'])->name('admin.server.stats');

    Route::get('/regear/players', [RegearHistoryController::class, 'index'])->name('regear.players');
    Route::get('/regear/players/{name}', [RegearHistoryController::class, 'show'])->name('regear.player')->where('name', '[A-Za-z0-9]{1,50}');

    Route::get('/settings/regear', [RegearSettingsController::class, 'edit'])->name('settings.regear');
    Route::put('/settings/regear', [RegearSettingsController::class, 'update'])->name('settings.regear.update');
    Route::post('/settings/regear/guild', [RegearSettingsController::class, 'selectGuild'])->name('settings.regear.guild');
    Route::put('/settings/regear/gank-weapons', [RegearSettingsController::class, 'gankWeapons'])->name('settings.regear.gank');
    Route::resource('builds', BuildController::class)->except(['show']);
});
