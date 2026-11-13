<?php
require_once __DIR__ . '/config/leagueSession.php';
require_once __DIR__ . '/config/dbconnect.php';
require_once __DIR__ . '/includes/schedulingHelper.php';

$id = intval($_GET['id'] ?? 0);
$leagueId = $id;
$teamId = intval($_GET['team_id'] ?? 0);

// Public, read-only fixture board. Organizers manage the schedule in
// app/organizer/schedule.php and Committees manage each published fixture
// (livestream + result) in app/committee/manage_match.php, so no score-entry
// controls live here.

require_once __DIR__ . '/includes/header.php';
if ((int) ($_SESSION['lms_role'] ?? 0) === 2) {
    require_once __DIR__ . '/includes/bar-tr-league.php'; // Team Representative portal chrome
} else {
    require_once __DIR__ . '/includes/bar.php'; // public navbar
}
require_once __DIR__ . '/includes/league_header.php';
?>

<style>
    /* ==========================================================
       Schedule — page-specific additions only. Shared panels, chips,
       buttons and empty states live in includes/league-view-styles.php
       ========================================================== */

    /* ---------- Panel count chip (mirrors Participants/Standings) ---------- */
    .lp-count { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:20px; background:#e8edf7; color:#1a3a7c; font-size:11px; font-weight:600; }

    /* ---------- Filter toolbar --------------------------------------------- */
    .sch-toolbar { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin:0 0 18px; }
    .sch-select { position:relative; flex:0 0 auto; min-width:196px; }
    .sch-select > i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:13px; color:#94a3b8; pointer-events:none; }
    .sch-select .form-select { height:40px; padding:8px 32px 8px 33px; border:1.5px solid var(--lp-border); border-radius:9px; background-color:#f9fafb; font-size:12.5px; font-weight:500; }
    .sch-select .form-select:focus { border-color:var(--lp-blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .sch-toolbar-end { margin-left:auto; }
    .sch-refresh { display:inline-flex; align-items:center; justify-content:center; gap:7px; height:40px; padding:0 14px; border:1.5px solid var(--lp-border); border-radius:9px; background:#fff; color:var(--lp-navy); font-size:12.5px; font-weight:600; cursor:pointer; transition:background .18s ease, border-color .18s ease, color .18s ease; }
    .sch-refresh:hover:not(:disabled) { border-color:var(--lp-navy); background:var(--lp-navy); color:#fff; }
    .sch-refresh:disabled { cursor:default; opacity:.72; }
    .sch-refresh i { font-size:13px; }
    .sch-refresh.is-busy i { animation:sch-spin .9s linear infinite; }
    @keyframes sch-spin { to { transform:rotate(360deg); } }

    /* ---------- View switch (Fixtures / Bracket) ---------------------------- */
    .sch-head-tools { display:flex; align-items:center; gap:12px; margin-left:auto; }
    .sch-view-toggle { display:inline-flex; gap:3px; padding:3px; border-radius:9px; background:#eef2f7; }
    .sch-view-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:6px 13px; border:0; border-radius:7px; background:transparent; color:var(--lp-muted); font-size:11.5px; font-weight:600; line-height:1.2; cursor:pointer; transition:background .18s ease, color .18s ease, box-shadow .18s ease; }
    .sch-view-btn:hover { color:var(--lp-navy); }
    .sch-view-btn.is-active { background:#fff; color:var(--lp-navy); box-shadow:0 2px 6px rgba(9,23,60,.14); }
    .sch-view-btn i { font-size:12.5px; }
    .sch-view-btn:focus-visible { outline:2px solid rgba(37,99,235,.45); outline-offset:2px; }


    /* ---------- Match day groups ------------------------------------------- */
    .sch-day + .sch-day { margin-top:20px; }
    .sch-day-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:0 0 8px; padding-bottom:7px; border-bottom:1px solid #eef2f7; }
    .sch-day-label { display:inline-flex; align-items:center; gap:8px; min-width:0; font-size:11.5px; font-weight:600; letter-spacing:.5px; text-transform:uppercase; color:var(--lp-navy); }
    .sch-day-label i { flex:0 0 auto; font-size:13px; color:#94a3b8; }
    .sch-day-count { flex:0 0 auto; font-size:11.5px; color:var(--lp-muted); white-space:nowrap; }
    .sch-day-list { display:grid; gap:10px; }

    /* ---------- Match card ------------------------------------------------- */
    .sch-match { display:flex; flex-direction:column; overflow:hidden; border:2px solid #a9bfd9; border-radius:14px; background:#fff; box-shadow:0 4px 12px rgba(9,23,60,.04); transition:transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
    .sch-match:hover { transform:translateY(-2px); border-color:#86a4c8; box-shadow:0 9px 20px rgba(9,23,60,.08); }

    .sch-match-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px 12px; padding:10px 18px 0; }
    .sch-when { display:inline-flex; align-items:center; flex-wrap:wrap; gap:6px 10px; min-width:0; }
    .sch-time { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--lp-navy); font-variant-numeric:tabular-nums; }
    .sch-time i { font-size:11.5px; color:#94a3b8; }
    .sch-context { display:inline-flex; align-items:center; gap:8px; min-width:0; font-size:10.5px; font-weight:500; color:var(--lp-muted); overflow-wrap:anywhere; }
    .sch-context::before { content:''; flex:0 0 auto; width:3px; height:3px; border-radius:50%; background:#cbd5e1; }

    .sch-status { display:inline-flex; align-items:center; gap:6px; font-size:10px; font-weight:600; letter-spacing:.5px; text-transform:uppercase; white-space:nowrap; }
    .sch-status::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
    .sch-status.is-upcoming { color:#b45309; }
    .sch-status.is-scheduled { color:#64748b; }
    .sch-status.is-ongoing { color:#1d4ed8; }
    .sch-status.is-ongoing::before { box-shadow:0 0 0 3px rgba(29,78,216,.12); }
    .sch-status.is-completed { color:#047857; }
    .sch-status.is-pending { color:#6d28d9; }
    .sch-status.is-muted { color:#4b5563; }

    .sch-live { display:inline-flex; align-items:center; gap:6px; color:#be123c; font-size:9.5px; font-weight:600; letter-spacing:.45px; text-transform:uppercase; text-decoration:none !important; white-space:nowrap; transition:color .18s ease; }
    .sch-live::before { content:''; width:6px; height:6px; border-radius:50%; background:#e11d48; box-shadow:0 0 0 3px rgba(225,29,72,.12); }
    .sch-live:hover { color:#9f1239; }
    .sch-live:focus-visible { outline:2px solid rgba(225,29,72,.45); outline-offset:3px; border-radius:3px; }

    .sch-match-body { display:grid; flex:1 1 auto; grid-template-columns:minmax(0,1fr) auto minmax(0,1fr); align-items:center; gap:8px 16px; padding:8px 18px 10px; }
    .sch-team { display:flex; align-items:center; gap:9px; min-width:0; }
    .sch-team.is-right { flex-direction:row-reverse; text-align:right; }
    .sch-crest { width:38px; height:38px; flex:0 0 auto; border:1px solid #eaf0f7; border-radius:50%; background:#f4f7fb; object-fit:cover; }
    .sch-crest-initials { display:inline-flex; align-items:center; justify-content:center; background:#edf3fb; color:#1e3a68; font-size:10.5px; font-weight:700; letter-spacing:.2px; }
    .sch-team-name { min-width:0; font-size:.82rem; font-weight:600; line-height:1.25; color:var(--lp-navy); overflow-wrap:anywhere; }
    /* Result tones: the winning side reads green, the losing side red. Draws
       and fixtures without a result keep the neutral navy treatment. */
    .sch-team.is-winner .sch-team-name { color:#0f7b4f; }
    .sch-team.is-loser .sch-team-name { color:#dc2626; }
    .sch-scoreboard { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-width:52px; font-variant-numeric:tabular-nums; }
    .sch-score { font-size:18px; font-weight:700; line-height:1; color:var(--lp-navy); }
    .sch-score.is-winner { color:#0f7b4f; }
    .sch-score.is-loser { color:#dc2626; font-weight:600; }
    .sch-score.is-draw { color:#475569; }
    .sch-score-sep { font-size:11px; font-weight:500; color:#cbd5e1; }
    .sch-vs { display:inline-flex; align-items:center; justify-content:center; font-size:9.5px; font-weight:600; letter-spacing:.8px; color:var(--lp-muted); }
    .sch-score-pending { font-size:9px; font-weight:600; letter-spacing:.35px; text-transform:uppercase; color:var(--lp-muted); white-space:nowrap; }

    .sch-match-foot { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px 12px; margin-top:auto; padding:0 18px 10px; }
    .sch-meta { display:inline-flex; align-items:center; flex-wrap:wrap; gap:5px 12px; min-width:0; }
    .sch-meta-item { display:inline-flex; align-items:center; gap:6px; min-width:0; font-size:11px; color:var(--lp-muted); overflow-wrap:anywhere; }
    .sch-meta-item i { flex:0 0 auto; font-size:11px; color:#94a3b8; }
    .sch-foot-actions { display:inline-flex; align-items:center; gap:12px; flex:0 0 auto; margin-left:auto; }
    .sch-conflict { display:inline-flex; align-items:center; gap:5px; font-size:9px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; color:#b45309; }
    .sch-conflict i { font-size:10px; }

    /* ---------- Loading / error helpers ------------------------------------ */
    .sch-state { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:46px 24px; border:1px dashed #d7e0ec; border-radius:12px; background:#fbfdff; text-align:center; }
    .sch-state p { margin:0; font-size:13px; color:var(--lp-muted); }
    .sch-state > i { font-size:1.6rem; color:#94a3b8; }
    .sch-state.is-error > i { color:#b45309; }
    .sch-spinner { width:26px; height:26px; border:2px solid #dbe6f2; border-top-color:var(--lp-blue); border-radius:50%; animation:sch-spin .8s linear infinite; }
    .sch-reset { display:inline-flex; align-items:center; justify-content:center; gap:7px; margin-top:4px; padding:8px 16px; border:1.5px solid #dbe6f2; border-radius:8px; background:#fff; color:var(--lp-navy); font-size:12.5px; font-weight:600; cursor:pointer; transition:background .18s ease, border-color .18s ease, color .18s ease; }
    .sch-reset:hover { border-color:var(--lp-navy); background:var(--lp-navy); color:#fff; }
    .lp-empty .sch-reset { margin-top:14px; }

    /* ---------- Bracket board ---------------------------------------------- */
    .sch-bracket-board { --sch-bracket-line:#4b5563; position:relative; overflow:hidden; padding:16px 18px 18px; border:1px solid #2f3540; border-radius:12px; background:radial-gradient(circle at 20% 0%, #232a33 0%, #191d23 55%, #15181d 100%); box-shadow:inset 0 1px 0 rgba(255,255,255,.04), 0 12px 28px rgba(9,23,60,.12); }
    .sch-bracket-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px 16px; margin-bottom:14px; }
    .sch-bracket-tools { display:flex; align-items:center; flex-wrap:wrap; gap:10px 16px; margin-left:auto; }
    .sch-bracket-stages { display:flex; align-items:center; flex-wrap:wrap; gap:6px; min-width:0; }
    .sch-bracket-stage-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border:1px solid #3b424c; border-radius:20px; background:#23282f; color:#aab4c0; font-size:11px; font-weight:600; letter-spacing:.3px; cursor:pointer; transition:border-color .18s ease, background .18s ease, color .18s ease; }
    .sch-bracket-stage-pill:hover { border-color:#64748b; color:#e2e8f0; }
    .sch-bracket-stage-pill.is-active { border-color:#22c55e; background:rgba(34,197,94,.12); color:#86efac; }
    .sch-bracket-stage-pill:focus-visible { outline:2px solid rgba(34,197,94,.55); outline-offset:2px; }
    .sch-bracket-stage-pill small { font-size:9.5px; font-weight:500; color:#7d8794; }
    .sch-bracket-stage-pill.is-active small { color:#4ade80; }
    .sch-bracket-refresh { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:5px 11px; border:1px solid #3b424c; border-radius:8px; background:#23282f; color:#aab4c0; font-size:10.5px; font-weight:600; cursor:pointer; transition:border-color .18s ease, background .18s ease, color .18s ease; }
    .sch-bracket-refresh:hover:not(:disabled) { border-color:#64748b; background:#2b323a; color:#f1f5f9; }
    .sch-bracket-refresh:disabled { cursor:default; opacity:.6; }
    .sch-bracket-refresh.is-busy i { animation:sch-spin .9s linear infinite; }
    .sch-bracket-refresh:focus-visible { outline:2px solid rgba(34,197,94,.55); outline-offset:2px; }
    .sch-bracket-legend { display:flex; align-items:center; flex-wrap:wrap; gap:6px 14px; font-size:10.5px; font-weight:500; letter-spacing:.25px; color:#8d97a4; }
    .sch-bracket-legend span { display:inline-flex; align-items:center; gap:6px; }
    .sch-bracket-dot { width:7px; height:7px; border-radius:50%; }
    .sch-bracket-dot.is-win { background:#22c55e; }
    .sch-bracket-dot.is-bye { background:#64748b; }
    .sch-bracket-dot.is-tbd { background:#475569; }
    .sch-bracket-scroll { overflow-x:auto; padding:2px 2px 6px; scrollbar-width:thin; scrollbar-color:#4b5563 transparent; }
    .sch-bracket-scroll::-webkit-scrollbar { display:block; height:6px; }
    .sch-bracket-scroll::-webkit-scrollbar-track { background:transparent; }
    .sch-bracket-scroll::-webkit-scrollbar-thumb { border-radius:99px; background:#3f4a57; }
    .sch-bracket-table { width:100%; max-width:1280px; margin:0 auto; min-width:660px; border-collapse:separate; border-spacing:0; }
    .sch-bracket-table.is-compact { width:auto; min-width:300px; }
    .sch-bracket-table th { padding:0 0 12px; border:0; color:#7f8a97; font-size:9.5px; font-weight:700; letter-spacing:.9px; text-align:center; text-transform:uppercase; white-space:nowrap; }
    .sch-bracket-table th.is-final { color:#d7dde5; }
    .sch-bracket-table td { position:relative; padding:7px 0; vertical-align:middle; }
    .sch-bracket-table .sch-bracket-cell { padding-right:34px; }
    .sch-bracket-cell::after { content:''; position:absolute; top:50%; right:0; width:34px; border-top:2px solid var(--sch-bracket-line); }
    .sch-bracket-table .sch-bracket-cell.is-final { padding-right:0; }
    .sch-bracket-cell.is-final::after { display:none; }
    .sch-bracket-table .sch-bracket-cell.has-feeders { padding-left:34px; }
    .sch-bracket-cell.has-feeders::before { content:''; position:absolute; left:0; top:25%; height:50%; border-left:2px solid var(--sch-bracket-line); }
    .sch-bracket-match { position:relative; display:flex; flex-direction:column; min-width:196px; overflow:hidden; border:1px solid #3a414b; border-radius:5px; background:linear-gradient(180deg,#2c3138 0%,#262b31 100%); box-shadow:0 2px 6px rgba(0,0,0,.24); }
    .sch-bracket-cell.has-feeders .sch-bracket-match::before { content:''; position:absolute; left:-34px; top:50%; width:34px; border-top:2px solid var(--sch-bracket-line); }
    .sch-bracket-match.is-bye { border-style:dashed; background:#23272d; }
    .sch-bracket-match.is-tbd { background:#23272d; }
    .sch-bracket-row { display:flex; align-items:center; gap:8px; padding:9px 11px; }
    .sch-bracket-row + .sch-bracket-row { border-top:1px solid #3a414b; }
    .sch-bracket-row.is-winner { background:rgba(34,197,94,.055); }
    .sch-bracket-name { flex:1 1 auto; min-width:0; overflow:hidden; color:#e5e9ee; font-size:11.5px; font-weight:600; line-height:1.35; text-overflow:ellipsis; white-space:nowrap; }
    .sch-bracket-row.is-winner .sch-bracket-name,
    .sch-bracket-row.is-winner .sch-bracket-score { color:#4ade80; }
    .sch-bracket-row.is-bye .sch-bracket-name,
    .sch-bracket-row.is-tbd .sch-bracket-name { color:#7f8a97; font-style:italic; font-weight:500; }
    .sch-bracket-check { flex:0 0 auto; color:#22c55e; font-size:11px; }
    .sch-bracket-score { flex:0 0 auto; min-width:18px; color:#f8fafc; font-size:11.5px; font-weight:700; font-variant-numeric:tabular-nums; text-align:right; }
    .sch-bracket-row.is-loser .sch-bracket-name,
    .sch-bracket-row.is-loser .sch-bracket-score { color:#f87171; }
    .sch-bracket-row.is-loser .sch-bracket-score { font-weight:600; }
    .sch-bracket-section + .sch-bracket-section { margin-top:22px; }
    .sch-bracket-section-head { display:flex; align-items:center; gap:8px; margin:0 0 10px; color:#cbd5e1; font-size:10.5px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; }
    .sch-bracket-section-head i { color:#22c55e; font-size:12px; }
    .sch-bracket-section.is-losers .sch-bracket-section-head i { color:#f59e0b; }
    .sch-bracket-section.is-grand_final .sch-bracket-section-head i { color:#eab308; }
    .sch-bracket-section-head span { margin-left:auto; color:#7f8a97; font-size:9.5px; font-weight:600; letter-spacing:.3px; }
    .sch-bracket-columns { display:flex; gap:26px; overflow-x:auto; padding-bottom:6px; }
    .sch-bracket-column { flex:0 0 220px; display:flex; flex-direction:column; gap:10px; }
    .sch-bracket-column-head { color:#7f8a97; font-size:9.5px; font-weight:700; letter-spacing:.9px; text-transform:uppercase; }
    .sch-bracket-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; padding:42px 20px; border:1px dashed #3b424c; border-radius:9px; background:rgba(255,255,255,.015); text-align:center; }
    .sch-bracket-empty i { color:#64748b; font-size:1.7rem; }
    .sch-bracket-empty p { margin:0; max-width:48ch; color:#8d97a4; font-size:12.5px; line-height:1.6; }
    .sch-bracket-empty strong { color:#cbd5e1; }
    .sch-bracket-loading { display:flex; align-items:center; justify-content:center; gap:10px; padding:42px 20px; color:#8d97a4; font-size:12px; }
    .sch-bracket-spinner { width:20px; height:20px; border:2px solid #3b424c; border-top-color:#22c55e; border-radius:50%; animation:sch-spin .8s linear infinite; }


    /* ---------- Responsive ------------------------------------------------- */
    @media (max-width:767.98px) {
        .sch-match-head { padding-left:14px; padding-right:14px; }
        .sch-match-body { padding:7px 14px 9px; gap:8px; }
        .sch-match-foot { padding-left:14px; padding-right:14px; padding-bottom:9px; }
        .sch-team { gap:8px; }
        .sch-team-name { font-size:.8rem; }
        .sch-crest { width:34px; height:34px; }
        .sch-crest-initials { font-size:10px; }
        .sch-scoreboard { min-width:48px; }
        .sch-head-tools { width:100%; justify-content:space-between; }
        .sch-bracket-board { padding:14px 12px; }
        .sch-bracket-table .sch-bracket-cell { padding-right:22px; }
        .sch-bracket-cell::after { width:22px; }
        .sch-bracket-table .sch-bracket-cell.has-feeders { padding-left:22px; }
        .sch-bracket-cell.has-feeders .sch-bracket-match::before { left:-22px; width:22px; }
        .sch-bracket-match { min-width:170px; }
        .sch-bracket-table { min-width:560px; }
    }
    @media (max-width:575.98px) {
        .sch-toolbar { align-items:stretch; }
        .sch-select { flex:1 1 100%; width:100%; min-width:0; }
        .sch-select .form-select { width:100%; }
        .sch-toolbar-end { margin-left:0; }
        .sch-refresh { width:100%; }
        .sch-head-tools { flex-wrap:wrap; gap:10px; }
        .sch-view-toggle { width:100%; }
        .sch-view-btn { flex:1 1 0; }
        .sch-bracket-board { padding:12px 10px; }
        .sch-bracket-toolbar { align-items:flex-start; }
        .sch-bracket-tools { width:100%; justify-content:space-between; }
        .sch-bracket-legend { font-size:10px; }
        .sch-day-head { align-items:flex-start; }
        .sch-day-label { font-size:10.5px; letter-spacing:.4px; }
        .sch-match-head { align-items:flex-start; padding-top:10px; }
        .sch-match-body { grid-template-columns:minmax(0,1fr) auto minmax(0,1fr); gap:6px; padding:6px 12px 9px; }
        .sch-team, .sch-team.is-right { flex-direction:column; justify-content:flex-start; gap:5px; text-align:center; }
        .sch-team-name { font-size:.78rem; }
        .sch-crest { width:32px; height:32px; }
        .sch-crest-initials { font-size:9.5px; }
        .sch-scoreboard { min-width:40px; gap:4px; }
        .sch-score { font-size:16px; }
        .sch-score-pending { font-size:8.5px; letter-spacing:.2px; }
        .sch-status { font-size:9.5px; letter-spacing:.4px; }
        .sch-live { font-size:9px; }
        .sch-match-foot { padding-left:12px; padding-right:12px; padding-bottom:8px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .sch-spinner, .sch-refresh.is-busy i, .sch-bracket-spinner, .sch-bracket-refresh.is-busy i { animation:none; }
        .sch-match { transition:none; }
        .sch-match:hover { transform:none; }
    }
</style>

<div class="container-fluid lp-page sch-page">
    <section class="lp-panel">
        <div class="lp-panel-head">
            <h2 class="lp-panel-title">
                <span class="lp-panel-icon"><i class="bi bi-calendar-week" aria-hidden="true"></i></span>Match schedule
            </h2>
            <div class="sch-head-tools">
                <div class="sch-view-toggle" role="group" aria-label="Match schedule view">
                    <button type="button" class="sch-view-btn is-active" data-sch-view="list" aria-pressed="true">
                        <i class="bi bi-list-ul" aria-hidden="true"></i><span>Fixtures</span>
                    </button>
                    <button type="button" class="sch-view-btn" data-sch-view="bracket" aria-pressed="false">
                        <i class="bi bi-diagram-3" aria-hidden="true"></i><span>Bracket</span>
                    </button>
                </div>
                
            </div>
        </div>

        <div id="fixturesViewWrapper">
            <div class="sch-toolbar" role="group" aria-label="Schedule filters">
                <div class="sch-select">
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    <select id="statusFilter" class="form-select" aria-label="Filter matches by status">
                        <option value="">All matches</option>
                        <option value="Upcoming">Upcoming</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <div class="sch-select">
                    <i class="bi bi-sort-down" aria-hidden="true"></i>
                    <select id="sortFilter" class="form-select" aria-label="Sort matches">
                        <option value="date_asc">Earliest first</option>
                        <option value="date_desc">Latest first</option>
                    </select>
                </div>
                <div class="sch-toolbar-end">
                    <button type="button" class="sch-refresh" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span>Refresh</span>
                    </button>
                </div>
            </div>

            <div id="scheduleContainer" aria-busy="true">
                <div class="sch-state">
                    <span class="sch-spinner" aria-hidden="true"></span>
                    <p>Loading fixtures&hellip;</p>
                </div>
            </div>

            <div id="emptyState" class="lp-empty d-none">
                <i class="bi bi-calendar-x" aria-hidden="true"></i>
                <h5>No matches found</h5>
                <p>Try a different status filter, or check back once the organizer publishes the fixtures.</p>
                <button type="button" class="sch-reset d-none" id="emptyReset">Clear status filter</button>
            </div>
        </div>

        <div id="bracketViewWrapper" class="d-none">
            <div class="sch-bracket-board">
                <div class="sch-bracket-toolbar">
                    <div class="sch-bracket-stages" id="bracketStageBar" role="group" aria-label="Knockout stages"></div>
                    <div class="sch-bracket-tools">
                        <div class="sch-bracket-legend" aria-label="Bracket legend">
                            <span><i class="sch-bracket-dot is-win" aria-hidden="true"></i>Winner</span>
                            <span><i class="sch-bracket-dot is-bye" aria-hidden="true"></i>Bye</span>
                            <span><i class="sch-bracket-dot is-tbd" aria-hidden="true"></i>To be decided</span>
                        </div>
                        <button type="button" class="sch-bracket-refresh" id="bracketRefreshBtn">
                            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span>Refresh</span>
                        </button>
                    </div>
                </div>
                <div id="bracketContainer" aria-live="polite">
                    <div class="sch-bracket-loading">
                        <span class="sch-bracket-spinner" aria-hidden="true"></span>
                        <span>Loading bracket&hellip;</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    const leagueId = <?php echo (int) $leagueId; ?>;
    const teamId = <?php echo (int) $teamId; ?>;

    const scheduleState = {
        matches: [],
        bracketMatches: [],
        loading: false,
        error: false,
        renderedAt: 0,
        view: 'list',
        bracketStageKey: '',
        visibleCount: 0
    };
    const scheduleEls = {};
    let scheduleRequestId = 0;

    document.addEventListener('DOMContentLoaded', function () {
        scheduleEls.container = document.getElementById('scheduleContainer');
        scheduleEls.emptyState = document.getElementById('emptyState');
        scheduleEls.emptyReset = document.getElementById('emptyReset');
        scheduleEls.statusFilter = document.getElementById('statusFilter');
        scheduleEls.sortFilter = document.getElementById('sortFilter');
        scheduleEls.refreshBtn = document.getElementById('refreshBtn');
        scheduleEls.count = document.getElementById('matchCount');
        scheduleEls.countText = document.getElementById('matchCountText');
        scheduleEls.fixturesView = document.getElementById('fixturesViewWrapper');
        scheduleEls.bracketView = document.getElementById('bracketViewWrapper');
        scheduleEls.bracketContainer = document.getElementById('bracketContainer');
        scheduleEls.bracketStageBar = document.getElementById('bracketStageBar');
        scheduleEls.bracketRefreshBtn = document.getElementById('bracketRefreshBtn');

        scheduleEls.statusFilter.addEventListener('change', applyScheduleFilter);
        scheduleEls.sortFilter.addEventListener('change', applyScheduleSort);
        scheduleEls.refreshBtn.addEventListener('click', loadSchedule);
        if (scheduleEls.bracketRefreshBtn) {
            scheduleEls.bracketRefreshBtn.addEventListener('click', loadSchedule);
        }

        document.querySelectorAll('[data-sch-view]').forEach(function (button) {
            button.addEventListener('click', function () {
                setScheduleView(this.getAttribute('data-sch-view'));
            });
        });

        if (scheduleEls.bracketStageBar) {
            scheduleEls.bracketStageBar.addEventListener('click', function (event) {
                const button = event.target.closest('[data-bracket-stage]');
                if (!button) return;
                scheduleState.bracketStageKey = button.getAttribute('data-bracket-stage') || '';
                renderBracket();
            });
        }

        scheduleEls.emptyReset.addEventListener('click', function () {
            scheduleEls.statusFilter.value = '';
            applyScheduleFilter();
        });

        scheduleEls.container.addEventListener('click', function (event) {
            if (event.target.closest('[data-sch-retry]')) {
                loadSchedule();
            }
        });

        // Deep link: schedule.php?id=X#bracket opens the bracket view.
        if (window.location.hash === '#bracket') {
            setScheduleView('bracket', false);
        }

        loadSchedule();

        // Promote fixtures whose kick-off time has passed without re-fetching
        // the whole board every half minute.
        window.setInterval(function () {
            if (scheduleState.loading || !scheduleState.matches.length) return;
            const now = Date.now();
            const justStarted = scheduleState.matches.some(function (match) {
                const key = String(match.status || '').toLowerCase();
                const startsAt = matchTimestamp(match);
                return (key === 'upcoming' || key === 'scheduled')
                    && startsAt > scheduleState.renderedAt
                    && startsAt <= now;
            });
            if (justStarted) applyScheduleFilter();
        }, 30000);
    });

    function setScheduleLoading(isLoading) {
        scheduleState.loading = isLoading;
        if (scheduleEls.container) {
            scheduleEls.container.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        }
        if (scheduleEls.refreshBtn) {
            scheduleEls.refreshBtn.classList.toggle('is-busy', isLoading);
            scheduleEls.refreshBtn.disabled = isLoading;
            scheduleEls.refreshBtn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        }
        if (scheduleEls.bracketRefreshBtn) {
            scheduleEls.bracketRefreshBtn.classList.toggle('is-busy', isLoading);
            scheduleEls.bracketRefreshBtn.disabled = isLoading;
            scheduleEls.bracketRefreshBtn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        }
    }

    function loadSchedule() {
        const requestId = ++scheduleRequestId;

        scheduleState.error = false;
        scheduleEls.emptyState.classList.add('d-none');
        setScheduleLoading(true);
        scheduleEls.container.innerHTML = '<div class="sch-state">' +
            '<span class="sch-spinner" aria-hidden="true"></span>' +
            '<p>Loading fixtures&hellip;</p>' +
        '</div>';
        if (scheduleState.view === 'bracket') renderBracket();

        $.ajax({
            url: 'includes/publicSchedule.php',
            type: 'POST',
            data: {
                getSchedule: true,
                league_id: leagueId || 0,
                // Byes complete the bracket picture; the fixtures list filters
                // them out below so they never read as playable matches.
                include_byes: 1,
                // Ask for the full draw separately: drafts are needed to show the
                // whole tree before every fixture is published.
                include_bracket: 1
            },
            dataType: 'json'
        }).done(function (res) {
            if (requestId !== scheduleRequestId) return;
            if (!res || res.status !== 200) {
                renderScheduleError();
                return;
            }

            let matches = Array.isArray(res.schedule) ? res.schedule.slice() : [];

            // The bracket uses its own payload: it includes draft rounds so the
            // public tree is complete, while unpublished fixture details are
            // already blanked by the endpoint. Older payloads fall back to the
            // published list.
            scheduleState.bracketMatches = Array.isArray(res.bracket)
                ? res.bracket.slice()
                : matches.slice();

            // Optional ?team_id= scope (used by team-specific fixture links).
            if (teamId) {
                matches = matches.filter(function (match) {
                    return Number(match.team_a) === teamId || Number(match.team_b) === teamId;
                });
            }

            scheduleState.matches = sortMatches(matches.filter(function (match) {
                return !isByeMatch(match);
            }));
            applyScheduleFilter();
        }).fail(function () {
            if (requestId !== scheduleRequestId) return;
            renderScheduleError();
        }).always(function () {
            if (requestId !== scheduleRequestId) return;
            setScheduleLoading(false);
            if (scheduleState.view === 'bracket') renderBracket();
        });
    }

    function sortMatches(matches) {
        const sort = scheduleEls.sortFilter.value;
        return matches.slice().sort(function (a, b) {
            const delta = matchTimestamp(a) - matchTimestamp(b);
            return sort === 'date_desc' ? -delta : delta;
        });
    }

    function applyScheduleSort() {
        scheduleState.matches = sortMatches(scheduleState.matches);
        applyScheduleFilter();
    }

    function applyScheduleFilter() {
        const wanted = scheduleEls.statusFilter.value.trim().toLowerCase();
        const visible = wanted === ''
            ? scheduleState.matches.slice()
            : scheduleState.matches.filter(function (match) {
                return resolveStatusLabel(match).toLowerCase() === wanted;
            });

        renderSchedule(visible);
    }

    function renderScheduleError() {
        scheduleState.error = true;
        scheduleState.matches = [];
        scheduleState.bracketMatches = [];
        scheduleEls.emptyState.classList.add('d-none');
        updateMatchCount(0);
        scheduleEls.container.innerHTML = '<div class="sch-state is-error">' +
            '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>' +
            '<p>We could not load the match schedule. Please try again.</p>' +
            '<button type="button" class="sch-reset" data-sch-retry>Try again</button>' +
        '</div>';
    }

    function renderSchedule(matches) {
        scheduleState.renderedAt = Date.now();
        scheduleEls.container.innerHTML = '';

        if (!Array.isArray(matches) || matches.length === 0) {
            updateMatchCount(0);
            toggleEmptyReset();
            const noMatchesCopy = scheduleEls.emptyState.querySelector('p');
            if (noMatchesCopy) {
                noMatchesCopy.textContent = (teamId && scheduleEls.statusFilter.value === '')
                    ? 'This team has no published fixtures yet. Check back once the organizer releases the schedule.'
                    : 'Try a different status filter, or check back once the organizer publishes the fixtures.';
            }
            scheduleEls.emptyState.classList.remove('d-none');
            return;
        }

        scheduleEls.emptyState.classList.add('d-none');
        updateMatchCount(matches.length);

        const groups = new Map();
        matches.forEach(function (match) {
            const key = String(match.date || '').trim() || 'unknown';
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(match);
        });

        const sections = [];
        groups.forEach(function (dayMatches, dateKey) {
            const dayLabel = dateKey === 'unknown' ? 'Date to be announced' : formatDate(dateKey);
            const matchWord = dayMatches.length === 1 ? 'match' : 'matches';
            sections.push('<section class="sch-day" aria-label="' + escapeHtml(dayLabel) + '">' +
                '<div class="sch-day-head">' +
                    '<span class="sch-day-label"><i class="bi bi-calendar3" aria-hidden="true"></i>' + escapeHtml(dayLabel) + '</span>' +
                    '<span class="sch-day-count">' + dayMatches.length + ' ' + matchWord + '</span>' +
                '</div>' +
                '<div class="sch-day-list">' + dayMatches.map(renderMatchCard).join('') + '</div>' +
            '</section>');
        });

        scheduleEls.container.innerHTML = sections.join('');
    }

    function updateMatchCount(count) {
        if (!scheduleEls.count || !scheduleEls.countText) return;
        scheduleState.visibleCount = count;
        scheduleEls.countText.textContent = count + ' match' + (count === 1 ? '' : 'es');
        scheduleEls.count.classList.toggle('d-none', count === 0 || scheduleState.view === 'bracket');
    }

    function toggleEmptyReset() {
        if (!scheduleEls.emptyReset) return;
        scheduleEls.emptyReset.classList.toggle('d-none', scheduleEls.statusFilter.value === '');
    }

    /* ---------- Bracket view ------------------------------------------------ */
    // The bracket is drawn from the same published schedule payload as the
    // fixtures list. BYE rows are requested from the endpoint so every slot in
    // the tree stays visible, then filtered out of the fixture cards above.

    function setScheduleView(viewKey, persist) {
        const isBracket = viewKey === 'bracket';
        scheduleState.view = isBracket ? 'bracket' : 'list';

        if (scheduleEls.fixturesView) {
            scheduleEls.fixturesView.classList.toggle('d-none', isBracket);
        }
        if (scheduleEls.bracketView) {
            scheduleEls.bracketView.classList.toggle('d-none', !isBracket);
        }
        document.querySelectorAll('[data-sch-view]').forEach(function (button) {
            const active = button.getAttribute('data-sch-view') === scheduleState.view;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        updateMatchCount(scheduleState.visibleCount);

        if (isBracket) renderBracket();

        // Keep the bracket deep-linkable without making the browser jump.
        if (persist !== false) {
            try {
                const target = window.location.pathname + window.location.search + (isBracket ? '#bracket' : '');
                window.history.replaceState(null, '', target);
            } catch (error) {
                // History is a bonus; the view already changed.
            }
        }
    }

    // A bye slot is the one-team row the bracket generator auto-completes.
    function isByeMatch(match) {
        return Number(match.team_b) === 0 && String(match.seed_b || '').toUpperCase() === 'BYE';
    }

    function isPublishedMatch(match) {
        if (match.is_published === true || match.is_published === 1 || match.is_published === '1') return true;
        return match.published_at !== null
            && match.published_at !== undefined
            && String(match.published_at).trim() !== '';
    }

    function isBracketMatch(match) {
        const stageType = String(match.stage_type || '').toLowerCase();
        const stageFormat = String(match.stage_format || '').toLowerCase();
        if (stageType === 'placement') return false;
        if (stageType === 'knockout') return true;
        if (stageFormat === 'single_elimination' || stageFormat === 'double_elimination') return true;
        // Legacy rows created before stages existed only carry the bracket tag.
        return Number(match.stage_id || 0) === 0 && String(match.bracket || '').toLowerCase() === 'knockout';
    }

    function bracketStageKeyFor(match) {
        const stageId = Number(match.stage_id || 0);
        if (stageId > 0) return 'stage-' + stageId;
        // Legacy rows have no stage id; keep differently named brackets apart.
        const legacyName = String(match.stage || match.stage_name || '').trim().toLowerCase();
        return 'legacy-' + (legacyName !== '' ? legacyName.replace(/[^a-z0-9]+/g, '-') : 'bracket');
    }

    function bracketStagesFromSchedule(matches) {
        const stages = {};
        (matches || []).forEach(function (match) {
            if (!isBracketMatch(match)) return;
            const key = bracketStageKeyFor(match);
            if (!stages[key]) {
                const stageName = String(match.stage_name || '').trim()
                    || String(match.stage || '').trim()
                    || 'Playoff bracket';
                stages[key] = {
                    key: key,
                    name: stageName,
                    format: String(match.stage_format || '').toLowerCase(),
                    slots: 0,
                    matches: 0
                };
            }
            stages[key].slots += 1;
            if (!isByeMatch(match)) stages[key].matches += 1;
        });
        return Object.keys(stages).map(function (key) { return stages[key]; })
            .sort(function (a, b) {
                const idOf = function (key) {
                    return key.indexOf('stage-') === 0 ? Number(key.slice(6)) : 0;
                };
                return idOf(a.key) - idOf(b.key);
            });
    }
    function renderBracket() {
        const container = scheduleEls.bracketContainer;
        if (!container) return;

        if (scheduleState.error) {
            if (scheduleEls.bracketStageBar) scheduleEls.bracketStageBar.innerHTML = '';
            container.innerHTML = bracketEmptyState(
                'We could not load the bracket. Use <strong>Refresh</strong> to try again.',
                'bi-exclamation-triangle'
            );
            return;
        }

        if (scheduleState.loading && (!scheduleState.bracketMatches || scheduleState.bracketMatches.length === 0)) {
            container.innerHTML = '<div class="sch-bracket-loading">' +
                '<span class="sch-bracket-spinner" aria-hidden="true"></span>' +
                '<span>Loading bracket&hellip;</span>' +
            '</div>';
            return;
        }

        const stages = bracketStagesFromSchedule(scheduleState.bracketMatches);
        if (!stages.length) {
            if (scheduleEls.bracketStageBar) scheduleEls.bracketStageBar.innerHTML = '';
            container.innerHTML = bracketEmptyState(
                '<strong>No knockout bracket yet.</strong> The bracket appears here once the organizer publishes the playoff fixtures.',
                'bi-diagram-3'
            );
            return;
        }

        if (!stages.some(function (stage) { return stage.key === scheduleState.bracketStageKey; })) {
            scheduleState.bracketStageKey = stages[0].key;
        }
        const activeStage = stages.find(function (stage) { return stage.key === scheduleState.bracketStageKey; }) || stages[0];

        if (scheduleEls.bracketStageBar) {
            scheduleEls.bracketStageBar.innerHTML = stages.map(function (stage) {
                const active = stage.key === activeStage.key;
                const formatLabel = stage.format === 'double_elimination'
                    ? 'Double elimination'
                    : (stage.format === 'single_elimination' ? 'Single elimination' : 'Knockout');
                const countLabel = stage.matches + (stage.matches === 1 ? ' match' : ' matches');
                return '<button type="button" class="sch-bracket-stage-pill' + (active ? ' is-active' : '') + '"' +
                    ' data-bracket-stage="' + escapeHtml(stage.key) + '"' +
                    ' aria-pressed="' + (active ? 'true' : 'false') + '"' +
                    ' title="' + escapeHtml(stage.name + ' \u00b7 ' + formatLabel) + '">' +
                    escapeHtml(stage.name) + '<small>' + escapeHtml(countLabel) + '</small>' +
                '</button>';
            }).join('');
        }

        const stageMatches = scheduleState.bracketMatches.filter(function (match) {
            return isBracketMatch(match) && bracketStageKeyFor(match) === activeStage.key;
        });
        container.innerHTML = renderBracketStage(stageMatches);
    }

    function bracketEmptyState(message, icon) {
        return '<div class="sch-bracket-empty">' +
            '<i class="bi ' + (icon || 'bi-diagram-3') + '" aria-hidden="true"></i>' +
            '<p>' + message + '</p>' +
        '</div>';
    }

    function bracketMatchSide(match) {
        return String(match.bracket_side || '').toLowerCase() || 'winners';
    }

    function renderBracketStage(matches) {
        const sides = ['winners', 'losers', 'grand_final', 'consolation'].filter(function (side) {
            return matches.some(function (match) { return bracketMatchSide(match) === side; });
        });

        // Single elimination (or one side only): one clean tree, no section head.
        if (sides.length <= 1) return renderBracketRounds(matches);

        const sections = [
            { key: 'winners', label: 'Winners bracket', icon: 'bi-arrow-up-right-circle' },
            { key: 'losers', label: 'Losers bracket', icon: 'bi-arrow-down-right-circle' },
            { key: 'grand_final', label: 'Grand final', icon: 'bi-trophy-fill' },
            { key: 'consolation', label: 'Consolation', icon: 'bi-award' }
        ];

        let html = '';
        sections.forEach(function (section) {
            const subset = matches.filter(function (match) {
                return bracketMatchSide(match) === section.key;
            });
            if (!subset.length) return;
            html += '<div class="sch-bracket-section is-' + section.key + '">' +
                '<div class="sch-bracket-section-head">' +
                    '<i class="bi ' + section.icon + '" aria-hidden="true"></i>' + section.label +
                    '<span>' + subset.length + ' fixture' + (subset.length === 1 ? '' : 's') + '</span>' +
                '</div>' +
                renderBracketRounds(subset) +
            '</div>';
        });
        return html;
    }
    function renderBracketRounds(matches) {
        const byRound = {};
        matches.forEach(function (match) {
            const round = Number(match.round || 1) || 1;
            if (!byRound[round]) byRound[round] = [];
            byRound[round].push(match);
        });
        const roundNumbers = Object.keys(byRound).map(Number).sort(function (a, b) { return a - b; });
        if (!roundNumbers.length) {
            return bracketEmptyState(
                '<strong>Nothing published yet.</strong> This knockout stage has no published fixtures.',
                'bi-calendar-x'
            );
        }
        roundNumbers.forEach(function (round) {
            byRound[round].sort(compareBracketMatches);
        });
        const rounds = roundNumbers.map(function (round) { return byRound[round]; });
        const baseRows = rounds[0].length;

        // A classic bracket halves every round. A custom multi-pool playoff can
        // skip a size, so fall back to plain columns instead of misaligning the
        // connector lines.
        const canSpan = rounds.every(function (list, index) {
            if (!list.length || baseRows % list.length !== 0) return false;
            return index === 0 || rounds[index - 1].length === list.length * 2;
        });
        if (!canSpan) {
            return '<div class="sch-bracket-columns">' + rounds.map(function (list, index) {
                return '<div class="sch-bracket-column">' +
                    '<div class="sch-bracket-column-head">' + bracketRoundLabel(rounds.length, index + 1) + '</div>' +
                    list.map(function (match) { return bracketCard(match, index + 1, rounds.length); }).join('') +
                '</div>';
            }).join('') + '</div>';
        }

        const compact = rounds.length <= 1;
        let html = '<div class="sch-bracket-scroll"><table class="sch-bracket-table' + (compact ? ' is-compact' : '') + '">' +
            '<thead><tr>';
        rounds.forEach(function (list, index) {
            html += '<th scope="col"' + (index === rounds.length - 1 ? ' class="is-final"' : '') + '>' +
                bracketRoundLabel(rounds.length, index + 1) +
            '</th>';
        });
        html += '</tr></thead><tbody>';

        for (let row = 0; row < baseRows; row++) {
            html += '<tr>';
            rounds.forEach(function (list, index) {
                const span = baseRows / list.length;
                if (row % span !== 0) return;
                const match = list[row / span];
                if (!match) return;
                const feeds = index > 0 && rounds[index - 1].length === list.length * 2;
                const classes = 'sch-bracket-cell' + (feeds ? ' has-feeders' : '')
                    + (index === rounds.length - 1 ? ' is-final' : '');
                html += '<td rowspan="' + span + '" class="' + classes + '">' +
                    bracketCard(match, index + 1, rounds.length) +
                '</td>';
            });
            html += '</tr>';
        }
        html += '</tbody></table></div>';
        return html;
    }

    function compareBracketMatches(a, b) {
        const aNode = String(a.node_key || '').trim();
        const bNode = String(b.node_key || '').trim();
        if (aNode !== '' && bNode !== '' && aNode !== bNode) {
            return aNode.localeCompare(bNode, undefined, { numeric: true, sensitivity: 'base' });
        }
        return Number(a.id || 0) - Number(b.id || 0);
    }

    function bracketCard(match, roundNumber, roundCount) {
        const bye = isByeMatch(match);
        const published = isPublishedMatch(match);
        const status = String(match.status || '').toLowerCase();
        const isTbd = !bye && Number(match.team_a) === 0 && Number(match.team_b) === 0;
        const classes = ['sch-bracket-match'];
        if (bye) classes.push('is-bye');
        else if (isTbd) classes.push('is-tbd');
        if (!published && !bye) classes.push('is-pending');
        if (published && status === 'completed') classes.push('is-complete');

        const title = bracketMatchTitle(match, roundNumber, roundCount);
        return '<div class="' + classes.join(' ') + '" title="' + escapeHtml(title) + '" aria-label="' + escapeHtml(title) + '">' +
            bracketSide(match, 'a') +
            bracketSide(match, 'b') +
        '</div>';
    }

    function bracketSide(match, side) {
        const bye = isByeMatch(match);
        const isByeSlot = bye && side === 'b';
        const name = String((side === 'a' ? match.team_a_name : match.team_b_name) || '').trim();
        const teamId = Number(side === 'a' ? match.team_a : match.team_b) || 0;
        const status = String(match.status || '').toLowerCase();
        const completed = isPublishedMatch(match) && status === 'completed';
        const winner = Number(match.winner || 0);
        const isWinner = !bye && completed && winner > 0 && teamId === winner;
        const advanced = bye && side === 'a';
        const isTbd = !bye && teamId === 0;

        const rawScore = side === 'a' ? match.a_score : match.b_score;
        const scoreText = completed && !bye
            ? (rawScore === null || rawScore === undefined || String(rawScore).trim() === '' ? '0' : String(rawScore))
            : '';

        const rowClasses = ['sch-bracket-row'];
        if (isWinner || advanced) rowClasses.push('is-winner');
        if (isByeSlot) rowClasses.push('is-bye');
        else if (isTbd) rowClasses.push('is-tbd');
        if (completed && !bye && winner > 0 && !isWinner) rowClasses.push('is-loser');

        const displayName = name !== '' ? name : (isByeSlot ? 'BYE' : 'TBD');
        return '<div class="' + rowClasses.join(' ') + '">' +
            '<span class="sch-bracket-name">' + escapeHtml(displayName) + '</span>' +
            (isWinner || advanced ? '<i class="bi bi-check2 sch-bracket-check" aria-label="Winner" title="Winner"></i>' : '') +
            '<span class="sch-bracket-score">' + escapeHtml(scoreText) + '</span>' +
        '</div>';
    }

    function bracketRoundLabel(roundCount, roundNumber) {
        const fromEnd = roundCount - roundNumber + 1;
        if (fromEnd === 1) return 'Final';
        if (fromEnd === 2) return 'Semifinal';
        if (fromEnd === 3) return 'Quarterfinal';
        if (fromEnd === 4) return 'Round of 16';
        return 'Round of ' + Math.pow(2, fromEnd);
    }

    function bracketMatchTitle(match, roundNumber, roundCount) {
        const teamA = teamName(match.team_a_name);
        const teamB = isByeMatch(match) ? 'BYE' : teamName(match.team_b_name);
        const date = String(match.date || '').trim();
        const time = String(match.time || '').trim();
        const when = [date !== '' ? formatDate(date) : '', time !== '' ? formatTime(time) : '']
            .filter(function (part) { return part !== ''; })
            .join(' \u00b7 ');
        return teamA + ' vs ' + teamB + ' \u2014 ' + bracketRoundLabel(roundCount, roundNumber)
            + (when !== '' ? ' \u2014 ' + when : '')
            + (!isPublishedMatch(match) && !isByeMatch(match) ? ' \u2014 Pending' : '');
    }






    /**
     * Winner/loser tone for a completed fixture. The stored winner id is the
     * source of truth; the score comparison is only a fallback for legacy rows.
     */
    function matchResultTone(match, statusLabel) {
        const tones = { a: '', b: '' };
        if (String(statusLabel || '').toLowerCase() !== 'completed') return tones;

        const teamA = Number(match.team_a) || 0;
        const teamB = Number(match.team_b) || 0;
        let winner = Number(match.winner) || 0;

        if (winner <= 0) {
            const rawA = match.a_score;
            const rawB = match.b_score;
            const hasA = rawA !== null && rawA !== undefined && String(rawA).trim() !== '';
            const hasB = rawB !== null && rawB !== undefined && String(rawB).trim() !== '';
            if (hasA && hasB) {
                const scoreA = parseInt(rawA, 10) || 0;
                const scoreB = parseInt(rawB, 10) || 0;
                if (scoreA > scoreB) winner = teamA;
                else if (scoreB > scoreA) winner = teamB;
            }
        }

        if (winner > 0 && winner === teamA) {
            tones.a = 'is-winner';
            tones.b = teamB > 0 ? 'is-loser' : '';
        } else if (winner > 0 && winner === teamB) {
            tones.a = teamA > 0 ? 'is-loser' : '';
            tones.b = 'is-winner';
        }
        return tones;
    }

    function renderMatchCard(match) {
        const statusLabel = resolveStatusLabel(match);
        const teamA = teamName(match.team_a_name);
        const teamB = teamName(match.team_b_name);
        const resultTone = matchResultTone(match, statusLabel);
        const venue = String(match.venue || '').trim();
        const isLive = String(match.livestream_status || '').toLowerCase() === 'live'
            && String(match.livestream_url || '').trim() !== '';
        const isConflict = Number(match.conflict_detected) === 1;

        const liveLink = isLive
            ? '<a class="sch-live" href="live.php?id=' + encodeURIComponent(match.id) + '" target="_blank" rel="noopener" aria-label="' + escapeHtml('Watch live stream: ' + teamA + ' vs ' + teamB) + '">Watch live</a>'
            : '';
        const venueItem = venue !== ''
            ? '<span class="sch-meta-item"><i class="bi bi-geo-alt" aria-hidden="true"></i>' + escapeHtml(venue) + '</span>'
            : '';
        const conflictItem = isConflict
            ? '<span class="sch-conflict"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>Schedule conflict</span>'
            : '';
        const footerLeft = venueItem + conflictItem;
        const footerActions = liveLink !== '' ? '<div class="sch-foot-actions">' + liveLink + '</div>' : '';
        const footer = (footerLeft !== '' || footerActions !== '')
            ? '<footer class="sch-match-foot"><div class="sch-meta">' + footerLeft + '</div>' + footerActions + '</footer>'
            : '';
        const cardLabel = teamA + ' vs ' + teamB + ', ' + formatDate(match.date) + ' at ' + formatTime(match.time);

        return '<article class="sch-match" aria-label="' + escapeHtml(cardLabel) + '">' +
            '<header class="sch-match-head">' +
                '<div class="sch-when">' +
                    '<span class="sch-time"><i class="bi bi-clock" aria-hidden="true"></i>' + escapeHtml(formatTime(match.time)) + '</span>' +
                    renderMatchContext(match) +
                '</div>' +
                '<span class="sch-status ' + statusTone(statusLabel) + '">' + escapeHtml(statusLabel) + '</span>' +
            '</header>' +
            '<div class="sch-match-body">' +
                renderTeam(match.team_a_name, match.team_a_image, false, resultTone.a) +
                renderScoreboard(match, statusLabel) +
                renderTeam(match.team_b_name, match.team_b_image, true, resultTone.b) +
            '</div>' +
            footer +
        '</article>';
    }

    function renderTeam(name, image, alignRight, tone) {
        const imageValue = String(image || '').trim();
        const crest = imageValue !== ''
            ? '<img class="sch-crest" src="' + escapeHtml(getTeamImgUrl(imageValue)) + '" alt="" loading="lazy" onerror="this.onerror=null;this.src=\'assets/images/default-league.svg\';">'
            : '<span class="sch-crest sch-crest-initials" aria-hidden="true">' + escapeHtml(teamInitials(name)) + '</span>';

        const classes = ['sch-team'];
        if (alignRight) classes.push('is-right');
        if (tone) classes.push(tone);

        return '<div class="' + classes.join(' ') + '">' +
            crest +
            '<span class="sch-team-name">' + escapeHtml(teamName(name)) + '</span>' +
        '</div>';
    }

    function teamInitials(value) {
        const parts = String(value || '').trim().split(/\s+/).filter(function (part) {
            return part !== '';
        });
        if (parts.length === 0) return '?';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    function renderScoreboard(match, statusLabel) {
        if (String(statusLabel).toLowerCase() !== 'completed') {
            return '<div class="sch-scoreboard"><span class="sch-vs">VS</span></div>';
        }

        const rawA = match.a_score;
        const rawB = match.b_score;
        const missingA = rawA === null || rawA === undefined || String(rawA).trim() === '';
        const missingB = rawB === null || rawB === undefined || String(rawB).trim() === '';

        if (missingA && missingB) {
            return '<div class="sch-scoreboard"><span class="sch-score-pending">Pending</span></div>';
        }

        const scoreA = missingA ? 0 : (parseInt(rawA, 10) || 0);
        const scoreB = missingB ? 0 : (parseInt(rawB, 10) || 0);
        const toneA = scoreA === scoreB ? 'is-draw' : (scoreA > scoreB ? 'is-winner' : 'is-loser');
        const toneB = scoreA === scoreB ? 'is-draw' : (scoreB > scoreA ? 'is-winner' : 'is-loser');

        return '<div class="sch-scoreboard" aria-label="Final score, ' + scoreA + ' to ' + scoreB + '">' +
            '<span class="sch-score ' + toneA + '">' + scoreA + '</span>' +
            '<span class="sch-score-sep" aria-hidden="true">&ndash;</span>' +
            '<span class="sch-score ' + toneB + '">' + scoreB + '</span>' +
        '</div>';
    }

    function renderMatchContext(match) {
        const parts = [];
        const group = String(match.stage_group_name || match.group_name || '').trim();
        const stage = String(match.stage_name || '').trim();
        if (group !== '') parts.push(group);
        if (stage !== '') parts.push(stage);
        if (parts.length === 0) return '';
        return '<span class="sch-context">' + parts.map(escapeHtml).join(' &middot; ') + '</span>';
    }

    function resolveStatusLabel(match) {
        const raw = String(match.status || '').trim();
        const key = raw.toLowerCase();
        const startsAt = matchTimestamp(match);
        if ((key === 'upcoming' || key === 'scheduled') && startsAt > 0 && startsAt <= Date.now()) {
            return 'Ongoing';
        }
        return raw !== '' ? raw : 'Scheduled';
    }

    function statusTone(label) {
        switch (String(label || '').toLowerCase()) {
            case 'completed': return 'is-completed';
            case 'ongoing': return 'is-ongoing';
            case 'pending review': return 'is-pending';
            case 'scheduled': return 'is-scheduled';
            case 'cancelled':
            case 'canceled':
            case 'postponed': return 'is-muted';
            default: return 'is-upcoming';
        }
    }

    function matchTimestamp(match) {
        const date = String(match.date || '').trim();
        const time = String(match.time || '').trim() || '00:00:00';
        if (date === '') return 0;
        const timestamp = Date.parse(date + 'T' + time);
        return Number.isNaN(timestamp) ? 0 : timestamp;
    }

    function formatDate(dateStr) {
        const value = String(dateStr || '').trim();
        if (value === '') return 'Date to be announced';
        const date = new Date(value + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function formatTime(value) {
        const raw = String(value || '').trim();
        const parts = raw.split(':');
        if (parts.length < 2) return raw !== '' ? raw : 'Time to be announced';
        let hours = parseInt(parts[0], 10);
        const minutes = parseInt(parts[1], 10);
        if (Number.isNaN(hours) || Number.isNaN(minutes)) return raw;
        const suffix = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        if (hours === 0) hours = 12;
        return hours + ':' + String(minutes).padStart(2, '0') + ' ' + suffix;
    }

    function getTeamImgUrl(filename) {
        const value = String(filename || '').trim();
        if (/^https?:\/\//i.test(value)) return value;
        if (value !== '') return 'assets/uploads/' + value;
        return 'assets/images/default-league.svg';
    }

    function teamName(value) {
        const name = String(value || '').trim();
        return name !== '' ? name : 'To be decided';
    }

    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text === null || text === undefined ? '' : text).replace(/[&<>"']/g, function (character) {
            return map[character];
        });
    }
</script>
