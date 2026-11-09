<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sunucu durumu</title>
    @include('partials.ui')
    <style>
        .meters { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .meter .value { font-size: 30px; font-weight: 700; margin: 4px 0 2px; }
        .meter .sub { color: var(--muted); font-size: 13px; min-height: 18px; }
        .bar { height: 8px; background: var(--card-2); border-radius: 999px; overflow: hidden; margin-top: 10px; }
        .bar i { display: block; height: 100%; width: 0; background: var(--ok); border-radius: 999px; transition: width .4s, background .4s; }
        .bar i.warn { background: var(--warn); }
        .bar i.bad { background: var(--bad); }
        canvas { width: 100%; height: 56px; margin-top: 10px; display: block; }
        .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0 24px; font-size: 14px; }
        .facts div { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid var(--line); }
        .facts span { color: var(--muted); }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>🖥️ Sunucu durumu</h1>
            <div class="subtitle">Her 5 saniyede bir güncellenir. <span id="lastUpdate" class="muted"></span></div>
        </div>
        <div class="links"><a href="{{ route('admin.activity') }}">👣 Aktivite</a><a href="{{ route('dashboard') }}">⬅ Dashboard</a></div>
    </div>

    <div class="meters">
        <div class="card meter">
            <div class="card-title">⚙️ CPU</div>
            <div class="value" id="cpuValue">–</div>
            <div class="sub" id="cpuSub"></div>
            <div class="bar"><i id="cpuBar"></i></div>
            <canvas id="cpuChart" width="400" height="56" aria-label="CPU geçmişi"></canvas>
        </div>
        <div class="card meter">
            <div class="card-title">🧠 RAM</div>
            <div class="value" id="memValue">–</div>
            <div class="sub" id="memSub"></div>
            <div class="bar"><i id="memBar"></i></div>
            <canvas id="memChart" width="400" height="56" aria-label="RAM geçmişi"></canvas>
        </div>
        <div class="card meter">
            <div class="card-title">💾 Disk</div>
            <div class="value" id="diskValue">–</div>
            <div class="sub" id="diskSub"></div>
            <div class="bar"><i id="diskBar"></i></div>
        </div>
    </div>

    <div class="card">
        <div class="card-title">📦 Uygulama</div>
        <div class="facts">
            <div><span>Ortam</span><b>{{ $app['environment'] }}{{ $app['debug'] ? ' · debug açık' : '' }}</b></div>
            <div><span>PHP / Laravel</span><b>{{ $app['php'] }} / {{ $app['laravel'] }}</b></div>
            <div><span>Uptime</span><b id="uptime">–</b></div>
            <div><span>Veritabanı</span><b>{{ $app['database_mb'] !== null ? $app['database_mb'].' MB' : '—' }}</b></div>
            <div><span>Kuyrukta bekleyen</span><b>{{ $app['queue_pending'] ?? '—' }}</b></div>
            <div><span>Başarısız job</span><b style="color: {{ $app['queue_failed'] ? 'var(--bad)' : 'inherit' }};">{{ $app['queue_failed'] ?? '—' }}</b></div>
            <div><span>App container RAM</span><b>{{ $app['container_memory_mb'] !== null ? $app['container_memory_mb'].' MB' : '—' }}</b></div>
            <div><span>Önbellekteki ikonlar</span><b>{{ $app['icons'] }}</b></div>
        </div>
        <p class="card-hint" style="margin: 12px 0 0;">CPU / RAM / disk, uygulamanın çalıştığı makineyi gösterir (Docker'da: host ya da Docker Desktop sanal makinesi).</p>
    </div>
</div>

<script>
(function () {
    const url = @json(route('admin.server.stats'));
    const history = { cpu: [], mem: [] };
    const MAX_POINTS = 60; // 5 minutes

    const level = (p) => (p >= 90 ? 'bad' : p >= 75 ? 'warn' : '');
    const setBar = (id, percent) => {
        const bar = document.getElementById(id);
        bar.style.width = `${Math.min(100, percent ?? 0)}%`;
        bar.className = level(percent ?? 0);
    };
    const text = (id, value) => { document.getElementById(id).textContent = value; };
    const duration = (s) => {
        const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
        return [d && `${d} gün`, h && `${h} sa`, `${m} dk`].filter(Boolean).join(' ');
    };

    function chart(id, points) {
        const canvas = document.getElementById(id);
        const ctx = canvas.getContext('2d');
        const w = canvas.width, h = canvas.height;
        ctx.clearRect(0, 0, w, h);
        if (points.length < 2) return;
        const style = getComputedStyle(document.body);
        ctx.strokeStyle = style.getPropertyValue('--accent').trim() || '#7c83ff';
        ctx.lineWidth = 2;
        ctx.beginPath();
        points.forEach((p, i) => {
            const x = (i / (MAX_POINTS - 1)) * w;
            const y = h - (Math.min(100, p) / 100) * (h - 4) - 2;
            i ? ctx.lineTo(x, y) : ctx.moveTo(x, y);
        });
        ctx.stroke();
    }

    function render(s) {
        if (s.cpu) {
            const p = s.cpu.percent;
            text('cpuValue', p === null ? '—' : `${p}%`);
            text('cpuSub', `${s.cpu.cores} çekirdek · load ${s.cpu.load.map((l) => l.toFixed(2)).join(' / ')}`);
            setBar('cpuBar', p);
            if (p !== null) history.cpu.push(p);
        }
        if (s.memory) {
            text('memValue', `${s.memory.percent}%`);
            text('memSub', `${(s.memory.used_mb / 1024).toFixed(1)} / ${(s.memory.total_mb / 1024).toFixed(1)} GB${s.memory.swap_used_mb ? ` · swap ${s.memory.swap_used_mb} MB` : ''}`);
            setBar('memBar', s.memory.percent);
            history.mem.push(s.memory.percent);
        }
        if (s.disk) {
            text('diskValue', `${s.disk.percent}%`);
            text('diskSub', `${s.disk.used_gb} / ${s.disk.total_gb} GB kullanılıyor`);
            setBar('diskBar', s.disk.percent);
        }
        if (s.uptime_seconds) text('uptime', duration(s.uptime_seconds));
        ['cpu', 'mem'].forEach((k) => { if (history[k].length > MAX_POINTS) history[k].shift(); });
        chart('cpuChart', history.cpu);
        chart('memChart', history.mem);
        text('lastUpdate', `Son: ${new Date(s.time).toLocaleTimeString('tr-TR')}`);
    }

    async function poll() {
        if (!document.hidden) {
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (response.ok) render(await response.json());
            } catch (e) {
                text('lastUpdate', 'Sunucuya ulaşılamadı');
            }
        }
        setTimeout(poll, 5000);
    }

    render(@json($snapshot));
    setTimeout(poll, 5000);
})();
</script>
</body>
</html>
