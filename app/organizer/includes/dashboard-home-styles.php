<?php
/**
 * Organizer dashboard (home) design layer.
 *
 * Every rule here is scoped under `.og-home` (or `.og-grid` / `.og-cal`, which
 * only exist inside it) so the shared includes/dashboard-styles.php used by the
 * admin, committee, TR and other organizer pages is never affected.
 *
 * Design language: an "organizer season desk" — shared navy hero deck, joined
 * scoreboard strip, milestone ticker, league desk list and planner-style
 * calendar. The hero banner itself is rendered by includes/page-hero.php.
 *
 * Usage:  <?php include 'includes/dashboard-home-styles.php'; ?>
 */
?>
<style>
/* ---------- Tokens ------------------------------------------------------- */
.og-home {
    --og-navy:#09173c; --og-navy-2:#17356f; --og-blue:#2f6fed;
    --og-ink:#0d1526; --og-muted:#6f7a8d; --og-faint:#98a1b2;
    --og-line:#e4e9f2; --og-line-soft:#eef1f7; --og-soft:#f4f6fb;
    --og-green:#137a4e; --og-amber:#b26a12; --og-radius:14px;
    color:var(--og-ink);
    font-variant-numeric:tabular-nums;
}
.og-home a { text-decoration:none !important; }
.og-home a:focus-visible,
.og-home button:focus-visible { outline:2px solid var(--og-blue); outline-offset:2px; }

/* ---------- Hero side widgets -------------------------------------------
   The deck chrome itself now comes from includes/header.php (.page-header) so
   the dashboard banner matches every other organizer page. Only the date and
   clock widgets below are dashboard-specific. */
