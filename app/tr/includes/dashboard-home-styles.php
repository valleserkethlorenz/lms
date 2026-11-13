<?php
/**
 * Team Representative dashboard (home) design layer.
 *
 * A `.tr-`-prefixed mirror of the organizer home design layer
 * (app/organizer/includes/dashboard-home-styles.php) so both dashboards share the
 * same visual language: shared navy hero deck, joined scoreboard strip, panel
 * cards with eyebrow + title, registrations list, inbox list and a planner-style
 * calendar. The hero banner itself is rendered by includes/page-hero.php.
 * Everything is scoped under `.tr-home` (or `.tr-grid` / `.tr-cal`, which only exist
 * inside it) so the other TR pages — which rely on includes/dashboard-styles.php and
 * the shared SB-Admin shell — are never affected.
 *
 * Usage:  <?php include 'includes/dashboard-home-styles.php'; ?>
 */
?>
<style>
/* ---------- Tokens ------------------------------------------------------- */
.tr-home {
    --tr-navy:#09173c; --tr-navy-2:#17356f; --tr-blue:#2f6fed;
    --tr-ink:#0d1526; --tr-faint:#98a1b2;
    --tr-line:#e4e9f2; --tr-line-soft:#eef1f7; --tr-soft:#f4f6fb;
    --tr-green:#137a4e; --tr-amber:#b26a12; --tr-red:#b42335;
    --tr-radius:14px;
    color:var(--tr-ink);
    font-variant-numeric:tabular-nums;
}
.tr-home a { text-decoration:none !important; }
.tr-home a:focus-visible,
.tr-home button:focus-visible { outline:2px solid var(--tr-blue); outline-offset:2px; }

/* ---------- Hero side widgets -------------------------------------------
   The deck chrome itself now comes from includes/header.php (.page-header) so
   the dashboard banner matches every other TR page. Only the date and clock
   widgets below are dashboard-specific. */
