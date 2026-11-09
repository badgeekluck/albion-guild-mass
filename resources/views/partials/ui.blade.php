{{-- Shared look for the regear screens: calm dark theme, rounded cards, readable sizes. --}}
<style>
    :root { --bg: #0f1420; --card: #171e2c; --card-2: #1d2536; --line: #2a3447; --text: #e6e9ef; --muted: #8b95a7;
            --accent: #7c83ff; --ok: #34d399; --warn: #fbbf24; --bad: #f87171; --info: #93c5fd; }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 24px 20px 120px; font-size: 14px; }
    .page { max-width: 1180px; margin: 0 auto; }
    a { color: var(--info); text-decoration: none; }
    a:hover { text-decoration: underline; }
    .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
    .topbar h1 { margin: 0; font-size: 24px; }
    .subtitle { color: var(--muted); margin-top: 4px; }
    .links { display: flex; gap: 14px; align-items: center; }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 18px 20px; margin-bottom: 16px; }
    .card-title { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 600; margin: 0 0 4px; }
    .card-hint { color: var(--muted); font-size: 13px; margin: 0 0 14px; }
    .step { width: 28px; height: 28px; border-radius: 50%; background: var(--card-2); border: 1px solid var(--line); display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
    .step.done { background: rgba(52, 211, 153, .15); border-color: var(--ok); color: var(--ok); }
    .row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
    .field label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 6px; font-weight: 600; }
    input, select { background: var(--bg); border: 1px solid var(--line); color: var(--text); padding: 10px 12px; border-radius: 10px; font-size: 14px; }
    input:focus, select:focus { outline: none; border-color: var(--accent); }
    .btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; border-radius: 10px; padding: 10px 16px; font-size: 14px; font-weight: 600; cursor: pointer; background: var(--card-2); color: var(--text); border-color: var(--line); }
    .btn:hover { filter: brightness(1.15); }
    .btn:disabled { opacity: .45; cursor: not-allowed; }
    .btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
    .btn.ok { background: rgba(52, 211, 153, .16); border-color: rgba(52, 211, 153, .5); color: var(--ok); }
    .btn.bad { background: transparent; border-color: rgba(248, 113, 113, .4); color: var(--bad); }
    .btn.ghost { background: transparent; }
    .btn.sm { padding: 6px 10px; font-size: 13px; border-radius: 8px; }
    .chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .chip { border: 1px solid var(--line); background: var(--bg); color: var(--text); padding: 7px 12px; border-radius: 999px; font-size: 13px; cursor: pointer; }
    .chip.active { border-color: var(--accent); background: rgba(124, 131, 255, .15); color: #fff; }
    .pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: var(--card-2); color: var(--muted); }
    .pill.ok { background: rgba(52, 211, 153, .12); color: var(--ok); }
    .pill.warn { background: rgba(251, 191, 36, .12); color: var(--warn); }
    .pill.bad { background: rgba(248, 113, 113, .12); color: var(--bad); }
    .pill.info { background: rgba(147, 197, 253, .12); color: var(--info); }
    .muted { color: var(--muted); }
    .notice { border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; font-size: 14px; }
    .notice.ok { background: rgba(52, 211, 153, .1); border: 1px solid rgba(52, 211, 153, .35); color: #a7f3d0; }
    .notice.bad { background: rgba(248, 113, 113, .1); border: 1px solid rgba(248, 113, 113, .35); color: #fecaca; }
    .notice.warn { background: rgba(251, 191, 36, .08); border: 1px solid rgba(251, 191, 36, .35); color: #fde68a; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
    details > summary { cursor: pointer; color: var(--info); list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
    .error-text { color: var(--bad); font-size: 12px; margin-top: 4px; }
    .tier-matrix { border-collapse: separate; border-spacing: 6px; }
    .tier-matrix th { font-size: 12px; color: var(--muted); font-weight: 600; text-align: left; white-space: nowrap; padding: 0 4px; }
    .tier-matrix tbody th { color: var(--text); font-size: 14px; }
    .tier-matrix select { padding: 8px 10px; min-width: 130px; }
    .tier-matrix select.set { border-color: var(--accent); color: #fff; }
</style>
