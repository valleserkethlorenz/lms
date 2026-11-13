<?php
/**
 * Committee dashboard design layer.
 *
 * Mirrors the admin home design language (app/admin/includes/dashboard-home-styles.php):
 * navy editorial hero, joined metric strip, panel cards with eyebrow + title, match calendar.
 * Every rule is scoped under `.cm-home` so the other committee pages — which rely on
 * includes/dashboard-styles.php and the shared SB-Admin shell — are never affected.
 *
 * Usage:  <?php include 'includes/dashboard-home-styles.php'; ?>
 */
?>
<style>
/* ---------- Tokens ------------------------------------------------------- */
.cm-home {
    --cm-navy:#09173c; --cm-navy-2:#17356f; --cm-blue:#2f6fed;
    --cm-ink:#0d1526; --cm-muted:#6f7a8d; --cm-faint:#98a1b2;
    --cm-line:#e4e9f2; --cm-line-soft:#eef1f7; --cm-soft:#f4f6fb;
    --cm-green:#137a4e; --cm-amber:#b26a12; --cm-radius:14px;
    color:var(--cm-ink);
    font-variant-numeric:tabular-nums;
}
.cm-home a { text-decoration:none !important; }
.cm-home a:focus-visible,
.cm-home button:focus-visible { outline:2px solid var(--cm-blue); outline-offset:2px; }

