    /* ==========================================================================
       Stage-aware standings — shared by the public and organizer pages.
       Page-specific shell styles stay in each page's own <style> block.
       ========================================================================== */

    .lp-stage-panel { margin-bottom:20px; }
    .lp-stage-panel:last-child { margin-bottom:0; }
    .lp-stage-head { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; padding-bottom:14px; margin-bottom:16px; border-bottom:1px solid #eef2f7; }
    .lp-stage-heading { display:flex; align-items:flex-start; gap:12px; min-width:0; }
    .lp-stage-icon { width:38px; height:38px; flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; background:#e8edf7; color:#09173c; font-size:1rem; }
    .lp-stage-title { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin:0; font-size:15px; font-weight:700; line-height:1.3; color:var(--lp-navy,#09173c); }
    .lp-stage-group-name { display:inline-flex; align-items:center; padding:2px 9px; border-radius:999px; background:#f1f5f9; color:#475569; font-size:10.5px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; }
    .lp-stage-caption { margin:4px 0 0; font-size:12px; line-height:1.5; color:var(--lp-muted,#64748b); }
    .lp-stage-badges { display:flex; align-items:center; flex-wrap:wrap; gap:7px; }
    .lp-stage-format { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; background:#eff6ff; color:#1e40af; font-size:10.5px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; }
    .lp-stage-status { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:999px; font-size:10.5px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; }
    .lp-stage-status::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; opacity:.7; }
    .lp-stage-status.is-active { background:#dbeafe; color:#1e40af; }
    .lp-stage-status.is-pending { background:#fef3c7; color:#92400e; }
    .lp-stage-status.is-complete { background:#d1fae5; color:#065f46; }
    .lp-stage-status.is-locked { background:#e5e7eb; color:#374151; }
    .lp-stage-status.is-neutral { background:#e5e7eb; color:#374151; }
    .lp-stage-count { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; background:#e8edf7; color:#1a3a7c; font-size:11px; font-weight:600; }
    .lp-stage-body { min-width:0; }

    /* ---------- Stage anchor navigation ----------------------------------- */
    .lp-stage-nav { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin:0 0 18px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; box-shadow:0 2px 8px rgba(9,23,60,.04); }
    .lp-stage-nav-label { font-size:10.5px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#94a3b8; }
    .lp-stage-nav a { display:inline-flex; align-items:center; gap:6px; padding:5px 11px; border-radius:999px; background:#f1f5f9; color:#334155; font-size:11.5px; font-weight:600; text-decoration:none; transition:background .15s ease, color .15s ease; }
    .lp-stage-nav a:hover { background:#09173c; color:#fff; }
    .lp-stage-nav a i { font-size:11px; }

    /* ---------- Stage tables ---------------------------------------------- */
    .lp-stage-tables { display:grid; gap:16px; }
    @media (min-width:1200px) { .lp-stage-tables.is-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    .lp-stage-table { min-width:0; border:1px solid #eef2f7; border-radius:10px; overflow:hidden; background:#fff; }
    .lp-stage-table-head { display:flex; align-items:center; flex-wrap:wrap; gap:8px 12px; padding:11px 14px; border-bottom:1px solid #eef2f7; background:#f8fafc; }
    .lp-stage-table-head b { font-size:12.5px; color:#09173c; }
    .lp-stage-table-head span { font-size:11px; color:#64748b; }
    .lp-source-badge { display:inline-flex; align-items:center; gap:5px; margin-left:auto; padding:3px 9px; border-radius:999px; background:#eef2ff; color:#3730a3; font-size:10.5px; font-weight:600; }
    .lp-stage-table .lp-table-wrap { border:0; border-radius:0; }
    .lp-stage-table .lp-table { margin:0; }
    .lp-stage-table .lp-standings-table { margin:0; }

    /* ---------- Shared table cells ---------------------------------------- */
    .lp-standings-team { display:inline-flex; align-items:center; gap:9px; min-width:0; font-weight:600; color:var(--lp-navy,#09173c); }
    .lp-standings-team img { width:32px; height:32px; flex:0 0 auto; border-radius:9px; border:1px solid #e8eef6; background:#e8eef6; object-fit:cover; }
    .lp-standings-team-name { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-transform:capitalize; }
    .lp-standings-table .lp-standings-team { font-size:12.5px; }
    .lp-standings-table .lp-standings-team img { width:30px; height:30px; border-radius:8px; }
    .lp-standings-progress { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:20px; font-size:11px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; }
    .lp-standings-progress::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; opacity:.75; }
    .lp-standings-progress.is-qualified { background:#d1fae5; color:#065f46; }
    .lp-standings-progress.is-eliminated { background:#fee2e2; color:#991b1b; }
    .lp-standings-progress.is-neutral { background:#e5e7eb; color:#374151; }
    .lp-num { font-variant-numeric:tabular-nums; }
    .lp-pts { font-weight:700; }
    .lp-diff { font-weight:600; font-variant-numeric:tabular-nums; }
    .lp-diff.is-pos { color:#0f7b4f; }
    .lp-diff.is-neg { color:#b42318; }
    .lp-diff.is-zero { color:#6b7280; }

    /* ---------- Empty state ------------------------------------------------ */
    .lp-stage-empty { display:flex; align-items:flex-start; gap:12px; padding:18px; border:1px dashed #d7e0ec; border-radius:10px; background:#fbfdff; }
    .lp-stage-empty i { font-size:1.4rem; color:#cbd5e1; }
    .lp-stage-empty b { display:block; font-size:12.5px; color:#09173c; }
    .lp-stage-empty p { margin:3px 0 0; font-size:12px; line-height:1.5; color:#64748b; }

    /* ---------- Placement matches ----------------------------------------- */
    .lp-placement-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:12px; }
    .lp-placement-card { min-width:0; padding:10px; border:1px solid #eef2f7; border-radius:10px; background:#fff; }
    .lp-placement-round { margin-bottom:7px; font-size:10.5px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#94a3b8; }

    /* ---------- Utility ---------------------------------------------------- */
    .is-hidden { display:none !important; }
    @media (max-width:767.98px) {
        .lp-stage-head { flex-direction:column; align-items:stretch; }
        .lp-stage-badges { justify-content:flex-start; }
        .lp-stage-table-head { align-items:flex-start; }
        .lp-source-badge { margin-left:0; }
    }

    /* ---------- Bracket board ---------------------------------------------- */
    .lp-bracket-board { display:flex; flex-direction:column; gap:16px; }
    .lp-bracket-side { min-width:0; border:1px solid #eef2f7; border-radius:10px; overflow:hidden; background:#fff; }
    .lp-bracket-side-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:11px 14px; border-bottom:1px solid #eef2f7; background:#f8fafc; }
    .lp-bracket-side-head h3 { margin:0; font-size:12.5px; font-weight:700; color:#09173c; }
    .lp-bracket-side-head span { font-size:11px; color:#64748b; }
    .lp-bracket-scroll { overflow-x:auto; padding:14px; }
    .lp-bracket-rounds { display:flex; align-items:flex-start; gap:14px; min-width:max-content; }
    .lp-bracket-round { display:flex; flex-direction:column; gap:10px; min-width:190px; max-width:230px; }
    .lp-bracket-round-head { padding:6px 9px; border-radius:7px; background:#09173c; color:#fff; font-size:10.5px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; text-align:center; white-space:nowrap; }
    .lp-bracket-match { overflow:hidden; border:1px solid #e2e8f0; border-radius:9px; background:#fff; box-shadow:0 1px 2px rgba(9,23,60,.04); }
    .lp-bracket-match.is-complete { border-color:#cbd5e1; }
    .lp-bracket-match.is-bye { border-style:dashed; background:#fbfdff; }
    .lp-bracket-match.is-tbd { background:#fbfdff; }
    .lp-bracket-match.is-cancelled { border-color:#fecaca; background:#fef2f2; }
    .lp-bracket-team { display:flex; align-items:center; gap:7px; padding:8px 10px; border-bottom:1px solid #eef2f7; font-size:12px; color:#334155; }
    .lp-bracket-team:last-of-type { border-bottom:0; }
    .lp-bracket-team.is-winner { background:#ecfdf5; color:#065f46; font-weight:700; }
    .lp-bracket-team.is-draw { background:#fefce8; color:#854d0e; font-weight:600; }
    .lp-bracket-seed { flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center; min-width:24px; padding:1px 5px; border-radius:5px; background:#eef2f7; color:#64748b; font-size:9.5px; font-weight:700; }
    .lp-bracket-team.is-winner .lp-bracket-seed { background:#a7f3d0; color:#065f46; }
    .lp-bracket-name { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .lp-bracket-score { flex:0 0 auto; min-width:20px; text-align:right; font-weight:700; font-variant-numeric:tabular-nums; color:#09173c; }
    .lp-bracket-foot { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:4px 8px; padding:6px 10px; border-top:1px solid #f1f5f9; background:#f8fafc; font-size:10px; color:#64748b; }
    .lp-bracket-state { font-weight:600; letter-spacing:.2px; text-transform:uppercase; }
    .lp-bracket-match.is-complete .lp-bracket-state { color:#047857; }
    .lp-bracket-match.is-draft .lp-bracket-state { color:#92400e; }
    .lp-bracket-meta { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .lp-bracket-side.is-grand_final .lp-bracket-round-head { background:#7c2d12; }
    .lp-bracket-side.is-losers .lp-bracket-round-head { background:#334155; }
    @media (max-width:767.98px) {
        .lp-bracket-round { min-width:170px; }
        .lp-bracket-scroll { padding:10px; }
    }

