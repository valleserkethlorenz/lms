<?php
/**
 * Admin dashboard design layer.
 *
 * Mirrors the organizer home design language (app/organizer/includes/dashboard-home-styles.php):
 * joined metric strip, panel cards with eyebrow + title, inbox list. The hero
 * banner itself is shared through includes/page-hero.php + includes/header.php.
 * Every rule here is scoped under `.ad-home` so the other admin pages are never affected.
 *
 * Usage:  <?php include 'includes/dashboard-home-styles.php'; ?>
 */
?>
<style>
/* ---------- Tokens ------------------------------------------------------- */
.ad-home {
    --ad-navy:#09173c; --ad-navy-2:#17356f; --ad-blue:#2f6fed;
    --ad-ink:#0d1526; --ad-muted:#6f7a8d; --ad-faint:#98a1b2;
    --ad-line:#e4e9f2; --ad-line-soft:#eef1f7; --ad-soft:#f4f6fb;
    --ad-green:#137a4e; --ad-amber:#b26a12; --ad-radius:14px;
    color:var(--ad-ink);
    font-variant-numeric:tabular-nums;
}
.ad-home a { text-decoration:none !important; }
.ad-home a:focus-visible,
.ad-home button:focus-visible { outline:2px solid var(--ad-blue); outline-offset:2px; }

/* ---------- Hero side widgets -------------------------------------------
   The deck chrome itself now comes from includes/header.php (.page-header) so
   the dashboard banner matches every other admin page. Only the date + clock
   widgets below are dashboard-specific. */