.tr-datecard {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    min-width:92px; padding:12px 16px; text-align:center;
    border:1px solid rgba(255,255,255,.16); border-radius:12px; background:rgba(255,255,255,.07);
}
.tr-date-dow { font-size:9px; font-weight:600; letter-spacing:.18em; text-transform:uppercase; color:rgba(255,255,255,.55); }
.tr-date-day { margin:2px 0 0; font-size:1.9rem; font-weight:600; line-height:1.05; color:#fff; }
.tr-date-my  { font-size:9.5px; font-weight:500; letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,.58); }
.tr-clock {
    display:flex; flex-direction:column; justify-content:center; gap:5px;
    min-width:156px; padding:12px 16px;
    border:1px solid rgba(255,255,255,.16); border-radius:12px; background:rgba(255,255,255,.07);
}
.tr-clock-label {
    display:inline-flex; align-items:center; gap:7px;
    font-size:9px; font-weight:600; letter-spacing:.18em; text-transform:uppercase;
    color:rgba(255,255,255,.55);
}
.tr-clock-time { font-size:1.1rem; font-weight:600; line-height:1.1; letter-spacing:.06em; color:#fff; }
.tr-clock-date { font-size:9.5px; font-weight:400; letter-spacing:.02em; color:rgba(255,255,255,.55); }
.tr-live-dot { width:6px; height:6px; border-radius:50%; background:#34d399; box-shadow:0 0 0 3px rgba(52,211,153,.16); animation:trPulse 2.4s ease-in-out infinite; }
@keyframes trPulse { 0%,100% { opacity:1; } 50% { opacity:.4; } }

/* ---------- Metric strip ------------------------------------------------- */
.tr-board {
    display:grid; grid-template-columns:repeat(4, minmax(0,1fr));
    margin-bottom:16px; overflow:hidden;
    border:1px solid var(--tr-line); border-radius:var(--tr-radius); background:#fff;
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 46px -42px rgba(13,21,38,.55);
}
.tr-metric { position:relative; display:block; padding:18px 22px 16px; color:inherit; transition:background .18s ease; }
.tr-metric + .tr-metric { border-left:1px solid var(--tr-line-soft); }
.tr-metric::after {
    content:""; position:absolute; left:0; right:0; top:0; height:2px;
    background:var(--tr-accent, var(--tr-blue));
    transform:scaleX(0); transform-origin:left; transition:transform .22s ease;
}
.tr-metric:hover { background:#fbfcff; }
.tr-metric:hover::after { transform:scaleX(1); }
.tr-metric-index { position:absolute; top:16px; right:18px; font-size:9.5px; font-weight:600; letter-spacing:.12em; color:#c2cad9; }
.tr-metric-label {
    display:flex; align-items:center; gap:7px;
    font-size:9.5px; font-weight:600; letter-spacing:.15em; text-transform:uppercase; color:#75809a;
}
.tr-metric-label i { font-size:12px; line-height:1; color:var(--tr-accent, var(--tr-blue)); }
.tr-metric-value {
    display:block; margin:8px 0 3px;
    font-size:2.25rem; font-weight:600; line-height:1; letter-spacing:-.03em; color:var(--tr-navy);
}
.tr-metric-note { display:block; font-size:11px; color:var(--tr-faint); }
.tr-metric-go {
    position:absolute; right:16px; bottom:14px; font-size:11px;
    color:var(--tr-accent, var(--tr-blue)); opacity:0; transform:translateX(-4px);
    transition:opacity .18s ease, transform .18s ease;
}
.tr-metric:hover .tr-metric-go { opacity:1; transform:translateX(0); }

/* ---------- Panels & two-column grid ------------------------------------ */
.tr-grid { display:grid; grid-template-columns:minmax(0,43fr) minmax(0,57fr); gap:16px; align-items:start; margin-bottom:20px; }
.tr-col { display:flex; flex-direction:column; gap:16px; min-width:0; }
.tr-panel {
    overflow:hidden; background:#fff;
    border:1px solid var(--tr-line); border-radius:var(--tr-radius);
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 50px -44px rgba(13,21,38,.55);
}
.tr-panel-head {
    display:flex; align-items:flex-start; justify-content:space-between; gap:14px;
    padding:18px 22px 15px; border-bottom:1px solid var(--tr-line-soft);
}
.tr-panel-eyebrow { display:block; font-size:9.5px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:#8b95a8; }
.tr-panel-title { margin:3px 0 0; font-size:1.05rem; font-weight:600; letter-spacing:-.01em; color:var(--tr-navy); }
.tr-panel-link {
    display:inline-flex; align-items:center; gap:6px; padding:6px 12px;
    border:1px solid var(--tr-line); border-radius:8px;
    font-size:11px; font-weight:600; letter-spacing:.04em; color:#4b5563;
    transition:background .16s ease, border-color .16s ease, color .16s ease;
}
.tr-panel-link i { font-size:11px; }
.tr-panel-link:hover { background:var(--tr-navy); border-color:var(--tr-navy); color:#fff !important; }
.tr-panel-link.is-accent {
    background:var(--tr-blue); border-color:var(--tr-blue); color:#fff;
    box-shadow:0 8px 16px -10px rgba(47,111,237,.95);
}
.tr-panel-link.is-accent i { color:#fff; }
.tr-panel-link.is-accent:hover {
    background:var(--tr-navy-2); border-color:var(--tr-navy-2); color:#fff !important;
    box-shadow:0 10px 20px -10px rgba(9,23,60,.85);
}
.tr-panel-count { display:inline-flex; align-items:center; padding:4px 9px; border-radius:999px; background:var(--tr-soft); color:#64708a; font-size:9.5px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap; }
button.tr-panel-link { background:none; cursor:pointer; font-family:inherit; }
button.tr-panel-link:disabled { opacity:.55; cursor:default; background:none; border-color:var(--tr-line); color:#4b5563; }
.tr-notif-tools { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; }

/* ---------- Inbox list --------------------------------------------------- */
.tr-notif-list { padding:6px 22px 10px; }
.tr-notif { position:relative; display:grid; grid-template-columns:34px minmax(0,1fr); gap:12px; align-items:flex-start; padding:12px 0; color:inherit; }
.tr-notif + .tr-notif { border-top:1px solid var(--tr-line-soft); }
.tr-notif:hover { text-decoration:none; color:inherit; }
.tr-notif:hover .tr-notif-title { color:var(--tr-blue); }
.tr-notif.is-unread::before { content:""; position:absolute; left:-22px; top:11px; bottom:11px; width:3px; border-radius:0 2px 2px 0; background:var(--tr-blue); }
.tr-notif-icon { display:grid; place-items:center; width:34px; height:34px; border-radius:10px; font-size:14px; flex:0 0 auto; }
.tr-notif-icon.is-team { background:#f3e8ff; color:#7c3aed; }
.tr-notif-icon.is-schedule { background:#e7f8ef; color:#0f7a4e; }
.tr-notif-icon.is-announcement { background:#fff4e0; color:#a16207; }
.tr-notif-icon.is-default { background:#eef1f6; color:#5b6577; }
.tr-notif-body { display:flex; flex-direction:column; gap:2px; min-width:0; }
.tr-notif-title { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:600; color:var(--tr-navy); transition:color .15s ease; }
.tr-notif:not(.is-unread) .tr-notif-title { color:#5b6577; font-weight:500; }
.tr-notif-msg { display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; line-clamp:2; overflow:hidden; font-size:11.5px; line-height:1.45; color:#8b95a8; }
.tr-notif-foot { display:flex; align-items:center; gap:10px; }
.tr-notif-time { font-size:9px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:#b3bccd; }
.tr-notif-mark {
    background:none; border:none; padding:0; margin:0;
    font-family:inherit; font-size:9px; font-weight:600; letter-spacing:.1em; text-transform:uppercase;
    color:var(--tr-blue); cursor:pointer;
}
.tr-notif-mark:hover { text-decoration:underline; }

/* ---------- Registration list ------------------------------------------- */
.tr-reg { position:relative; display:grid; grid-template-columns:26px minmax(0,1fr) auto; align-items:center; gap:12px; padding:14px 22px; border-bottom:1px solid var(--tr-line-soft); color:inherit; transition:background .16s ease; }
.tr-reg:last-child { border-bottom:0; }
.tr-reg::before { content:""; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--tr-accent, var(--tr-blue)); transform:scaleY(0); transition:transform .16s ease; }
.tr-reg:hover { background:#f8faff; }
.tr-reg:hover::before { transform:scaleY(1); }
.tr-reg-idx { font-size:10px; font-weight:600; letter-spacing:.08em; color:#b3bccd; }
.tr-reg-main { min-width:0; }
.tr-reg-name { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:13.5px; font-weight:600; line-height:1.35; color:var(--tr-navy); }
.tr-reg-meta { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:3px; font-size:11px; color:#8b95a8; }
.tr-reg-meta .tr-sep { width:3px; height:3px; border-radius:50%; background:#cbd3e0; }
.tr-status {
    display:inline-flex; align-items:center; gap:6px; padding:4px 10px; white-space:nowrap;
    border-radius:999px; font-size:9.5px; font-weight:600; letter-spacing:.1em; text-transform:uppercase;
}
.tr-status::before { content:""; width:5px; height:5px; border-radius:50%; background:currentColor; }
.tr-status.is-approved { background:#e7f8ef; color:#0f7a4e; }
.tr-status.is-pending { background:#fff4e0; color:#a16207; }
.tr-status.is-rejected { background:#fde8ec; color:#b42335; }
.tr-status.is-correction { background:#f1eafe; color:#6941c6; }
.tr-status.is-cancelled, .tr-status.is-expired, .tr-status.is-default { background:#eef1f6; color:#5b6577; }

/* ---------- Empty states ------------------------------------------------- */
.tr-empty { display:flex; flex-direction:column; align-items:center; gap:6px; padding:44px 24px; text-align:center; }
.tr-empty i { margin-bottom:4px; font-size:1.6rem; color:#c2cad9; }
.tr-empty-title { font-size:13.5px; font-weight:600; color:var(--tr-navy); }
.tr-empty-sub { max-width:34ch; font-size:11.5px; line-height:1.55; color:#8b95a8; }
.tr-empty-cta {
    display:inline-flex; align-items:center; gap:6px; margin-top:12px; padding:8px 16px;
    border-radius:7px; background:var(--tr-navy); color:#fff !important;
    font-size:12px; font-weight:600; transition:background .16s ease;
}
.tr-empty .tr-empty-cta i { margin-bottom:0; font-size:12px; color:inherit; }
.tr-empty-cta:hover { background:var(--tr-navy-2); }

/* ---------- Planner-style calendar -------------------------------------- */
.tr-cal { transition:none; }
.tr-cal-head {
    display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;
    padding:18px 22px 14px; border-bottom:1px solid var(--tr-line-soft);
}
.tr-cal-month { margin-top:3px; font-size:1.2rem; font-weight:600; letter-spacing:-.01em; color:var(--tr-navy); }
.tr-cal-tools { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.tr-cal-legend { display:flex; align-items:center; gap:10px; font-size:10px; font-weight:500; color:#8b95a8; }
.tr-legend-item { display:inline-flex; align-items:center; gap:5px; }
.tr-legend-dot { width:6px; height:6px; border-radius:50%; }
.tr-legend-dot.is-reg { background:var(--tr-blue); }
.tr-legend-dot.is-lg { background:#16a34a; }
.tr-legend-dot.is-match { background:#d97706; }
.tr-cal-nav { display:flex; align-items:center; gap:6px; }
.tr-navbtn {
    width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center;
    border:1px solid var(--tr-line); border-radius:8px; background:#fff; color:#4b5563; font-size:11px;
    transition:background .16s ease, border-color .16s ease, color .16s ease;
}
.tr-navbtn:hover { background:var(--tr-navy); border-color:var(--tr-navy); color:#fff; }
.tr-todaybtn {
    height:30px; padding:0 11px; border:1px solid var(--tr-line); border-radius:8px; background:#fff;
    font-size:10px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--tr-navy);
    transition:background .16s ease, border-color .16s ease;
}
.tr-todaybtn:hover { background:var(--tr-soft); border-color:#d3dae6; }
.tr-cal .calendar-weekdays { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); background:#fafbfe; border-bottom:1px solid var(--tr-line-soft); }
.tr-cal .calendar-weekdays div {
    padding:9px 4px; text-align:center;
    font-size:9px; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:#8b95a8;
}
.tr-cal .calendar-grid { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); }
.tr-cal .calendar-day {
    min-height:92px; padding:7px 8px; overflow:hidden;
    border-right:1px solid var(--tr-line-soft); border-bottom:1px solid var(--tr-line-soft);
    background:#fff; transition:background .15s ease;
}
.tr-cal .calendar-day:nth-child(7n) { border-right:0; }
.tr-cal .calendar-day:nth-child(n+36) { border-bottom:0; }
.tr-cal .calendar-day:hover { background:#fbfcff; }
.tr-cal .calendar-day.is-outside { background:#fcfdff; }
.tr-cal .calendar-day.is-outside .calendar-date { color:#c3cbd9; }
.tr-cal .calendar-day.is-today { background:#f4f8ff; box-shadow:inset 0 0 0 1px #cadcfb; }
.tr-cal .calendar-date {
    display:inline-flex; align-items:center; justify-content:center;
    width:22px; height:22px; border-radius:7px;
    font-size:11px; font-weight:600; color:#5b6577;
}
.tr-cal .calendar-day.is-today .calendar-date { background:var(--tr-navy); color:#fff; box-shadow:0 4px 10px -4px rgba(9,23,60,.55); }
.tr-cal .calendar-event {
    display:flex; align-items:center; gap:5px; width:100%; margin-top:4px; padding:3px 6px;
    border:0; border-radius:5px; background:#f2f6ff; color:#1d4ed8;
    font-size:9.5px; font-weight:500; line-height:1.3; text-align:left;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; cursor:pointer;
    transition:background .15s ease, color .15s ease;
}
.tr-cal .calendar-event::before {
    content:""; width:5px; height:5px; flex:0 0 auto; border-radius:50%; background:currentColor; opacity:.9;
}
.tr-cal .event-league { background:#eefaf3; color:#0f7a4e; }
.tr-cal .event-match { background:#fff5e6; color:#a16207; }
.tr-cal .calendar-event:hover { background:#e3edff; color:#17356f; }
.tr-cal .event-league:hover { background:#dcf5e8; color:#0b5c3c; }
.tr-cal .event-match:hover { background:#ffedd1; color:#8a5a00; }
.tr-cal .calendar-more { margin-top:4px; padding-left:6px; font-size:9px; font-weight:600; color:#98a1b2; }

/* Compact calendar while the registrations panel sits beside it */
.tr-grid:not(.expanded) .tr-cal .calendar-day { min-height:84px; padding:6px; }
.tr-grid:not(.expanded) .tr-cal .calendar-event {
    display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; line-clamp:2;
    margin-top:3px; padding:2px 5px 2px 6px;
    border-left:2px solid currentColor; border-radius:3px;
    font-size:9px; line-height:1.25; white-space:normal;
}
.tr-grid:not(.expanded) .tr-cal .calendar-event::before { content:none; }

/* Expanded calendar takes the full row */
.tr-grid.expanded { grid-template-columns:minmax(0,1fr); }
.tr-grid.expanded .tr-col-calendar { order:-1; }
.tr-grid.expanded .tr-cal .calendar-day { min-height:112px; }
.tr-grid.expanded .tr-cal .calendar-event { font-size:10.5px; padding:4px 7px; }

/* ---------- Responsive --------------------------------------------------- */
@media (max-width:1199.98px) {
    .tr-board { grid-template-columns:repeat(2, minmax(0,1fr)); }
    .tr-metric:nth-child(3) { border-left:0; }
    .tr-metric:nth-child(n+3) { border-top:1px solid var(--tr-line-soft); }
}
@media (max-width:991.98px) {
    .tr-grid { grid-template-columns:minmax(0,1fr); }
    .tr-grid:not(.expanded) .tr-cal .calendar-day { min-height:92px; padding:7px 8px; }
    .tr-grid:not(.expanded) .tr-cal .calendar-event { margin-top:4px; padding:3px 6px; font-size:9.5px; }
}
@media (max-width:767.98px) {
    .tr-cal-head { padding:16px; }
    .tr-cal-tools { width:100%; justify-content:space-between; }
    .tr-cal .calendar-weekdays div { padding:8px 2px; font-size:8px; letter-spacing:.08em; }
    .tr-cal .calendar-day { min-height:70px; padding:5px 4px; }
    .tr-cal .calendar-date { width:20px; height:20px; font-size:10px; }
    .tr-cal .calendar-event { height:5px; margin-top:4px; padding:0; border-radius:99px; font-size:0; }
    .tr-cal .calendar-event::before { content:none; }
    .tr-cal .event-registration { background:var(--tr-blue); }
    .tr-cal .event-league { background:#16a34a; }
    .tr-cal .event-match { background:#d97706; }
    .tr-cal .calendar-more { font-size:8px; padding-left:2px; }
    .tr-panel-head, .tr-reg { padding-left:16px; padding-right:16px; }
    .tr-notif-list { padding-left:16px; padding-right:16px; }
    .tr-notif.is-unread::before { left:-16px; }
    .tr-notif-icon { width:30px; height:30px; font-size:13px; }
    .tr-notif { grid-template-columns:30px minmax(0,1fr); gap:10px; }
}
@media (max-width:575.98px) {
    .tr-datecard { display:none; }
    .tr-clock { flex:1 1 100%; }
    .tr-metric { padding:15px 16px 14px; }
    .tr-metric-index { top:14px; right:14px; }
    .tr-metric-value { font-size:1.9rem; }
    .tr-metric-note { display:none; }
    .tr-metric-go { display:none; }
    .tr-reg { grid-template-columns:22px minmax(0,1fr); row-gap:7px; }
    .tr-reg .tr-status { grid-column:2; justify-self:start; }
    .tr-panel-title { font-size:1rem; }
    .tr-empty { padding:34px 18px; }
}

@media (prefers-reduced-motion: reduce) {
    .tr-home .dash-animate { animation:none !important; }
    .tr-live-dot { animation:none; }
    .tr-reg, .tr-notif, .tr-panel-link, .tr-navbtn, .tr-todaybtn { transition:none; }
}
</style>
