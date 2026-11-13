    /* ==========================================================
       Standings — page-specific additions only.
       Shared hero / breadcrumb / pills / buttons / tabs / panels /
       modals live in includes/league-view-styles.php
       ========================================================== */

    /* ---------- Flash notice ----------------------------------- */
    .lp-flash { display:flex; align-items:flex-start; gap:11px; margin-bottom:20px; padding:13px 18px; border:1px solid var(--lp-border); border-radius:10px; background:#fff; font-size:13px; font-weight:500; }
    .lp-flash i { margin-top:1px; font-size:15px; }
    .lp-flash span { line-height:1.5; }
    .lp-flash-success { border-color:#a7f3d0; background:#ecfdf5; color:#065f46; }
    .lp-flash-danger { border-color:#fecaca; background:#fef2f2; color:#991b1b; }
    .lp-flash-info { border-color:#bfdbfe; background:#eff6ff; color:#1e40af; }

    /* ---------- Shared section body rhythm ---------------------- */
    /* The shared .activity-section ships without a bottom margin (the other
       pages wrap it in a .col-*-mb-4), so spacing is restored here. */
    .lp-page .activity-section { margin-bottom:20px; }
    .lp-page .activity-section:last-child { margin-bottom:0; }
    .lp-section-body { padding:20px 24px 24px; }
    .lp-divider { margin:20px 0; border:0; border-top:1px solid #eef2f7; opacity:1; }
    .lp-section-body .lp-divider { margin:20px 0; border-color:#f1f5f9; opacity:1; }
    .lp-section-body .btn-primary { padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:600; background:#123f70; border-color:#123f70; }
    .lp-section-body .btn-primary:hover, .lp-section-body .btn-primary:focus { background:#0d3160; border-color:#0d3160; }
    .lp-search { position:relative; }
    .lp-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:13px; color:#9ca3af; }
    .lp-search input { min-width:220px; padding:7px 14px 7px 32px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12.5px; transition:border-color .2s ease, box-shadow .2s ease; }
    .lp-search input:focus { outline:none; border-color:var(--lp-blue); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.1); }

    /* ---------- Group setup ------------------------------------- */
    .lp-setup { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
    .lp-setup-icon { width:46px; height:46px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; border-radius:12px; background:#e8edf7; color:#09173c; font-size:1.25rem; }
    .lp-setup-text { flex:1 1 280px; min-width:0; }
    .lp-setup-text strong { display:block; font-size:14px; color:var(--lp-ink); }
    .lp-setup-text small { font-size:12.5px; line-height:1.5; color:var(--lp-muted); }
    .lp-switch { display:inline-flex; align-items:center; margin:0; padding:0; cursor:pointer; user-select:none; }
    /* Bootstrap 4 (sb-admin-2) and Bootstrap 5 both ship .form-check rules and
       they conflict — BS4 forces the input to position:absolute — so this
       switch is fully self-contained instead of using .form-check-input. */
    .lp-switch input { position:absolute; width:1px; height:1px; margin:0; padding:0; opacity:0; overflow:hidden; clip:rect(0 0 0 0); pointer-events:none; }
    .lp-switch-track { position:relative; display:block; flex:0 0 auto; width:52px; height:28px; border-radius:999px; background:#d1d5db; box-shadow:inset 0 0 0 1px rgba(9,23,60,.06); transition:background .2s ease; }
    .lp-switch-track::after { content:''; position:absolute; top:3px; left:3px; width:22px; height:22px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(9,23,60,.28); transition:transform .2s ease; }
    .lp-switch:hover .lp-switch-track { filter:brightness(.97); }
    .lp-switch input:checked + .lp-switch-track { background:var(--lp-blue); }
    .lp-switch input:checked + .lp-switch-track::after { transform:translateX(24px); }
    .lp-switch input:focus-visible + .lp-switch-track { box-shadow:0 0 0 3px rgba(37,99,235,.25); }
    .lp-prog-actions { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
    .lp-group-list { display:flex; flex-wrap:wrap; gap:10px; }
    .lp-group-chip { display:inline-flex; align-items:center; gap:9px; padding:6px 8px 6px 13px; border:1.5px solid var(--lp-border); border-radius:999px; background:#fff; font-size:12.5px; font-weight:600; color:var(--lp-ink); }
    .lp-group-order { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#e8edf7; color:#09173c; font-size:10.5px; font-weight:700; }
    .lp-group-chip form { display:flex; margin:0; }
    .lp-group-remove { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; padding:0; border:none; border-radius:50%; background:#f3f4f6; color:#6b7280; font-size:11px; line-height:1; transition:background .15s ease, color .15s ease; }
    .lp-group-remove:hover { background:#fee2e2; color:#b91c1c; }
    .lp-groups-empty { display:flex; align-items:center; gap:8px; font-size:12.5px; color:var(--lp-muted); }

    /* ---------- Progression manager ------------------------------ */
    .lp-prog-controls { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .lp-prog-controls .lp-inline-label { margin:0; font-size:11.5px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; color:var(--lp-muted); }
    .lp-prog-steps { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
    .lp-prog-step { display:flex; align-items:flex-start; gap:11px; padding:13px 15px; border:1px solid #eef2f7; border-radius:10px; background:#f8fafc; }
    .lp-step-num { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; flex:0 0 auto; border-radius:50%; background:#09173c; color:#fff; font-size:11.5px; font-weight:700; }
    .lp-step-text { font-size:12.5px; line-height:1.5; color:#475569; }
    .lp-step-text b { display:block; color:var(--lp-ink); font-weight:600; }
    @media (max-width:767px) { .lp-prog-steps { grid-template-columns:1fr; } }
    .lp-prog .alert { margin-bottom:16px; border:none; border-left:4px solid var(--lp-blue); border-radius:10px; background:#eff6ff; color:#1e3a8a; font-size:12.5px; }
    .lp-prog .alert.alert-success { border-left-color:#059669; background:#ecfdf5; color:#065f46; }
    .lp-prog .btn { border-radius:8px; font-size:12.5px; font-weight:600; }
    .lp-prog .btn-outline-primary { color:#123f70; border-color:#123f70; }
    .lp-prog .btn-outline-primary:hover { background:#123f70; border-color:#123f70; color:#fff; }
    .lp-prog .btn-outline-secondary { color:#4b5563; border-color:#d1d5db; }
    .lp-prog .btn-outline-secondary:hover { background:#f3f4f6; border-color:#9ca3af; color:#374151; }
    .lp-prog .btn-success { background:#15803d; border-color:#15803d; }
    .lp-prog .btn-success:hover { background:#166534; border-color:#166534; }
    #confirmedContainer h6 { display:flex; align-items:center; gap:7px; font-size:12.5px; }
    #confirmedList .badge { display:inline-flex; align-items:center; gap:6px; padding:7px 15px; border-radius:999px; background:#d1fae5 !important; color:#065f46 !important; font-size:12px; font-weight:600; }
    #suggestionsContainer h6 { font-size:12.5px; color:var(--lp-ink); }
    #suggestionsContainer .table { font-size:12.5px; }
    #suggestionsContainer .table thead th { padding:9px 12px; border-color:#09173c; background:#09173c; color:#fff; font-size:10.5px; letter-spacing:.5px; text-transform:uppercase; white-space:nowrap; }
    #suggestionsContainer .table tbody td { padding:9px 12px; border-color:#eef2f7; vertical-align:middle; }
    #suggestionsContainer .table-bordered { border-color:#eef2f7; }

    /* ---------- Standings table ---------------------------------- */
    .lp-standings-tools { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .lp-group-filter { min-width:150px; padding:7px 12px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12.5px; }
    .lp-table-wrap { overflow-x:auto; }
    .lp-standings-table { font-size:13.5px; margin:0; width:100%; }
    .lp-standings-table thead th { white-space:nowrap; }
    .lp-standings-table tbody td { padding:11px 14px; }
    .lp-standings-table .lp-num { text-align:center; font-weight:500; color:#334155; font-variant-numeric:tabular-nums; }
    .lp-rank { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:9px; background:#f1f5f9; color:#475569; font-size:12px; font-weight:700; }
    .lp-rank.is-1 { background:#fff4d6; color:#92400e; box-shadow:inset 0 0 0 1px #f2d693; }
    .lp-rank.is-2 { background:#eef2f7; color:#475569; box-shadow:inset 0 0 0 1px #dbe2ea; }
    .lp-rank.is-3 { background:#fdece0; color:#9a3412; box-shadow:inset 0 0 0 1px #f3c9a8; }
    .lp-team-cell { display:flex; align-items:center; gap:12px; min-width:200px; }
    .lp-team-avatar { width:38px; height:38px; flex:0 0 auto; border-radius:10px; border:1px solid #e8edf4; background:#eef2f7; object-fit:cover; }
    .lp-team-name { font-weight:600; color:var(--lp-ink); line-height:1.3; text-transform:capitalize; }
    .lp-team-sub { font-size:11px; color:#9ca3af; }
    .lp-diff { font-weight:600; font-variant-numeric:tabular-nums; }
    .lp-diff.is-pos { color:#0f7b4f; }
    .lp-diff.is-neg { color:#b42318; }
    .lp-diff.is-zero { color:#6b7280; }
    .lp-group-form { margin:0; }
    .lp-group-select { min-width:132px; padding:5px 10px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12.5px; }
    .lp-group-select:focus { border-color:var(--lp-blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .lp-decide { display:inline-flex; align-items:center; justify-content:flex-end; gap:6px; margin:0; }
    .lp-standings-table .league-icon-btn { width:32px; height:32px; border-radius:9px; font-size:.85rem; }
    .lp-action-danger { color:#b42318; background:#fee2e2; border-color:#fecaca; }
    .lp-action-danger:hover { color:#fff; background:#b42318; border-color:#b42318; }
    .lp-action-reset { color:#475569; background:#f3f4f6; border-color:#e5e7eb; }
    .lp-action-reset:hover { color:#123f70; background:#e8edf7; border-color:#c7d5ea; }
    .lp-standings-legend { display:flex; align-items:center; flex-wrap:wrap; gap:6px 18px; padding:13px 24px; border-top:1px solid #f1f5f9; font-size:11.5px; color:#9ca3af; }
    .lp-standings-legend b { color:#475569; font-weight:600; }
    .lp-standings-legend .lp-legend-note { display:inline-flex; align-items:center; gap:6px; margin-left:auto; }
    @media (max-width:575px) { .lp-standings-legend .lp-legend-note { margin-left:0; } }