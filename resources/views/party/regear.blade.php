@php
    use App\Models\RegearClaim;
    $roleLabels = ['tank' => '🛡️ Tank', 'heal' => '🩹 Healer', 'supp' => '🖐️ Support', 'dps' => '⚔️ DPS', 'mount' => '🐘 Mount', 'any' => '⚪ Flex'];
    $matchColors = ['ok' => 'var(--ok)', 'alt' => 'var(--info)', 'wrong' => 'var(--bad)', 'missing' => '#4b5563', 'unchecked' => 'var(--line)'];
    $matchText = ['ok' => 'doğru', 'alt' => 'alternatif / swap', 'wrong' => 'yanlış item', 'missing' => 'boş', 'unchecked' => 'kontrol edilmedi'];
    $hasWindow = $link->starts_at && $link->ends_at;
    $scanFailed = str_starts_with((string) $link->regear_scan_status, 'failed');
    $scanned = $link->regear_scanned_at !== null && ! $scanning;
    $payouts = collect($rules['payouts'])->filter()->map(fn ($v, $k) => ($roleLabels[$k] ?? $k).' '.RegearClaim::formatSilver($v));
    $slotShort = ['weapon' => 'silah', 'head' => 'kafa', 'armor' => 'göğüs', 'shoe' => 'ayakkabı'];
    $tierText = 'Min T'.$rules['min_tier']['default'].collect(\App\Models\SharedLink::TIER_ROLES)->map(function ($role) use ($rules, $roleLabels, $slotShort) {
        $r = $rules['min_tier'][$role];
        $parts = collect($slotShort)->filter(fn ($l, $slot) => $r[$slot])->map(fn ($l, $slot) => $l.' T'.$r[$slot]);
        if ($r['default']) $parts->prepend('T'.$r['default']);

        return $parts->isEmpty() ? null : ' · '.$roleLabels[$role].' '.$parts->implode(', ');
    })->filter()->implode('');
    $statusLabels = ['pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi'];
    $tabs = ['pending' => 'Bekleyen', 'approved' => 'Onaylı', 'rejected' => 'Reddedilen', 'all' => 'Tümü'];
    $tabCounts = ['pending' => $totals['pending'], 'approved' => $totals['approved'], 'rejected' => $totals['rejected'], 'all' => $claims->count()];
    $unpaidPlayers = $claims->where('status', RegearClaim::APPROVED)->whereNull('paid_at')->pluck('victim_name')->unique()->count();
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @if($scanning)<meta http-equiv="refresh" content="5">@endif
    <title>Regear · {{ $link->title }}</title>
    @include('partials.ui')
    <style>
        .death { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 16px 18px; margin-bottom: 12px; }
        .death.approved { border-color: rgba(52, 211, 153, .35); }
        .death.rejected { opacity: .7; }
        .death-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
        .who { display: flex; gap: 12px; align-items: center; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--card-2); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; }
        .name { font-weight: 700; font-size: 16px; }
        .meta { color: var(--muted); font-size: 13px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 2px; }
        .verdict { margin: 12px 0; }
        .issues { background: rgba(251, 191, 36, .07); border: 1px solid rgba(251, 191, 36, .25); border-radius: 10px; padding: 10px 12px; color: #fde68a; font-size: 13px; }
        .issues ul { margin: 6px 0 0; padding-left: 18px; }
        .gear { display: flex; gap: 10px; flex-wrap: wrap; }
        .slot { width: 64px; text-align: center; font-size: 11px; color: var(--muted); }
        .slot-box { width: 58px; height: 58px; border-radius: 10px; border: 2px solid var(--line); background: var(--bg); margin: 0 auto 4px; position: relative; display: flex; align-items: center; justify-content: center; }
        .slot-box img { width: 54px; height: 54px; }
        .tier { position: absolute; bottom: -6px; right: -6px; background: var(--card-2); border: 1px solid var(--line); border-radius: 6px; font-size: 10px; font-weight: 700; padding: 0 4px; color: var(--text); }
        .tier.low { background: var(--bad); border-color: var(--bad); color: #fff; }
        .expected { font-size: 10px; color: var(--muted); line-height: 1.2; margin-top: 2px; }
        .expected img { width: 18px; height: 18px; vertical-align: middle; }
        .decide { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); }
        .amount { width: 110px; text-align: right; font-weight: 700; }
        .tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
        .tab { padding: 8px 14px; border-radius: 999px; border: 1px solid var(--line); color: var(--text); font-size: 13px; }
        .tab.active { background: rgba(124, 131, 255, .15); border-color: var(--accent); }
        .tab .count { color: var(--muted); margin-left: 4px; }
        .paybar { position: fixed; left: 0; right: 0; bottom: 0; background: rgba(15, 20, 32, .96); border-top: 1px solid var(--line); padding: 12px 20px; backdrop-filter: blur(6px); }
        .paybar-inner { max-width: 1180px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .big { font-size: 20px; font-weight: 800; }
        .empty { text-align: center; color: var(--muted); padding: 36px 10px; }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>💰 Regear — {{ $link->title }}</h1>
            <div class="subtitle">Etkinlikte ölen oyuncular killboard'dan getirilir, giymesi gereken build ile karşılaştırılır. Son karar senin.</div>
        </div>
        <div class="links">
            <a href="{{ route('settings.regear') }}">⚙️ Regear Ayarları</a>
            <a href="{{ route('party.show', $link) }}">⬅ Partiye dön</a>
        </div>
    </div>

    @if(session('success'))<div class="notice ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="notice bad">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="notice bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    @unless($configured)
        <div class="notice warn">Hangi guild'in ölümlerinin getirileceği henüz seçilmedi. <a href="{{ route('settings.regear') }}">Regear Ayarları → Guild seç</a></div>
    @endunless

    {{-- 1. Event window --}}
    <div class="card">
        <div class="card-title"><span class="step {{ $hasWindow ? 'done' : '' }}">{{ $hasWindow ? '✓' : '1' }}</span> Etkinlik ne zaman oldu?</div>
        @if($hasWindow)
            <p class="card-hint" style="margin-bottom: 8px;">
                <b data-utc="{{ $link->starts_at->toIso8601ZuluString() }}" data-format="datetime"></b> –
                <b data-utc="{{ $link->ends_at->toIso8601ZuluString() }}" data-format="time"></b>
                <span class="muted">(senin saatinle · UTC {{ $link->starts_at->utc()->format('H:i') }}–{{ $link->ends_at->utc()->format('H:i') }} · en az {{ $link->minBattleSize() }} kişilik savaşlar{{ $link->regear_include_alliance ? ' · alliance dahil' : '' }})</span>
            </p>
        @else
            <p class="card-hint">Saati kendi saatinle gir, UTC'ye otomatik çevrilir. Sadece bu aralıktaki ölümler getirilir.</p>
        @endif

        <details @if(! $hasWindow) open @endif>
            @if($hasWindow)<summary>Değiştir</summary>@endif
            <form method="POST" action="{{ route('party.regear.window', $link) }}" id="windowForm" style="margin-top: 12px;">
                @csrf
                <input type="hidden" name="starts_at" id="startsAtUtc" value="{{ $link->starts_at?->toIso8601ZuluString() }}">
                <input type="hidden" name="ends_at" id="endsAtUtc" value="{{ $link->ends_at?->toIso8601ZuluString() }}">
                <div class="row">
                    <div class="field">
                        <label for="startLocal">Başlangıç</label>
                        <input type="datetime-local" id="startLocal" required>
                    </div>
                    <div class="field">
                        <label>Süre</label>
                        <div class="chips" id="durationChips">
                            @foreach([1, 1.5, 2, 3, 4] as $hours)
                                <button type="button" class="chip" data-hours="{{ $hours }}">{{ str_replace('.', ',', (string) $hours) }} saat</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="field">
                        <label for="endLocal">Bitiş</label>
                        <input type="datetime-local" id="endLocal" required>
                    </div>
                    <div class="field">
                        <label for="minBattleSize">Min. savaş büyüklüğü</label>
                        <input type="number" id="minBattleSize" name="min_battle_size" min="2" max="2000" style="width: 130px;"
                               value="{{ $link->regear_min_battle_size }}" placeholder="{{ $link->maxSlots() }} (parti)">
                    </div>
                    <button type="submit" class="btn primary">Kaydet</button>
                </div>
                <div class="muted" id="utcPreview" style="margin-top: 8px; font-size: 13px;"></div>
                <label style="display:flex; align-items:center; gap:8px; margin-top: 10px; font-size: 13px; cursor:pointer;">
                    <input type="checkbox" name="include_alliance" value="1" @checked($link->regear_include_alliance)>
                    🤝 Alliance üyelerini de dahil et
                    <span class="muted" style="font-size: 12px;">— alliance'taki diğer guild'lerin, bu partiye kayıtlı oyuncularının ölümleri de getirilir</span>
                </label>
                @if($link->regear_include_alliance)
                    <div class="muted" style="margin-top: 4px; font-size: 12px;">
                        @if($alliance === null)
                            Alliance bilgisi şu an alınamadı; tarama sırasında tekrar denenecek.
                        @elseif($alliance['id'] === null)
                            <span style="color: var(--warn);">Guild şu an bir alliance'ta değil; sadece guild taranır.</span>
                        @else
                            Alliance [{{ $alliance['tag'] }}]{{ $alliance['name'] ? ' '.$alliance['name'] : '' }}: {{ collect($alliance['guilds'])->pluck('name')->implode(', ') }}
                        @endif
                    </div>
                @endif
                <div class="muted" style="margin-top: 4px; font-size: 12px;">Bu sayıdan az oyunculu (iki taraf dahil) savaşlardaki ölümler getirilmez; open world gankleri böyle elenir. Boş = parti büyüklüğü ({{ $link->maxSlots() }}).</div>
            </form>
        </details>
    </div>

    {{-- 2. Scan --}}
    <div class="card">
        <div class="card-title"><span class="step {{ $scanned && ! $scanFailed ? 'done' : '' }}">{{ $scanned && ! $scanFailed ? '✓' : '2' }}</span> Ölümleri getir</div>
        <p class="card-hint">Event bittikten birkaç dakika sonra çalıştır (killboard birkaç dakika gecikmeli güncellenir). Tekrar çalıştırmak güvenli: verdiğin kararlar korunur.</p>
        <div class="row" style="align-items: center;">
            <form method="POST" action="{{ route('party.regear.scan', $link) }}" style="margin: 0;">
                @csrf
                <button type="submit" class="btn primary" @disabled($scanning || ! $configured || ! $hasWindow)>
                    {{ $scanning ? '⏳ Getiriliyor…' : ($scanned ? '🔄 Tekrar getir' : '⬇️ Killboard\'dan ölümleri getir') }}
                </button>
            </form>
            <div class="muted">
                @if($scanning)
                    Büyük savaşlarda bu biraz sürebilir; sayfa kendini yeniliyor.
                @elseif($scanFailed)
                    <span style="color: var(--bad);">Getirilemedi: {{ \Illuminate\Support\Str::after($link->regear_scan_status, 'failed: ') }}</span>
                @elseif($scanned)
                    @php [$scanSummary, $scanWarning] = array_pad(explode(' ⚠️ ', \Illuminate\Support\Str::after($link->regear_scan_status, 'done: '), 2), 2, null); @endphp
                    Son: {{ $link->regear_scanned_at->locale('tr')->diffForHumans() }} · {{ $scanSummary }}
                    @if($scanWarning)<div style="color: var(--warn); margin-top: 4px;">⚠️ {{ $scanWarning }}</div>@endif
                @elseif(! $hasWindow)
                    Önce etkinlik zamanını kaydet.
                @endif
            </div>
        </div>
    </div>

    {{-- 3. Review --}}
    <div class="card">
        <div class="card-title"><span class="step">3</span> İncele ve öde</div>
        <p class="card-hint" style="margin-bottom: 6px;">
            {{ $link->hasRegearOverride() ? 'Bu event için özel kurallar:' : 'Guild varsayılanları:' }}
            <b>{{ $payouts->isEmpty() ? 'payout girilmemiş' : $payouts->implode(' · ') }}</b> · {{ $tierText }}
            @if($rules['limits']['max_per_player']) · oyuncu başına en fazla {{ $rules['limits']['max_per_player'] }} @endif
            @if($rules['limits']['min_item_power']) · min {{ $rules['limits']['min_item_power'] }} IP @endif
        </p>
        @if($mainEnemies)
            <p class="card-hint" style="margin-bottom: 6px;">⚔️ Ana rakip:
                @foreach($mainEnemies as $enemy)<b>{{ $enemy['label'] }}</b> <span class="muted">({{ $enemy['kills'] }} kill)</span>@if(! $loop->last) · @endif @endforeach
                <span class="muted">— başkasının öldürdüğü ölümler işaretlenir.</span>
            </p>
        @endif
        <details>
            <summary>Bu event için farklı tutar / tier kullan</summary>
            <form method="POST" action="{{ route('party.regear.rules', $link) }}" style="margin-top: 12px;">
                @csrf
                <div class="grid">
                    @foreach(\App\Models\SharedLink::REGEAR_TYPES as $type)
                        <div class="field">
                            <label>{{ $roleLabels[$type] }}</label>
                            <input type="text" name="payouts[{{ $type }}]" style="width:100%;" value="{{ $rules['payouts'][$type] ? RegearClaim::formatSilver($rules['payouts'][$type]) : '' }}" placeholder="0">
                        </div>
                    @endforeach
                </div>
                <div style="margin-top: 16px;">
                    @include('partials.tier-matrix', ['minTier' => $rules['min_tier']])
                </div>
                <div style="margin-top: 16px;">
                    @include('partials.regear-limits', ['limits' => $rules['limits']])
                </div>
                <div class="row" style="margin-top: 12px;">
                    <button type="submit" class="btn primary">Bu event için kaydet</button>
                    @if($link->hasRegearOverride())
                        <button type="submit" name="use_defaults" value="1" class="btn ghost">Guild varsayılanlarına dön</button>
                    @endif
                </div>
            </form>
        </details>
    </div>

    @if($claims->isNotEmpty())
        <div class="tabs">
            @foreach($tabs as $key => $label)
                <a href="{{ route('party.regear', [$link, 'tab' => $key]) }}" class="tab {{ $tab === $key ? 'active' : '' }}">{{ $label }}<span class="count">{{ $tabCounts[$key] }}</span></a>
            @endforeach
        </div>

        @if($tab === 'pending' && $cleanCount > 0)
            <div class="notice ok" style="display:flex; justify-content:space-between; align-items:center; gap: 10px; flex-wrap: wrap;">
                <span>✨ <b>{{ $cleanCount }}</b> ölüm sorunsuz görünüyor (doğru build, yeterli tier).</span>
                <form method="POST" action="{{ route('party.regear.approve-clean', $link) }}" style="margin:0;">
                    @csrf <button type="submit" class="btn ok sm">Hepsini önerilen tutarla onayla</button>
                </form>
            </div>
        @endif
    @endif

    @forelse($visibleClaims as $claim)
        <div class="death {{ $claim->status }}">
            <div class="death-head">
                <div class="who">
                    <div class="avatar">{{ mb_strtoupper(mb_substr($claim->victim_name, 0, 1)) }}</div>
                    <div>
                        <div class="name">{{ $claim->victim_name }}@if($claim->victim_guild_id && $guildId && $claim->victim_guild_id !== $guildId) <span class="pill info" style="font-size: 11px;" title="Alliance üyesi">🤝 {{ $claim->victim_guild }}</span>@endif</div>
                        <div class="meta">
                            @if($claim->slot_index)
                                <span class="pill info" title="Öldüğü anda bulunduğu slot">Slot {{ $claim->slot_index }} · {{ $roleLabels[$claim->role_type] ?? 'Flex' }}</span>
                            @elseif($claim->attendee)
                                <span class="pill warn">Waitlist</span>
                            @else
                                <span class="pill warn">Roster'da yok</span>
                            @endif
                            <span>🕒 <span data-utc="{{ $claim->died_at->toIso8601ZuluString() }}" data-format="time"></span></span>
                            @if($claim->killer_name)
                                <span style="display:inline-flex; align-items:center; gap: 4px;">🗡️
                                    @if($claim->killer_weapon)<img src="/item-icons/{{ $claim->killer_weapon }}.png" alt="" style="width: 20px; height: 20px;" loading="lazy" onerror="this.remove()">@endif
                                    {{ $claim->killer_name }}@if($claim->killer_guild) · {{ $claim->killer_guild }}@endif @if($claim->killer_alliance)<span class="muted">[{{ $claim->killer_alliance }}]</span>@endif
                                </span>
                            @endif
                            @if($claim->battle_size)<span>👥 {{ $claim->battle_size }} kişilik savaş</span>@endif
                            @if($past = $history[mb_strtolower($claim->victim_name)] ?? null)
                                <a href="{{ route('regear.player', $claim->victim_name) }}" class="pill {{ $past['rejected'] ? 'warn' : '' }}" style="text-decoration: none;"
                                   title="Diğer event'lerde: {{ $past['events'] }} event, {{ $past['deaths'] }} ölüm, {{ $past['approved'] }} onay, {{ $past['rejected'] }} red">📒 Geçmiş: {{ $past['deaths'] }} ölüm · {{ $past['approved'] }} onay{{ $past['rejected'] ? ' · '.$past['rejected'].' red' : '' }} · {{ RegearClaim::formatSilver($past['silver']) }}</a>
                            @endif
                        </div>
                    </div>
                </div>
                <div>
                    @if($claim->paid_at)
                        <span class="pill info">💸 Ödendi · {{ RegearClaim::formatSilver($claim->payable()) }}</span>
                    @elseif($claim->status === RegearClaim::APPROVED)
                        <span class="pill ok">✓ Onaylandı · {{ RegearClaim::formatSilver($claim->payable()) }}</span>
                    @elseif($claim->status === RegearClaim::REJECTED)
                        <span class="pill bad">Reddedildi</span>
                    @else
                        <span class="pill">Karar bekliyor</span>
                    @endif
                </div>
            </div>

            @foreach($elsewhere[$claim->event_id] ?? [] as $other)
                <div class="notice {{ $other->status === 'approved' ? 'bad' : 'warn' }}" style="margin: 10px 0 0; padding: 8px 12px; font-size: 13px;">
                    🔁 Bu ölüm <a href="{{ route('party.regear', $other->link) }}">{{ $other->link?->title }}</a> partisinde de var
                    @if($other->paid_at) — <b>orada ödendi</b>, burada onaylanamaz.
                    @elseif($other->status === 'approved') — <b>orada onaylandı</b>, burada onaylanamaz.
                    @else <span>(orada henüz karar verilmedi).</span>
                    @endif
                </div>
            @endforeach

            <div class="verdict">
                @if(empty($claim->warnings))
                    <span class="pill ok">✓ Build doğru, tier yeterli</span>
                @else
                    <div class="issues">
                        ⚠️ <b>{{ count($claim->warnings) }} konuya bak</b>
                        <ul>@foreach($claim->warnings as $warning)<li>{{ $warning }}</li>@endforeach</ul>
                    </div>
                @endif
            </div>

            <div class="gear">
                @foreach((array) $claim->checks as $check)
                    @php $slotLabel = \App\Services\Regear\RegearEvaluator::SLOT_LABELS_TR[$check['slot']] ?? $check['label']; @endphp
                    <div class="slot" title="{{ $slotLabel }}: {{ $matchText[$check['match']] ?? '' }}{{ $check['expected'] ? ' · beklenen '.$check['expected'] : '' }}">
                        <div class="slot-box" style="border-color: {{ $matchColors[$check['match']] ?? 'var(--line)' }};">
                            @if($check['actual'])
                                <img src="/item-icons/{{ $check['actual'] }}.png" alt="" loading="lazy" onerror="this.remove()">
                                <span class="tier {{ $check['tier_ok'] === false ? 'low' : '' }}" @if(! empty($check['min_tier'])) title="En az T{{ $check['min_tier'] }}" @endif>{{ $check['actual_tier'] }}</span>
                            @else
                                <span class="muted" style="font-size: 20px;">∅</span>
                            @endif
                        </div>
                        {{ $slotLabel }}
                        @if(in_array($check['match'], ['wrong', 'missing'], true) && $check['expected'])
                            <div class="expected">
                                beklenen
                                @if($check['expected_icon'])<img src="/item-icons/{{ $check['expected_icon'] }}.png" alt="" loading="lazy" onerror="this.remove()">@endif
                                <br>{{ $check['expected'] }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @unless($claim->paid_at)
                <form method="POST" action="{{ route('party.regear.decide', [$link, $claim]) }}" class="decide">
                    @csrf
                    @if($claim->isPending())
                        <label class="muted" style="font-size: 13px;">Tutar</label>
                        <input class="amount" type="text" name="amount" value="{{ RegearClaim::formatSilver($claim->suggested_amount) }}" title="1.2m, 900k veya 1200000">
                        <button type="submit" name="action" value="approve" class="btn ok sm">✓ Onayla</button>
                        <button type="submit" name="action" value="reject" class="btn bad sm">Reddet</button>
                        <input class="reason" type="text" name="reason" maxlength="200" placeholder="Red sebebi (oyuncu görür, isteğe bağlı)" style="min-width: 220px; flex: 1;">
                        <span class="muted" style="font-size: 12px;">önerilen {{ RegearClaim::formatSilver($claim->suggested_amount) }}</span>
                    @else
                        <span class="muted" style="font-size: 13px;">{{ $statusLabels[$claim->status] }}@if($claim->reject_reason) — <i>{{ $claim->reject_reason }}</i>@endif @if($claim->decider) · {{ $claim->decider->name }}, {{ $claim->decided_at?->locale('tr')->diffForHumans() }}@endif</span>
                        @if($claim->notify_error)
                            <span class="pill warn" title="Discord bildirimi">⚠️ {{ ['dm_closed' => 'DM kapalı', 'no_discord' => 'Discord hesabı yok', 'failed' => 'Bildirim gönderilemedi'][$claim->notify_error] ?? $claim->notify_error }}</span>
                        @elseif($claim->notified_at && ! $claim->needsNotification())
                            <span class="pill" title="Oyuncuya Discord'dan bildirildi">📨 bildirildi</span>
                        @elseif($claim->notified_at)
                            <span class="pill info" title="Karar bildirildikten sonra değişti">📨 değişiklik bildirilmedi</span>
                        @endif
                        <button type="submit" name="action" value="reset" class="btn ghost sm">↺ Geri al</button>
                    @endif
                </form>
            @endunless
        </div>
    @empty
        <div class="card empty">
            @if($claims->isEmpty())
                @if(! $hasWindow) 1. adımda etkinlik zamanını gir, sonra ölümleri getir.
                @elseif(! $scanned) Ölümleri getirmek için 2. adımdaki butona bas.
                @else 🎉 Bu aralıkta guild'den kimse ölmemiş.
                @endif
            @else
                Bu sekmede kayıt yok.
            @endif
        </div>
    @endforelse
</div>

@if($claims->isNotEmpty())
    <div class="paybar">
        <div class="paybar-inner">
            <div>
                <div class="muted" style="font-size: 12px;">Ödenecek (onaylı, henüz ödenmemiş)</div>
                <div class="big">{{ RegearClaim::formatSilver($totals['unpaid_silver']) }} <span class="muted" style="font-size: 13px; font-weight: 500;">· {{ $unpaidPlayers }} oyuncu · bekleyen {{ $totals['pending'] }}</span></div>
                @if($notify['configured'])
                    <div class="muted" style="font-size: 12px; margin-top: 2px;">
                        📨 {{ $notify['sent'] }} oyuncuya Discord'dan bildirildi{{ $notify['pending'] ? ' · '.$notify['pending'].' oyuncunun yeni kararı bildirilmedi' : '' }}
                        @foreach(['dm_closed' => 'DM kapalı', 'no_discord' => 'Discord hesabı bulunamadı', 'failed' => 'gönderilemedi'] as $error => $label)
                            @if($names = $notify['unreachable'][$error] ?? null)
                                · <span style="color: var(--warn);" title="{{ $names->implode(', ') }}">⚠️ {{ $label }}: {{ $names->count() }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="row" style="align-items:center;">
                <textarea id="payoutList" hidden>{{ $payoutList }}</textarea>
                <button type="button" class="btn" id="copyPayouts" @disabled($payoutList === '')>📋 Ödeme listesini kopyala</button>
                @if($notify['configured'])
                    <form method="POST" action="{{ route('party.regear.notify', $link) }}" style="margin:0;"
                          onsubmit="return confirm('Kararı verilmiş {{ $notify['pending'] }} oyuncuya regear sonucu Discord DM ile gönderilsin mi?');">
                        @csrf
                        <button type="submit" class="btn" @disabled(! $notify['pending']) title="Her oyuncuya tek mesaj: onaylanan tutar, reddedilenler ve sebepleri. Sadece yeni / değişen kararlar gönderilir.">📨 Oyunculara bildir{{ $notify['pending'] ? ' ('.$notify['pending'].')' : '' }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('party.regear.paid', $link) }}" style="margin:0;" onsubmit="return confirm('Onaylı ve ödenmemiş tüm regear\'lar ödendi olarak işaretlensin mi?');">
                    @csrf
                    <button type="submit" class="btn primary" @disabled(! $totals['unpaid_silver'])>💸 Ödendi olarak işaretle</button>
                </form>
            </div>
        </div>
    </div>
@endif

@include('partials.local-time')
<script>
(function () {
    const pad = (n) => String(n).padStart(2, '0');
    const toLocalInput = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

    // Enter in the reject reason rejects (the form's first button would approve).
    document.querySelectorAll('.decide .reason').forEach((input) => input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            input.form.querySelector('button[value="reject"]').click();
        }
    }));

    const form = document.getElementById('windowForm');
    if (form) {
        const start = document.getElementById('startLocal');
        const end = document.getElementById('endLocal');
        const startUtc = document.getElementById('startsAtUtc');
        const endUtc = document.getElementById('endsAtUtc');
        const preview = document.getElementById('utcPreview');

        if (startUtc.value) start.value = toLocalInput(new Date(startUtc.value));
        if (endUtc.value) end.value = toLocalInput(new Date(endUtc.value));

        const sync = () => {
            if (!start.value || !end.value) { preview.textContent = ''; return; }
            const s = new Date(start.value), e = new Date(end.value);
            startUtc.value = s.toISOString();
            endUtc.value = e.toISOString();
            const utc = (d) => `${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`;
            const hours = (e - s) / 36e5;
            preview.textContent = hours > 0
                ? `UTC ${utc(s)}–${utc(e)} · ${hours.toLocaleString('tr-TR')} saat`
                : '⚠️ Bitiş, başlangıçtan sonra olmalı.';
            document.querySelectorAll('#durationChips .chip').forEach((c) => c.classList.toggle('active', Number(c.dataset.hours) === hours));
        };

        document.getElementById('durationChips').addEventListener('click', (event) => {
            const chip = event.target.closest('.chip');
            if (!chip || !start.value) return;
            end.value = toLocalInput(new Date(new Date(start.value).getTime() + Number(chip.dataset.hours) * 36e5));
            sync();
        });
        start.addEventListener('change', () => {
            if (start.value && !end.value) end.value = toLocalInput(new Date(new Date(start.value).getTime() + 2 * 36e5));
            sync();
        });
        end.addEventListener('change', sync);
        form.addEventListener('submit', sync);
        sync();
    }

    document.getElementById('copyPayouts')?.addEventListener('click', function () {
        navigator.clipboard.writeText(document.getElementById('payoutList').value).then(() => {
            this.textContent = '✅ Kopyalandı';
            setTimeout(() => (this.textContent = '📋 Ödeme listesini kopyala'), 1500);
        });
    });
})();
</script>
</body>
</html>