.ad-datecard {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    min-width:92px; padding:12px 16px; text-align:center;
    border:1px solid rgba(255,255,255,.16); border-radius:12px; background:rgba(255,255,255,.07);
}
.ad-date-dow { font-size:9px; font-weight:600; letter-spacing:.18em; text-transform:uppercase; color:rgba(255,255,255,.55); }
.ad-date-day { margin:2px 0 0; font-size:1.9rem; font-weight:600; line-height:1.05; color:#fff; }
.ad-date-my  { font-size:9.5px; font-weight:500; letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,.58); }
.ad-clock {
    display:flex; flex-direction:column; justify-content:center; gap:5px;
    min-width:156px; padding:12px 16px;
    border:1px solid rgba(255,255,255,.16); border-radius:12px; background:rgba(255,255,255,.07);
}
.ad-clock-label {
    display:inline-flex; align-items:center; gap:7px;
    font-size:9px; font-weight:600; letter-spacing:.18em; text-transform:uppercase;
    color:rgba(255,255,255,.55);
}
.ad-clock-time { font-size:1.1rem; font-weight:600; line-height:1.1; letter-spacing:.06em; color:#fff; }
.ad-clock-date { font-size:9.5px; font-weight:400; letter-spacing:.02em; color:rgba(255,255,255,.55); }
.ad-live-dot { width:6px; height:6px; border-radius:50%; background:#34d399; box-shadow:0 0 0 3px rgba(52,211,153,.16); animation:adPulse 2.4s ease-in-out infinite; }
@keyframes adPulse { 0%,100% { opacity:1; } 50% { opacity:.4; } }

/* ---------- Metric strip ------------------------------------------------- */
.ad-board {
    display:grid; grid-template-columns:repeat(6, minmax(0,1fr));
    margin-bottom:16px; overflow:hidden;
    border:1px solid var(--ad-line); border-radius:var(--ad-radius); background:#fff;
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 46px -42px rgba(13,21,38,.55);
}
.ad-metric { position:relative; display:block; padding:18px 20px 16px; color:inherit; transition:background .18s ease; }
.ad-metric + .ad-metric { border-left:1px solid var(--ad-line-soft); }
.ad-metric::after {
    content:""; position:absolute; left:0; right:0; top:0; height:2px;
    background:var(--ad-accent, var(--ad-blue));
    transform:scaleX(0); transform-origin:left; transition:transform .22s ease;
}
.ad-metric:hover { background:#fbfcff; }
.ad-metric:hover::after { transform:scaleX(1); }
.ad-metric-index { position:absolute; top:16px; right:16px; font-size:9.5px; font-weight:600; letter-spacing:.12em; color:#c2cad9; }
.ad-metric-label {
    display:flex; align-items:center; gap:7px;
    font-size:9.5px; font-weight:600; letter-spacing:.13em; text-transform:uppercase; color:#75809a;
}
.ad-metric-label i { font-size:12px; line-height:1; color:var(--ad-accent, var(--ad-blue)); }
.ad-metric-value {
    display:block; margin:8px 0 3px;
    font-size:2.1rem; font-weight:600; line-height:1; letter-spacing:-.03em; color:var(--ad-navy);
}
.ad-metric-note { display:block; font-size:11px; color:var(--ad-faint); }
.ad-metric-go {
    position:absolute; right:14px; bottom:14px; font-size:11px;
    color:var(--ad-accent, var(--ad-blue)); opacity:0; transform:translateX(-4px);
    transition:opacity .18s ease, transform .18s ease;
}
.ad-metric:hover .ad-metric-go { opacity:1; transform:translateX(0); }

/* ---------- Panels & two-column grid ------------------------------------ */
.ad-grid { display:grid; grid-template-columns:minmax(0,43fr) minmax(0,57fr); gap:16px; align-items:start; margin-bottom:20px; }
.ad-col { display:flex; flex-direction:column; gap:16px; min-width:0; }
.ad-panel {
    overflow:hidden; background:#fff;
    border:1px solid var(--ad-line); border-radius:var(--ad-radius);
    box-shadow:0 2px 4px rgba(13,21,38,.03), 0 26px 50px -44px rgba(13,21,38,.55);
}
.ad-panel-head {
    display:flex; align-items:flex-start; justify-content:space-between; gap:14px;
    padding:18px 22px 15px; border-bottom:1px solid var(--ad-line-soft);
}
.ad-panel-eyebrow { display:block; font-size:9.5px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:#8b95a8; }
.ad-panel-title { margin:3px 0 0; font-size:1.05rem; font-weight:600; letter-spacing:-.01em; color:var(--ad-navy); }
.ad-panel-link {
    display:inline-flex; align-items:center; gap:6px; padding:6px 12px;
    border:1px solid var(--ad-line); border-radius:8px;
    font-size:11px; font-weight:600; letter-spacing:.04em; color:#4b5563;
    transition:background .16s ease, border-color .16s ease, color .16s ease;
}
.ad-panel-link i { font-size:11px; }
.ad-panel-link:hover { background:var(--ad-navy); border-color:var(--ad-navy); color:#fff !important; }
.ad-panel-link.is-accent {
    background:var(--ad-blue); border-color:var(--ad-blue); color:#fff;
    box-shadow:0 8px 16px -10px rgba(47,111,237,.95);
}
.ad-panel-link.is-accent i { color:#fff; }
.ad-panel-link.is-accent:hover {
    background:var(--ad-navy-2); border-color:var(--ad-navy-2); color:#fff !important;
    box-shadow:0 10px 20px -10px rgba(9,23,60,.85);
}
.ad-panel-count { display:inline-flex; align-items:center; padding:4px 9px; border-radius:999px; background:var(--ad-soft); color:#64708a; font-size:9.5px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap; }
.ad-notif-tools { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
button.ad-panel-link { background:none; cursor:pointer; font-family:inherit; }
button.ad-panel-link:disabled { opacity:.55; cursor:default; background:none; border-color:var(--ad-line); color:#4b5563; }

/* ---------- Inbox list --------------------------------------------------- */
.ad-notif-list { padding:6px 22px 10px; }
.ad-notif { position:relative; display:grid; grid-template-columns:34px minmax(0,1fr); gap:12px; align-items:flex-start; padding:12px 0; color:inherit; }
.ad-notif + .ad-notif { border-top:1px solid var(--ad-line-soft); }
.ad-notif:hover { text-decoration:none; color:inherit; }
.ad-notif:hover .ad-notif-title { color:var(--ad-blue); }
.ad-notif.is-unread::before { content:""; position:absolute; left:-22px; top:11px; bottom:11px; width:3px; border-radius:0 2px 2px 0; background:var(--ad-blue); }
.ad-notif-icon { display:grid; place-items:center; width:34px; height:34px; border-radius:10px; font-size:14px; flex:0 0 auto; }
.ad-notif-icon.is-team { background:#f3e8ff; color:#7c3aed; }
.ad-notif-icon.is-schedule { background:#e7f8ef; color:#0f7a4e; }
.ad-notif-icon.is-announcement { background:#fff4e0; color:#a16207; }
.ad-notif-icon.is-default { background:#eef1f6; color:#5b6577; }
.ad-notif-body { display:flex; flex-direction:column; gap:2px; min-width:0; }
.ad-notif-title { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:600; color:var(--ad-navy); transition:color .15s ease; }
.ad-notif:not(.is-unread) .ad-notif-title { color:#5b6577; font-weight:500; }
.ad-notif-msg { display:-webkit-box; -webkit-box-orient:vertical; overflow:hidden; font-size:11.5px; line-height:1.45; color:#8b95a8; }
.ad-notif-time { font-size:9px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:#b3bccd; }
.ad-notif-foot { display:flex; align-items:center; gap:10px; }
.ad-notif-mark {
    background:none; border:none; padding:0; margin:0;
    font-family:inherit; font-size:9px; font-weight:600; letter-spacing:.1em; text-transform:uppercase;
    color:var(--ad-blue); cursor:pointer;
}
.ad-notif-mark:hover { text-decoration:underline; }

/* ---------- New accounts list ------------------------------------------- */
.ad-user-list { padding:6px 22px 12px; }
.ad-user { display:grid; grid-template-columns:32px minmax(0,1fr) auto; gap:12px; align-items:center; padding:11px 0; }
.ad-user + .ad-user { border-top:1px solid var(--ad-line-soft); }
.ad-user-avatar { display:grid; place-items:center; width:32px; height:32px; border-radius:50%; background:#e8edf7; color:var(--ad-navy); font-size:12px; font-weight:600; flex:0 0 auto; }
.ad-user-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:600; color:var(--ad-navy); }
.ad-user-meta { font-size:10.5px; color:var(--ad-faint); }
.ad-user-copy { display:flex; flex-direction:column; min-width:0; }
.ad-badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:999px; font-size:9px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; white-space:nowrap; }
.ad-badge.is-organizer { background:#dbeafe; color:#1e40af; }
.ad-badge.is-committee { background:#d1fae5; color:#065f46; }
.ad-badge.is-tr { background:#fff4e0; color:#a16207; }
.ad-badge.is-default { background:#f3f4f6; color:#374151; }

/* ---------- Audit trail table ------------------------------------------- */
.ad-table-wrap { overflow-x:auto; }
.ad-table { width:100%; margin:0; border-collapse:collapse; font-size:12.5px; }
.ad-table thead th {
    padding:10px 16px; text-align:left; white-space:nowrap;
    background:var(--ad-soft); color:#64708a;
    font-size:9px; font-weight:600; letter-spacing:.13em; text-transform:uppercase;
    border-bottom:1px solid var(--ad-line);
}
.ad-table tbody td { padding:11px 16px; vertical-align:middle; border-bottom:1px solid var(--ad-line-soft); color:#41506b; }
.ad-table tbody tr:last-child td { border-bottom:none; }
.ad-table tbody tr:hover { background:#fbfcff; }
.ad-cell-user { display:flex; align-items:center; gap:9px; min-width:0; }
.ad-cell-user span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; color:var(--ad-navy); }
.ad-cell-action { max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ad-cell-date { white-space:nowrap; font-size:11.5px; color:#7c8698; }

/* ---------- Empty state ------------------------------------------------- */
.ad-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; padding:44px 22px; text-align:center; }
.ad-empty > i { font-size:1.6rem; color:#c2cad9; }
.ad-empty-title { font-size:12.5px; font-weight:600; color:var(--ad-navy); }
.ad-empty-sub { max-width:34ch; font-size:11.5px; line-height:1.5; color:var(--ad-faint); }
.ad-empty-cta {
    display:inline-flex; align-items:center; gap:6px; margin-top:8px;
    padding:7px 14px; border-radius:8px; background:var(--ad-soft);
    font-size:11px; font-weight:600; color:#41506b; transition:background .16s ease, color .16s ease;
}
.ad-empty-cta:hover { background:var(--ad-navy); color:#fff !important; }

/* ---------- Responsive -------------------------------------------------- */
@media (max-width:1199.98px) {
    .ad-board { grid-template-columns:repeat(2, minmax(0,1fr)); }
    .ad-metric { border-left:1px solid var(--ad-line-soft); }
    .ad-metric:nth-child(odd) { border-left:none; }
    .ad-metric:nth-child(n+3) { border-top:1px solid var(--ad-line-soft); }
}
@media (max-width:991.98px) {
    .ad-grid { grid-template-columns:minmax(0,1fr); }
}
@media (max-width:767.98px) {
    .ad-datecard { display:none; }
    .ad-clock { flex:1 1 100%; }
    .ad-metric { padding:15px 16px 14px; }
    .ad-metric-index { top:14px; right:14px; }
    .ad-metric-value { font-size:1.9rem; }
    .ad-metric-note { display:none; }
    .ad-metric-go { display:none; }
    .ad-panel-head { padding-left:16px; padding-right:16px; }
    .ad-panel-title { font-size:1rem; }
    .ad-notif-list, .ad-user-list { padding-left:16px; padding-right:16px; }
    .ad-notif.is-unread::before { left:-16px; }
    .ad-notif-icon { width:30px; height:30px; font-size:13px; }
    .ad-notif { grid-template-columns:30px minmax(0,1fr); gap:10px; }
    .ad-empty { padding:34px 18px; }
    .ad-cell-action { max-width:160px; }

    /* Audit trail becomes a stacked list — no horizontal scrolling on phones */
    .ad-table-wrap { overflow:visible; }
    .ad-table { display:block; }
    .ad-table thead { display:none; }
    .ad-table tbody { display:block; }
    .ad-table tbody tr {
        display:grid; grid-template-columns:minmax(0,1fr) auto; gap:6px 10px;
        grid-template-areas:"user user" "action action" "role date";
        padding:12px 16px; border-bottom:1px solid var(--ad-line-soft);
    }
    .ad-table tbody tr:last-child { border-bottom:none; }
    .ad-table tbody td { display:block; padding:0; border:none; }
    .ad-table td:first-child { grid-area:user; }
    .ad-table td:nth-child(2) { grid-area:role; justify-self:start; }
    .ad-table td.ad-cell-action {
        grid-area:action; max-width:none; white-space:normal;
        overflow:visible; text-overflow:clip; font-size:12px; color:#5b6577;
    }
    .ad-table td.ad-cell-date { grid-area:date; align-self:center; text-align:right; font-size:10.5px; }
}

@media (prefers-reduced-motion: reduce) {
    .ad-home .dash-animate { animation:none !important; }
    .ad-live-dot { animation:none; }
    .ad-metric, .ad-notif, .ad-panel-link, .ad-empty-cta { transition:none; }
}
</style>