/* ---------- Hero deck ---------------------------------------------------- */
.cm-deck {
    position:relative; isolation:isolate; overflow:hidden;
    display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between;
    gap:24px; padding:28px 32px; margin-bottom:18px;
    border-radius:var(--cm-radius); color:#fff;
    background:radial-gradient(140% 190% at 100% 0%, #1d4390 0%, #0c2050 46%, #071129 100%);
    box-shadow:0 22px 44px -30px rgba(7,17,41,.9);
}
.cm-deck::before,
.cm-deck::after {
    content:""; position:absolute; z-index:-1; border-radius:50%;
    border:1px solid rgba(255,255,255,.10); pointer-events:none;
}
.cm-deck::before { top:-170px; right:-110px; width:420px; height:420px; }
.cm-deck::after  { bottom:-240px; right:80px; width:360px; height:360px; border-color:rgba(255,255,255,.06); }
.cm-court { position:absolute; z-index:-1; top:-24px; bottom:-24px; left:58%; width:1px; background:rgba(255,255,255,.08); }
.cm-deck-copy { position:relative; z-index:1; flex:1 1 320px; min-width:0; max-width:62ch; }
.cm-kicker {
    display:inline-flex; align-items:center; gap:9px;
    font-size:9.5px; font-weight:600; letter-spacing:.2em; text-transform:uppercase;
    color:rgba(255,255,255,.55);
}
.cm-kicker::before { content:""; width:20px; height:1px; background:rgba(255,255,255,.45); }
.cm-greeting {
    margin:12px 0 6px; color:#fff; line-height:1.16;
    font-size:clamp(1.5rem, 2.3vw, 2rem); font-weight:600; letter-spacing:-.015em;
}
.cm-greeting span { color:#93b8ff; }
.cm-deck-sub { margin:0; max-width:48ch; font-size:13px; line-height:1.6; color:rgba(255,255,255,.66); }
.cm-deck-side { position:relative; z-index:1; display:flex; align-items:stretch; gap:10px; flex-wrap:wrap; }
.cm-datecard {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    min-width:92px; padding:12px 16px; text-align:center;
    border:1px solid rgba(255,255,255,.16); border-radius:12px; background:rgba(255,255,255,.07);
}
.cm-date-dow { font-size:9px; font-weight:600; letter-spacing:.18em; text-transform:uppercase; color:rgba(255,255,255,.55); }
.cm-date-day { margin:2px 0 0; font-size:1.9rem; font-weight:600; line-height:1.05; color:#fff; }
.cm-date-my  { font-size:9.5px; font-weight:500; letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,.58); }
.cm-clock {
    display:flex; flex-direction:column; justify-content:center; gap:5px;
    min-width:156px; padding:12px 16px;
    border:1px solid rgba(255,255,255,.16); border-radius:12px; background:rgba(255,255,255,.07);
}
.cm-clock-label {
    display:inline-flex; align-items:center; gap:7px;
    font-size:9px; font-weight:600; letter-spacing:.18em; text-transform:uppercase;
    color:rgba(255,255,255,.55);
}
.cm-clock-time { font-size:1.1rem; font-weight:600; line-height:1.1; letter-spacing:.06em; color:#fff; }
.cm-clock-date { font-size:9.5px; font-weight:400; letter-spacing:.02em; color:rgba(255,255,255,.55); }
.cm-live-dot { width:6px; height:6px; border-radius:50%; background:#34d399; box-shadow:0 0 0 3px rgba(52,211,153,.16); animation:cmPulse 2.4s ease-in-out infinite; }
@keyframes cmPulse { 0%,100% { opacity:1; } 50% { opacity:.4; } }

/* ---------- Section heading --------------------------------------------- */
.cm-section-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:20px 2px 10px; }
.cm-section-title {
    display:inline-flex; align-items:center; gap:9px;
    font-size:10.5px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:#64708a;
}
.cm-section-title::before { content:""; width:3px; height:12px; border-radius:2px; background:var(--cm-blue); }
.cm-live { display:inline-flex; align-items:center; gap:6px; font-size:9.5px; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:#7c8698; }
.cm-live .cm-live-dot { width:5px; height:5px; box-shadow:none; }

/* ---------- Metric strip ------------------------------------------------- */
.cm-board {
    display:grid; grid-template-columns:repeat(4, minmax(0,1fr));
    margin-bottom:16px; overflow:hidden;
    border:1px solid var(--cm-line); border-radius:var(--cm-radius); background:#fff;
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 46px -42px rgba(13,21,38,.55);
}
.cm-metric { position:relative; display:block; padding:18px 20px 16px; color:inherit; transition:background .18s ease; }
.cm-metric + .cm-metric { border-left:1px solid var(--cm-line-soft); }
.cm-metric::after {
    content:""; position:absolute; left:0; right:0; top:0; height:2px;
    background:var(--cm-accent, var(--cm-blue));
    transform:scaleX(0); transform-origin:left; transition:transform .22s ease;
}
.cm-metric:hover { background:#fbfcff; }
.cm-metric:hover::after { transform:scaleX(1); }
.cm-metric-index { position:absolute; top:16px; right:16px; font-size:9.5px; font-weight:600; letter-spacing:.12em; color:#c2cad9; }
.cm-metric-label {
    display:flex; align-items:center; gap:7px;
    font-size:9.5px; font-weight:600; letter-spacing:.13em; text-transform:uppercase; color:#75809a;
}
.cm-metric-label i { font-size:12px; line-height:1; color:var(--cm-accent, var(--cm-blue)); }
.cm-metric-value {
    display:block; margin:8px 0 3px;
    font-size:2.1rem; font-weight:600; line-height:1; letter-spacing:-.03em; color:var(--cm-navy);
}
.cm-metric-note { display:block; font-size:11px; color:var(--cm-faint); }
.cm-metric-go {
    position:absolute; right:14px; bottom:14px; font-size:11px;
    color:var(--cm-accent, var(--cm-blue)); opacity:0; transform:translateX(-4px);
    transition:opacity .18s ease, transform .18s ease;
}
.cm-metric:hover .cm-metric-go { opacity:1; transform:translateX(0); }

/* ---------- Panels & two-column grid ------------------------------------ */
.cm-grid { display:grid; grid-template-columns:minmax(0,58fr) minmax(0,42fr); gap:16px; align-items:start; margin-bottom:20px; }
.cm-col { display:flex; flex-direction:column; gap:16px; min-width:0; }
.cm-panel {
    overflow:hidden; background:#fff;
    border:1px solid var(--cm-line); border-radius:var(--cm-radius);
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 50px -44px rgba(13,21,38,.55);
}
.cm-panel-head {
    display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px;
    padding:18px 22px 15px; border-bottom:1px solid var(--cm-line-soft);
}
.cm-panel-eyebrow { display:block; font-size:9.5px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:#8b95a8; }
.cm-panel-title { margin:3px 0 0; font-size:1.05rem; font-weight:600; letter-spacing:-.01em; color:var(--cm-navy); }
.cm-panel-link {
    display:inline-flex; align-items:center; gap:6px; padding:6px 12px;
    border:1px solid var(--cm-line); border-radius:8px;
    font-size:11px; font-weight:600; letter-spacing:.04em; color:#4b5563;
    transition:background .16s ease, border-color .16s ease, color .16s ease;
}
.cm-panel-link i { font-size:11px; }
.cm-panel-link:hover { background:var(--cm-navy); border-color:var(--cm-navy); color:#fff !important; }
.cm-panel-link.is-accent {
    background:var(--cm-blue); border-color:var(--cm-blue); color:#fff;
    box-shadow:0 8px 16px -10px rgba(47,111,237,.95);
}
.cm-panel-link.is-accent i { color:#fff; }
.cm-panel-link.is-accent:hover {
    background:var(--cm-navy-2); border-color:var(--cm-navy-2); color:#fff !important;
    box-shadow:0 10px 20px -10px rgba(9,23,60,.85);
}
.cm-panel-count { display:inline-flex; align-items:center; padding:4px 9px; border-radius:999px; background:var(--cm-soft); color:#64708a; font-size:9.5px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap; }
.cm-notif-tools { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
button.cm-panel-link { background:none; cursor:pointer; font-family:inherit; }
button.cm-panel-link:disabled { opacity:.55; cursor:default; background:none; border-color:var(--cm-line); color:#4b5563; }

/* ---------- Inbox list --------------------------------------------------- */
.cm-notif-list { padding:6px 22px 10px; }
.cm-notif { position:relative; display:grid; grid-template-columns:34px minmax(0,1fr); gap:12px; align-items:flex-start; padding:12px 0; color:inherit; }
.cm-notif + .cm-notif { border-top:1px solid var(--cm-line-soft); }
.cm-notif:hover { text-decoration:none; color:inherit; }
.cm-notif:hover .cm-notif-title { color:var(--cm-blue); }
.cm-notif.is-unread::before { content:""; position:absolute; left:-22px; top:11px; bottom:11px; width:3px; border-radius:0 2px 2px 0; background:var(--cm-blue); }
.cm-notif-icon { display:grid; place-items:center; width:34px; height:34px; border-radius:10px; font-size:14px; flex:0 0 auto; }
.cm-notif-icon.is-team { background:#f3e8ff; color:#7c3aed; }
.cm-notif-icon.is-schedule { background:#e7f8ef; color:#0f7a4e; }
.cm-notif-icon.is-announcement { background:#fff4e0; color:#a16207; }
.cm-notif-icon.is-default { background:#eef1f6; color:#5b6577; }
.cm-notif-body { display:flex; flex-direction:column; gap:2px; min-width:0; }
.cm-notif-title { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:600; color:var(--cm-navy); transition:color .15s ease; }
.cm-notif:not(.is-unread) .cm-notif-title { color:#5b6577; font-weight:500; }
.cm-notif-msg { display:-webkit-box; -webkit-box-orient:vertical; overflow:hidden; font-size:11.5px; line-height:1.45; color:#8b95a8; }
.cm-notif-time { font-size:9px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:#b3bccd; }
.cm-notif-foot { display:flex; align-items:center; gap:10px; }
.cm-notif-mark {
    background:none; border:none; padding:0; margin:0;
    font-family:inherit; font-size:9px; font-weight:600; letter-spacing:.1em; text-transform:uppercase;
    color:var(--cm-blue); cursor:pointer;
}
.cm-notif-mark:hover { text-decoration:underline; }

/* ---------- New accounts list ------------------------------------------- */
.cm-user-list { padding:6px 22px 12px; }
.cm-user { display:grid; grid-template-columns:32px minmax(0,1fr) auto; gap:12px; align-items:center; padding:11px 0; }
.cm-user + .cm-user { border-top:1px solid var(--cm-line-soft); }
.cm-user-avatar { display:grid; place-items:center; width:32px; height:32px; border-radius:50%; background:#e8edf7; color:var(--cm-navy); font-size:12px; font-weight:600; flex:0 0 auto; }
.cm-user-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:600; color:var(--cm-navy); }
.cm-user-meta { font-size:10.5px; color:var(--cm-faint); }
.cm-user-copy { display:flex; flex-direction:column; min-width:0; }
.cm-badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:999px; font-size:9px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; white-space:nowrap; }
.cm-badge.is-organizer { background:#dbeafe; color:#1e40af; }
.cm-badge.is-committee { background:#d1fae5; color:#065f46; }
.cm-badge.is-tr { background:#fff4e0; color:#a16207; }
.cm-badge.is-default { background:#f3f4f6; color:#374151; }

/* ---------- Audit trail table ------------------------------------------- */
.cm-table-wrap { overflow-x:auto; }
.cm-table { width:100%; margin:0; border-collapse:collapse; font-size:12.5px; }
.cm-table thead th {
    padding:10px 16px; text-align:left; white-space:nowrap;
    background:var(--cm-soft); color:#64708a;
    font-size:9px; font-weight:600; letter-spacing:.13em; text-transform:uppercase;
    border-bottom:1px solid var(--cm-line);
}
.cm-table tbody td { padding:11px 16px; vertical-align:middle; border-bottom:1px solid var(--cm-line-soft); color:#41506b; }
.cm-table tbody tr:last-child td { border-bottom:none; }
.cm-table tbody tr:hover { background:#fbfcff; }
.cm-cell-user { display:flex; align-items:center; gap:9px; min-width:0; }
.cm-cell-user span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; color:var(--cm-navy); }
.cm-cell-action { max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cm-cell-date { white-space:nowrap; font-size:11.5px; color:#7c8698; }

/* ---------- Empty state ------------------------------------------------- */
.cm-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; padding:44px 22px; text-align:center; }
.cm-empty > i { font-size:1.6rem; color:#c2cad9; }
.cm-empty-title { font-size:12.5px; font-weight:600; color:var(--cm-navy); }
.cm-empty-sub { max-width:34ch; font-size:11.5px; line-height:1.5; color:var(--cm-faint); }
.cm-empty-cta {
    display:inline-flex; align-items:center; gap:6px; margin-top:8px;
    padding:7px 14px; border-radius:8px; background:var(--cm-soft);
    font-size:11px; font-weight:600; color:#41506b; transition:background .16s ease, color .16s ease;
}
.cm-empty-cta:hover { background:var(--cm-navy); color:#fff !important; }

/* ---------- Match calendar ---------------------------------------------- */
.cm-panel.is-calendar { overflow:visible; }
.cm-cal { padding:14px 18px 18px; }
.cm-legend { display:flex; flex-wrap:wrap; align-items:center; gap:5px 14px; }
.cm-legend span { display:inline-flex; align-items:center; gap:6px; font-size:9.5px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:#8b95a8; }
.cm-legend-dot { width:7px; height:7px; border-radius:50%; display:inline-block; }
#committeeMatchCalendar { min-height:520px; font-size:12px; }
#committeeMatchCalendar .fc-toolbar { flex-wrap:wrap; gap:10px; }
#committeeMatchCalendar .fc-toolbar-title { color:var(--cm-navy); font-size:1rem; font-weight:600; letter-spacing:-.01em; }
#committeeMatchCalendar .fc-button-primary { padding:5px 11px; border-radius:7px; background:var(--cm-navy); border-color:var(--cm-navy); font-size:11px; font-weight:600; text-transform:capitalize; box-shadow:none; }
#committeeMatchCalendar .fc-button-primary:hover { background:var(--cm-navy-2); border-color:var(--cm-navy-2); }
#committeeMatchCalendar .fc-button-primary:not(:disabled):active,
#committeeMatchCalendar .fc-button-primary:not(:disabled).fc-button-active { background:var(--cm-blue); border-color:var(--cm-blue); }
#committeeMatchCalendar .fc-button-primary:disabled { background:#94a3b8; border-color:#94a3b8; opacity:1; }
#committeeMatchCalendar .fc-col-header-cell { background:var(--cm-soft); border-color:var(--cm-line); }
#committeeMatchCalendar .fc-col-header-cell-cushion { padding:8px 4px; color:#64708a; font-size:9.5px; font-weight:600; letter-spacing:.11em; text-transform:uppercase; }
#committeeMatchCalendar .fc-daygrid-day-number { padding:6px 8px; color:#41506b; font-size:11.5px; font-weight:600; }
#committeeMatchCalendar .fc-day-today { background:#f5f8ff !important; }
#committeeMatchCalendar .fc-event { border:0; border-radius:5px; padding:1px 6px; font-size:10.5px; font-weight:600; cursor:pointer; }
#committeeMatchCalendar .fc-event:hover { filter:brightness(1.08); }
#committeeMatchCalendar .fc-daygrid-event-dot { display:none; }
#committeeMatchCalendar .fc-daygrid-more-link { color:var(--cm-navy); font-size:10.5px; font-weight:600; }
#committeeMatchCalendar .fc-popover { border:1px solid var(--cm-line); border-radius:12px; box-shadow:0 20px 40px -26px rgba(7,17,41,.55); }
#committeeMatchCalendar .fc-popover-header { background:#fff; color:var(--cm-navy); font-size:11px; font-weight:600; }

/* ---------- Live now list ------------------------------------------------ */
.cm-stream-list { padding:4px 22px 14px; }
.cm-stream { padding:14px 0; }
.cm-stream + .cm-stream { border-top:1px solid var(--cm-line-soft); }
.cm-stream-top { display:flex; align-items:center; justify-content:space-between; gap:10px; }
.cm-stream-flag { display:inline-flex; align-items:center; gap:6px; padding:3px 9px; border-radius:999px; background:#fdecec; color:#b91c1c; font-size:9px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; }
.cm-stream-dot { width:6px; height:6px; border-radius:50%; background:#dc2626; box-shadow:0 0 0 3px rgba(220,38,38,.16); animation:cmPulse 1.8s ease-in-out infinite; }
.cm-stream-league { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:9.5px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--cm-faint); }
.cm-stream-match { margin:8px 0 3px; font-size:13px; font-weight:600; line-height:1.35; color:var(--cm-navy); }
.cm-stream-match span { font-weight:500; color:var(--cm-faint); }
.cm-stream-meta { display:inline-flex; align-items:center; gap:6px; font-size:11px; color:#7c8698; }
.cm-stream-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }

/* ---------- Assigned-match table ---------------------------------------- */
.cm-match { display:flex; align-items:center; gap:10px; min-width:0; }
.cm-match-copy { display:flex; flex-direction:column; min-width:0; }
.cm-match-teams { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:600; color:var(--cm-navy); }
.cm-match-league { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:10.5px; color:var(--cm-faint); }
.cm-cell-schedule { white-space:nowrap; font-size:11.5px; color:#7c8698; }
.cm-cell-schedule strong { display:block; font-size:12px; font-weight:600; color:#41506b; }
.cm-sched-venue { display:block; max-width:140px; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:10.5px; color:var(--cm-faint); }
.cm-badge.is-scheduled { background:#dbeafe; color:#1e40af; }
.cm-badge.is-postponed { background:#fff4e0; color:#a16207; }
.cm-badge.is-completed { background:#eef1f6; color:#41506b; }
.cm-badge.is-cancelled { background:#fdecec; color:#b91c1c; }
.cm-badge.is-live { background:#fdecec; color:#b91c1c; }

/* ---------- Responsive -------------------------------------------------- */
@media (max-width:1199.98px) {
    .cm-board { grid-template-columns:repeat(2, minmax(0,1fr)); }
    .cm-metric { border-left:1px solid var(--cm-line-soft); }
    .cm-metric:nth-child(odd) { border-left:none; }
    .cm-metric:nth-child(n+3) { border-top:1px solid var(--cm-line-soft); }
}
@media (max-width:991.98px) {
    .cm-grid { grid-template-columns:minmax(0,1fr); }
    .cm-deck { padding:24px 26px; }
}
@media (max-width:767.98px) {
    .cm-deck { padding:22px 20px; border-radius:12px; }
    .cm-greeting { margin-top:10px; font-size:1.35rem; }
    .cm-deck-side { width:100%; }
    .cm-datecard { display:none; }
    .cm-clock { flex:1 1 100%; }
    .cm-metric { padding:15px 16px 14px; }
    .cm-metric-index { top:14px; right:14px; }
    .cm-metric-value { font-size:1.9rem; }
    .cm-metric-note { display:none; }
    .cm-metric-go { display:none; }
    .cm-panel-head { padding-left:16px; padding-right:16px; }
    .cm-panel-title { font-size:1rem; }
    .cm-notif-list, .cm-user-list { padding-left:16px; padding-right:16px; }
    .cm-notif.is-unread::before { left:-16px; }
    .cm-notif-icon { width:30px; height:30px; font-size:13px; }
    .cm-notif { grid-template-columns:30px minmax(0,1fr); gap:10px; }
    .cm-empty { padding:34px 18px; }
    .cm-cal { padding:10px 12px 14px; }
    #committeeMatchCalendar { min-height:420px; }
    .cm-stream-list { padding-left:16px; padding-right:16px; }
    .cm-cell-action { max-width:160px; }

    /* Assigned-match table becomes a stacked list — no horizontal scrolling on phones */
    .cm-table-wrap { overflow:visible; }
    .cm-table { display:block; }
    .cm-table thead { display:none; }
    .cm-table tbody { display:block; }
    .cm-table tbody tr {
        display:grid; grid-template-columns:minmax(0,1fr) auto; gap:6px 10px;
        grid-template-areas:"match match" "schedule status" "action action";
        padding:12px 16px; border-bottom:1px solid var(--cm-line-soft);
    }
    .cm-table tbody tr:last-child { border-bottom:none; }
    .cm-table tbody td { display:block; padding:0; border:none; }
    .cm-table td.cm-cell-match { grid-area:match; }
    .cm-table td.cm-cell-schedule { grid-area:schedule; align-self:center; }
    .cm-table td.cm-cell-status { grid-area:status; justify-self:end; align-self:center; }
    .cm-table td.cm-cell-action { grid-area:action; justify-self:end; align-self:center; }
    .cm-sched-venue { max-width:none; }
}

@media (prefers-reduced-motion: reduce) {
    .cm-home .dash-animate { animation:none !important; }
    .cm-live-dot, .cm-stream-dot { animation:none; }
    .cm-metric, .cm-notif, .cm-panel-link, .cm-empty-cta, .cm-stream, .cm-metric-go { transition:none; }
}
</style>
