{{-- One button system for the staff pages: same height, padding, font and radius; colour only signals intent. --}}
<style>
    .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-sizing: border-box;
        height: 38px; padding: 0 14px; margin: 0; border-radius: 8px; border: 1px solid #4b5563; background: #374151; color: #f3f4f6;
        font: 600 13px/1 'Segoe UI', system-ui, sans-serif; white-space: nowrap; text-decoration: none !important; cursor: pointer;
        transition: background-color .15s, border-color .15s, filter .15s; vertical-align: middle; }
    .ui-btn:hover { background: #4b5563; color: #fff; }
    .ui-btn:disabled { opacity: .45; cursor: not-allowed; }
    .ui-btn--sm { height: 32px; padding: 0 11px; font-size: 12px; border-radius: 7px; }
    .ui-btn--lg { height: 42px; padding: 0 18px; font-size: 14px; }
    .ui-btn--icon { width: 32px; padding: 0; }
    .ui-btn--primary { background: #6366f1; border-color: #6366f1; color: #fff; }
    .ui-btn--primary:hover { background: #4f46e5; border-color: #4f46e5; }
    .ui-btn--success { background: #059669; border-color: #059669; color: #fff; }
    .ui-btn--success:hover { background: #047857; border-color: #047857; }
    .ui-btn--warning { background: #f59e0b; border-color: #f59e0b; color: #111827; }
    .ui-btn--warning:hover { background: #d97706; border-color: #d97706; color: #111827; }
    .ui-btn--danger { background: transparent; border-color: rgba(239, 68, 68, .55); color: #fca5a5; }
    .ui-btn--danger:hover { background: rgba(239, 68, 68, .15); border-color: #ef4444; color: #fecaca; }
    .ui-btn--ghost { background: transparent; border-color: #374151; color: #d1d5db; }
    .ui-btn--ghost:hover { background: #1f2937; border-color: #4b5563; }
    .ui-btn-group { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .ui-btn-group form { margin: 0; display: inline-flex; }
</style>
