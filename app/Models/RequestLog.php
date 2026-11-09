<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One page view or action of a logged-in user (see LogRequests). */
class RequestLog extends Model
{
    public const UPDATED_AT = null;

    public const KEEP_DAYS = 30;

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
        'is_mobile' => 'boolean',
    ];

    /** Human labels for the activity screen; unknown routes show their name. */
    public const LABELS = [
        'home' => 'Ana sayfa',
        'dashboard' => 'Dashboard',
        'dashboard.create' => 'Parti oluşturdu',
        'dashboard.archive' => 'Etkinliği bitirdi',
        'dashboard.delete' => 'Parti sildi',
        'party.show' => 'Parti ekranı',
        'party.join' => 'Partiye katıldı',
        'party.leave' => 'Partiden ayrıldı',
        'party.claim' => 'Slot claim etti',
        'party.release' => 'Slotu bıraktı',
        'party.move' => 'Oyuncu taşıdı',
        'party.slots' => 'Extra slot değiştirdi',
        'party.situation' => 'Durum değiştirdi',
        'party.registration' => 'Kayıt süresini değiştirdi',
        'party.ping' => 'Discord ping attı',
        'party.claims.confirm' => 'Claim onayladı',
        'party.claims.reject' => 'Claim reddetti',
        'party.attendees.bench' => 'Yedeğe aldı',
        'party.attendees.kick' => 'Partiden çıkardı',
        'party.attendance' => 'Katılım işaretledi',
        'party.save-template' => 'Kadroyu template yaptı',
        'party.apply-template' => 'Template uyguladı',
        'party.activity' => 'Parti activity log',
        'party.regear' => 'Regear ekranı',
        'party.regear.window' => 'Regear zamanı kaydetti',
        'party.regear.rules' => 'Regear kuralı değiştirdi',
        'party.regear.scan' => 'Killboard taraması başlattı',
        'party.regear.decide' => 'Regear kararı verdi',
        'party.regear.approve-clean' => 'Regear toplu onayladı',
        'party.regear.paid' => 'Regear ödendi işaretledi',
        'party.regear.notify' => 'Regear sonuçlarını Discord\'dan gönderdi',
        'templates.index' => 'Template listesi',
        'templates.edit' => 'Template düzenleme',
        'templates.store' => 'Template oluşturdu',
        'templates.update' => 'Template kaydetti',
        'templates.destroy' => 'Template sildi',
        'builds.index' => 'Build listesi',
        'builds.create' => 'Yeni build ekranı',
        'builds.edit' => 'Build düzenleme',
        'builds.store' => 'Build oluşturdu',
        'builds.update' => 'Build kaydetti',
        'builds.destroy' => 'Build sildi',
        'builds.duplicate' => 'Build kopyaladı',
        'builds.import' => 'Excel içe aktardı',
        'settings.regear' => 'Regear ayarları',
        'settings.regear.update' => 'Regear ayarlarını kaydetti',
        'settings.regear.guild' => 'Guild seçti',
        'settings.regear.gank' => 'Gank silahlarını kaydetti',
        'regear.players' => 'Regear geçmişi',
        'regear.player' => 'Oyuncu regear geçmişi',
        'attendance.index' => 'Attendance',
        'attendance.show' => 'Oyuncu attendance',
        'admin.activity' => 'Aktivite ekranı',
        'admin.server' => 'Sunucu durumu',
        'logout' => 'Çıkış yaptı',
    ];

    public function label(): string
    {
        return self::LABELS[$this->route] ?? ($this->route ?: $this->path);
    }

    public function isAction(): bool
    {
        return $this->method !== 'GET';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(SharedLink::class, 'shared_link_id')->withTrashed();
    }
}