.og-datecard {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    min-width:92px; padding:12px 16px; text-align:center;
    border:1px solid rgba(255,255,255,.16); border-radius:12px; background:rgba(255,255,255,.07);
}
.og-date-dow { font-size:9px; font-weight:600; letter-spacing:.18em; text-transform:uppercase; color:rgba(255,255,255,.55); }
.og-date-day { margin:2px 0 0; font-size:1.9rem; font-weight:600; line-height:1.05; color:#fff; }
.og-date-my  { font-size:9.5px; font-weight:500; letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,.58); }
.og-clock {
    display:flex; flex-direction:column; justify-content:center; gap:5px;
    min-width:156px; padding:12px 16px;
    border:1px solid rgba(255,255,255,.16); border-radius:12px; background:rgba(255,255,255,.07);
}
.og-clock-label {
    display:inline-flex; align-items:center; gap:7px;
    font-size:9px; font-weight:600; letter-spacing:.18em; text-transform:uppercase;
    color:rgba(255,255,255,.55);
}
.og-clock-time { font-size:1.1rem; font-weight:600; line-height:1.1; letter-spacing:.06em; color:#fff; }
.og-clock-date { font-size:9.5px; font-weight:400; letter-spacing:.02em; color:rgba(255,255,255,.55); }
.og-live-dot { width:6px; height:6px; border-radius:50%; background:#34d399; box-shadow:0 0 0 3px rgba(52,211,153,.16); animation:ogPulse 2.4s ease-in-out infinite; }
@keyframes ogPulse { 0%,100% { opacity:1; } 50% { opacity:.4; } }

/* ---------- Scoreboard strip -------------------------------------------- */
.og-board {
    display:grid; grid-template-columns:repeat(4, minmax(0,1fr));
    margin-bottom:16px; overflow:hidden;
    border:1px solid var(--og-line); border-radius:var(--og-radius); background:#fff;
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 46px -42px rgba(13,21,38,.55);
}
.og-metric { position:relative; display:block; padding:18px 22px 16px; color:inherit; transition:background .18s ease; }
.og-metric + .og-metric { border-left:1px solid var(--og-line-soft); }
.og-metric::after {
    content:""; position:absolute; left:0; right:0; top:0; height:2px;
    background:var(--og-accent, var(--og-blue));
    transform:scaleX(0); transform-origin:left; transition:transform .22s ease;
}
.og-metric:hover { background:#fbfcff; }
.og-metric:hover::after { transform:scaleX(1); }
.og-metric-index { position:absolute; top:16px; right:18px; font-size:9.5px; font-weight:600; letter-spacing:.12em; color:#c2cad9; }
.og-metric-label {
    display:flex; align-items:center; gap:7px;
    font-size:9.5px; font-weight:600; letter-spacing:.15em; text-transform:uppercase; color:#75809a;
}
.og-metric-label i { font-size:12px; line-height:1; color:var(--og-accent, var(--og-blue)); }
.og-metric-value {
    display:block; margin:8px 0 3px;
    font-size:2.25rem; font-weight:600; line-height:1; letter-spacing:-.03em; color:var(--og-navy);
}
.og-metric-note { display:block; font-size:11px; color:var(--og-faint); }
.og-metric-go {
    position:absolute; right:16px; bottom:14px; font-size:11px;
    color:var(--og-accent, var(--og-blue)); opacity:0; transform:translateX(-4px);
    transition:opacity .18s ease, transform .18s ease;
}
.og-metric:hover .og-metric-go { opacity:1; transform:translateX(0); }

/* ---------- Inbox / notification panel (left column) -------------------- */
.og-notif-tools { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
button.og-panel-link { background:none; cursor:pointer; font-family:inherit; }
button.og-panel-link:disabled { opacity:.55; cursor:default; background:none; border-color:var(--og-line); color:#4b5563; }
.og-notif-list { padding:6px 22px 10px; }
.og-notif { position:relative; display:grid; grid-template-columns:34px minmax(0,1fr); gap:12px; align-items:flex-start; padding:12px 0; color:inherit; }
.og-notif + .og-notif { border-top:1px solid var(--og-line-soft); }
.og-notif:hover { text-decoration:none; color:inherit; }
.og-notif:hover .og-notif-title { color:var(--og-blue); }
.og-notif.is-unread::before { content:""; position:absolute; left:-22px; top:11px; bottom:11px; width:3px; border-radius:0 2px 2px 0; background:var(--og-blue); }
.og-notif-icon { display:grid; place-items:center; width:34px; height:34px; border-radius:10px; font-size:14px; flex:0 0 auto; }
.og-notif-icon.is-team { background:#f3e8ff; color:#7c3aed; }
.og-notif-icon.is-schedule { background:#e7f8ef; color:#0f7a4e; }
.og-notif-icon.is-announcement { background:#fff4e0; color:#a16207; }
.og-notif-icon.is-default { background:#eef1f6; color:#5b6577; }
.og-notif-body { display:flex; flex-direction:column; gap:2px; min-width:0; }
.og-notif-title { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:600; color:var(--og-navy); transition:color .15s ease; }
.og-notif-msg { display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden; font-size:11.5px; line-height:1.45; color:#8b95a8; }
.og-notif-time { font-size:9px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:#b3bccd; }
.og-stack { display:flex; flex-direction:column; gap:16px; min-width:0; }

/* ---------- Panels & two-column grid ------------------------------------ */
.og-grid { display:grid; grid-template-columns:minmax(0,43fr) minmax(0,57fr); gap:16px; align-items:start; margin-bottom:20px; }
.og-grid.expanded { grid-template-columns:minmax(0,1fr); }
.og-grid.expanded .og-col-calendar { order:-1; }
.og-panel {
    overflow:hidden; background:#fff;
    border:1px solid var(--og-line); border-radius:var(--og-radius);
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 50px -44px rgba(13,21,38,.55);
}
.og-panel-head {
    display:flex; align-items:flex-start; justify-content:space-between; gap:14px;
    padding:18px 22px 15px; border-bottom:1px solid var(--og-line-soft);
}
.og-panel-eyebrow { display:block; font-size:9.5px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:#8b95a8; }
.og-panel-title { margin:3px 0 0; font-size:1.05rem; font-weight:600; letter-spacing:-.01em; color:var(--og-navy); }
.og-panel-link {
    display:inline-flex; align-items:center; gap:6px; padding:6px 12px;
    border:1px solid var(--og-line); border-radius:8px;
    font-size:11px; font-weight:600; letter-spacing:.04em; color:#4b5563;
    transition:background .16s ease, border-color .16s ease, color .16s ease;
}
.og-panel-link i { font-size:11px; }
.og-panel-link:hover { background:var(--og-navy); border-color:var(--og-navy); color:#fff !important; }
.og-panel-link.is-accent {
    background:var(--og-blue); border-color:var(--og-blue); color:#fff;
    box-shadow:0 8px 16px -10px rgba(47,111,237,.95);
}
.og-panel-link.is-accent i { color:#fff; }
.og-panel-link.is-accent:hover {
    background:var(--og-navy-2); border-color:var(--og-navy-2); color:#fff !important;
    box-shadow:0 10px 20px -10px rgba(9,23,60,.85);
}
.og-panel-count { display:inline-flex; align-items:center; padding:4px 9px; border-radius:999px; background:var(--og-soft); color:#64708a; font-size:9.5px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap; }

/* ---------- League desk list -------------------------------------------- */
.og-league {
    position:relative; display:grid; grid-template-columns:26px minmax(0,1fr) auto;
    align-items:center; gap:12px; padding:14px 22px;
    border-bottom:1px solid var(--og-line-soft); color:inherit;
    transition:background .16s ease;
}
.og-league:last-child { border-bottom:0; }
.og-league::before {
    content:""; position:absolute; left:0; top:0; bottom:0; width:3px;
    background:var(--og-accent, var(--og-blue));
    transform:scaleY(0); transition:transform .16s ease;
}
.og-league:hover { background:#f8faff; }
.og-league:hover::before { transform:scaleY(1); }
.og-league-idx { font-size:10px; font-weight:600; letter-spacing:.08em; color:#b3bccd; }
.og-league-main { min-width:0; }
.og-league-name { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:13.5px; font-weight:600; line-height:1.35; color:var(--og-navy); }
.og-league-meta { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:3px; font-size:11px; color:#8b95a8; }
.og-league-meta .og-sep { width:3px; height:3px; border-radius:50%; background:#cbd3e0; }
.og-league-meta .og-sport { text-transform:capitalize; }
.og-track { display:block; height:3px; margin-top:9px; border-radius:99px; background:#edf0f7; overflow:hidden; }
.og-track-fill { display:block; height:100%; border-radius:99px; background:var(--og-accent, var(--og-blue)); }
.og-status {
    display:inline-flex; align-items:center; gap:6px; padding:4px 10px; white-space:nowrap;
    border-radius:999px; font-size:9.5px; font-weight:600; letter-spacing:.1em; text-transform:uppercase;
}
.og-status::before { content:""; width:5px; height:5px; border-radius:50%; background:currentColor; }
.og-status.is-open { background:#e7f8ef; color:#0f7a4e; }
.og-status.is-ongoing { background:#e8effd; color:#1d4ed8; }
.og-status.is-upcoming { background:#fff4e0; color:#a16207; }
.og-status.is-completed { background:#eef1f6; color:#5b6577; }
.og-status.is-draft { background:#f1f3f8; color:#64708a; }
.og-empty { display:flex; flex-direction:column; align-items:center; gap:6px; padding:44px 24px; text-align:center; }
.og-empty i { margin-bottom:4px; font-size:1.6rem; color:#c2cad9; }
.og-empty-title { font-size:13.5px; font-weight:600; color:var(--og-navy); }
.og-empty-sub { max-width:32ch; font-size:11.5px; line-height:1.55; color:#8b95a8; }
.og-empty-cta {
    display:inline-flex; align-items:center; gap:6px; margin-top:12px; padding:8px 16px;
    border-radius:8px; background:var(--og-navy); color:#fff !important;
    font-size:11.5px; font-weight:600; transition:background .16s ease;
}
.og-empty-cta:hover { background:var(--og-navy-2); }

/* ---------- Planner-style calendar -------------------------------------- */
.og-cal { transition:none; }
.og-cal-head {
    display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;
    padding:18px 22px 14px; border-bottom:1px solid var(--og-line-soft);
}
.og-cal-month { margin-top:3px; font-size:1.2rem; font-weight:600; letter-spacing:-.01em; color:var(--og-navy); }
.og-cal-tools { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.og-cal-legend { display:flex; align-items:center; gap:10px; font-size:10px; font-weight:500; color:#8b95a8; }
.og-legend-item { display:inline-flex; align-items:center; gap:5px; }
.og-legend-dot { width:6px; height:6px; border-radius:50%; }
.og-legend-dot.is-reg { background:var(--og-blue); }
.og-legend-dot.is-lg { background:#16a34a; }
.og-cal-nav { display:flex; align-items:center; gap:6px; }
.og-navbtn {
    width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center;
    border:1px solid var(--og-line); border-radius:8px; background:#fff; color:#4b5563; font-size:11px;
    transition:background .16s ease, border-color .16s ease, color .16s ease;
}
.og-navbtn:hover { background:var(--og-navy); border-color:var(--og-navy); color:#fff; }
.og-todaybtn {
    height:30px; padding:0 11px; border:1px solid var(--og-line); border-radius:8px; background:#fff;
    font-size:10px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--og-navy);
    transition:background .16s ease, border-color .16s ease;
}
.og-todaybtn:hover { background:var(--og-soft); border-color:#d3dae6; }
.og-cal .calendar-weekdays { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); background:#fafbfe; border-bottom:1px solid var(--og-line-soft); }
.og-cal .calendar-weekdays div {
    padding:9px 4px; text-align:center;
    font-size:9px; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:#8b95a8;
}
.og-cal .calendar-grid { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); }
.og-cal .calendar-day {
    min-height:92px; padding:7px 8px; overflow:hidden;
    border-right:1px solid var(--og-line-soft); border-bottom:1px solid var(--og-line-soft);
    background:#fff; transition:background .15s ease;
}
.og-cal .calendar-day:nth-child(7n) { border-right:0; }
.og-cal .calendar-day:nth-child(n+36) { border-bottom:0; }
.og-cal .calendar-day:hover { background:#fbfcff; }
.og-cal .calendar-day.is-outside { background:#fcfdff; }
.og-cal .calendar-day.is-outside .calendar-date { color:#c3cbd9; }
.og-cal .calendar-day.is-today { background:#f4f8ff; box-shadow:inset 0 0 0 1px #cadcfb; }
.og-cal .calendar-date {
    display:inline-flex; align-items:center; justify-content:center;
    width:22px; height:22px; border-radius:7px;
    font-size:11px; font-weight:600; color:#5b6577;
}
.og-cal .calendar-day.is-today .calendar-date { background:var(--og-navy); color:#fff; box-shadow:0 4px 10px -4px rgba(9,23,60,.55); }
.og-cal .calendar-event {
    display:flex; align-items:center; gap:5px; width:100%; margin-top:4px; padding:3px 6px;
    border:0; border-radius:5px; background:#f2f6ff; color:#1d4ed8;
    font-size:9.5px; font-weight:500; line-height:1.3; text-align:left;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; cursor:pointer;
    transition:background .15s ease, color .15s ease;
}
.og-cal .calendar-event::before {
    content:""; width:5px; height:5px; flex:0 0 auto; border-radius:50%; background:currentColor; opacity:.9;
}
.og-cal .event-league { background:#eefaf3; color:#0f7a4e; }
.og-cal .calendar-event:hover { background:#e3edff; color:#17356f; }
.og-cal .event-league:hover { background:#dcf5e8; color:#0b5c3c; }
.og-cal .calendar-more { margin-top:4px; padding-left:6px; font-size:9px; font-weight:600; color:#98a1b2; }

/* Compact calendar while the league desk sits beside it */
.og-grid:not(.expanded) .og-cal .calendar-day { min-height:84px; padding:6px; }
.og-grid:not(.expanded) .og-cal .calendar-event {
    display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2;
    margin-top:3px; padding:2px 5px 2px 6px;
    border-left:2px solid currentColor; border-radius:3px;
    font-size:9px; line-height:1.25; white-space:normal;
}
.og-grid:not(.expanded) .og-cal .calendar-event::before { content:none; }

/* Expanded calendar takes the full row */
.og-grid.expanded .og-cal .calendar-day { min-height:112px; }
.og-grid.expanded .og-cal .calendar-event { font-size:10.5px; padding:4px 7px; }

/* ---------- Responsive --------------------------------------------------- */
@media (max-width:1199.98px) {
    .og-board { grid-template-columns:repeat(2, minmax(0,1fr)); }
    .og-metric:nth-child(3) { border-left:0; }
    .og-metric:nth-child(n+3) { border-top:1px solid var(--og-line-soft); }
}
@media (max-width:991.98px) {
    .og-grid { grid-template-columns:minmax(0,1fr); }
    .og-grid:not(.expanded) .og-cal .calendar-day { min-height:92px; padding:7px 8px; }
    .og-grid:not(.expanded) .og-cal .calendar-event { margin-top:4px; padding:3px 6px; font-size:9.5px; }
}
@media (max-width:767.98px) {
    .og-cal-head { padding:16px; }
    .og-cal-tools { width:100%; justify-content:space-between; }
    .og-cal .calendar-weekdays div { padding:8px 2px; font-size:8px; letter-spacing:.08em; }
    .og-cal .calendar-day { min-height:70px; padding:5px 4px; }
    .og-cal .calendar-date { width:20px; height:20px; font-size:10px; }
    .og-cal .calendar-event { height:5px; margin-top:4px; padding:0; border-radius:99px; font-size:0; }
    .og-cal .calendar-event::before { content:none; }
    .og-cal .event-registration { background:var(--og-blue); }
    .og-cal .event-league { background:#16a34a; }
    .og-cal .calendar-more { font-size:8px; padding-left:2px; }
    .og-panel-head, .og-league { padding-left:16px; padding-right:16px; }
    .og-notif-list { padding-left:16px; padding-right:16px; }
    .og-notif.is-unread::before { left:-16px; }
    .og-notif-icon { width:30px; height:30px; font-size:13px; }
    .og-notif { grid-template-columns:30px minmax(0,1fr); gap:10px; }
}
@media (max-width:575.98px) {
    .og-datecard { display:none; }
    .og-clock { flex:1 1 100%; }
    .og-metric { padding:15px 16px 14px; }
    .og-metric-index { top:14px; right:14px; }
    .og-metric-value { font-size:1.9rem; }
    .og-metric-note { display:none; }
    .og-metric-go { display:none; }
    .og-league { grid-template-columns:22px minmax(0,1fr); row-gap:7px; }
    .og-league .og-status { grid-column:2; justify-self:start; }
    .og-panel-title { font-size:1rem; }
    .og-empty { padding:34px 18px; }
}

@media (prefers-reduced-motion: reduce) {
    .og-home .dash-animate { animation:none !important; }
    .og-live-dot { animation:none; }
    .og-league, .og-notif, .og-panel-link, .og-navbtn, .og-todaybtn { transition:none; }
}
</style>
