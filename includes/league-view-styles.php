<?php
/**
 * ===========================================================================
 *  Public league view — shared design layer
 * ===========================================================================
 *  Loaded by the public league detail pages (which the Team Representative
 *  portal also routes into):
 *
 *      Overview     -> team_information.php
 *      Participants -> participant.php
 *      Schedule     -> schedule.php
 *      Standings    -> standing.php
 *
 *  This is the public twin of app/organizer/includes/league-view-styles.php so
 *  the breadcrumb, hero banner, status pills, tab strip, stat cards, panels and
 *  tables read as one hand-built product across every role.
 *
 *  Page-specific rules stay in each page's own <style> block.
 *  Usage:  require_once __DIR__ . '/league-view-styles.php';
 * ===========================================================================
 */
?>
<style>
    /* ---------- Tokens ----------------------------------------------------- */
    .lp-page {
        --lp-navy:#09173c; --lp-navy-2:#1a3a7c; --lp-blue:#2563eb;
        --lp-ink:#0f172a; --lp-muted:#64748b; --lp-border:#e2e8f0;
        --lp-soft:#f0f5ff; --lp-radius:10px;
    }

    /* ---------- Page shell ------------------------------------------------- */
    /* The public navbar is fixed; keep the league view clear of it. */
    .lms-league-page { padding-top:86px; }
    /* league_header.php closes .lms-league-page right after the tab strip and
       every league page then prints its own .lp-page body container, so a
       padding-bottom here would surface as a large gap *under the tabs*.
       The body container owns the page's bottom breathing room instead. */
    .lms-league-page ~ .lp-page { padding-bottom:40px; }
    @media (max-width:767.98px) {
        .lms-league-page ~ .lp-page { padding-bottom:28px; }
    }
    /* Under ~480px the navbar brand fills the row and Bootstrap wraps the
       hamburger onto a second line, so the fixed header grows to ~114px. */
    @media (max-width:479.98px) {
        .lms-league-page { padding-top:126px; }
    }

    /* ---------- Breadcrumb ------------------------------------------------- */
    /* Same treatment as the organizer league pages: borderless chips with
       chevron separators, so every league view reads as one product. */
    .lp-crumbs { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px; font-size:12.5px; color:var(--lp-muted); }
    .lp-crumbs a { display:inline-flex; align-items:center; gap:6px; padding:4px 10px 4px 8px; border-radius:8px; color:var(--lp-ink); font-weight:600; text-decoration:none !important; transition:background .15s ease, color .15s ease; }
    .lp-crumbs a:hover { background:#e8edf7; color:var(--lp-blue); transform:none; }
    .lp-crumbs a i { font-size:12px; }
    .lp-crumbs .lp-crumb-sep { font-size:13px; color:#64748b; }
    .lp-crumbs .lp-crumb-current { min-width:0; max-width:min(60ch,100%); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--lp-navy); font-weight:600; }

    /* ---------- Hero banner ------------------------------------------------ */
    .lp-hero { position:relative; overflow:hidden; margin-bottom:20px; border-radius:12px; box-shadow:0 10px 30px rgba(9,23,60,.18); }
    .lp-hero-bg { position:absolute; inset:0; background-size:cover; background-position:center; }
    .lp-hero-bg::after { content:''; position:absolute; inset:0; background:rgba(9,23,60,.93); }
    .lp-hero-inner { position:relative; z-index:1; padding:28px 32px 26px; color:#fff; }
    .lp-hero-top { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
    .lp-hero-title { margin:0 0 20px; font-size:1.6rem; font-weight:700; line-height:1.25; letter-spacing:.2px; color:#fff; text-transform:capitalize; }
    .lp-hero-bottom { display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:18px; }
    .lp-hero-meta { display:flex; flex-wrap:wrap; gap:16px 30px; min-width:0; }
    .lp-meta-item { display:flex; align-items:center; gap:10px; font-size:12.5px; color:rgba(255,255,255,.9); }
    .lp-meta-item i { flex:0 0 auto; font-size:16px; color:rgba(255,255,255,.5); }
    .lp-meta-item small { display:block; margin-bottom:1px; font-size:10px; font-weight:600; letter-spacing:.7px; text-transform:uppercase; color:rgba(255,255,255,.5); }
    .lp-meta-item span { display:block; line-height:1.35; }
    .lp-hero-actions { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex:0 0 auto; }
    .lp-countdown { font-size:11.5px; letter-spacing:.2px; color:rgba(255,255,255,.7); text-align:right; }
    .lp-countdown strong { color:#fff; font-weight:700; font-variant-numeric:tabular-nums; }

    /* ---------- Status pills ----------------------------------------------- */
    .lp-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; white-space:nowrap; }
    .lp-pill i { font-size:12px; }
    .lp-pill-ghost { background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.2); color:rgba(255,255,255,.92); }
    .lp-pill-open { background:#d1fae5; color:#065f46; }
    .lp-pill-ongoing { background:#dbeafe; color:#1e40af; }
    .lp-pill-upcoming { background:#fef3c7; color:#92400e; }
    .lp-pill-completed { background:#e5e7eb; color:#374151; }
    .lp-pill-ended { background:#e5e7eb; color:#374151; }

    /* ---------- Hero action buttons ---------------------------------------- */
    /* SB Admin 2 underlines anchors through `a:not(.btn)` with higher
       specificity, so button-style anchors reset it explicitly. */
    .lp-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:9px 18px; border:1.5px solid transparent; border-radius:8px; font-size:12.5px; font-weight:600; letter-spacing:.2px; text-decoration:none !important; cursor:pointer; transition:transform .15s ease, background .15s ease, border-color .15s ease, box-shadow .15s ease; }
    .lp-btn i { font-size:14px; }
    .lp-btn-primary { background:#15803d; border-color:#15803d; color:#fff; box-shadow:0 5px 16px rgba(21,128,61,.32); }
    .lp-btn-primary:not(:disabled):hover { background:#166534; border-color:#166534; color:#fff; transform:translateY(-1px); }
    .lp-btn-light { background:#fff; border-color:#fff; color:var(--lp-navy); }
    .lp-btn-light:hover { background:#eef4ff; border-color:#eef4ff; color:var(--lp-navy); transform:translateY(-1px); }
    .lp-btn-state { background:rgba(255,255,255,.12); border-color:rgba(255,255,255,.22); color:rgba(255,255,255,.8); cursor:not-allowed; }
    .lp-btn-state:hover { transform:none; }

    /* ---------- Tab strip -------------------------------------------------- */
    .lp-tabs-nav { display:flex; flex-wrap:wrap; align-items:center; margin:0 0 20px; padding:0; list-style:none; border-bottom:1px solid var(--lp-border); }
    .lp-tabs-nav .nav-item { flex:0 0 auto; }
    .lp-tabs-nav .nav-link { position:relative; display:inline-flex; align-items:center; gap:7px; margin:0 26px 0 0; padding:11px 0; background:none !important; border:0 !important; border-bottom:2px solid transparent !important; border-radius:0; font-size:13.5px; font-weight:600; letter-spacing:.1px; color:var(--lp-muted) !important; text-decoration:none !important; transition:color .18s ease, border-color .18s ease; }
    .lp-tabs-nav .nav-link::after { content:none !important; display:none !important; }
    .lp-tabs-nav .nav-link:hover { color:var(--lp-navy) !important; }
    .lp-tabs-nav .nav-link.active { color:var(--lp-navy) !important; border-bottom-color:var(--lp-blue) !important; }
    .lp-tabs-nav .nav-link i { font-size:14px; color:#94a3b8; transition:color .18s ease; }
    .lp-tabs-nav .nav-link.active i { color:var(--lp-blue); }

    /* ---------- Panels ----------------------------------------------------- */
    .lp-panel { margin-bottom:16px; padding:20px 22px; border:1px solid var(--lp-border); border-radius:12px; background:#fff; box-shadow:0 4px 18px rgba(9,23,60,.05); }
    .lp-panel-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin:-2px 0 16px; padding-bottom:14px; border-bottom:1px solid #eef2f7; }
    .lp-panel-title { display:flex; align-items:center; gap:10px; margin:0; font-size:.95rem; font-weight:700; color:var(--lp-navy); }
    .lp-panel-icon { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:32px; height:32px; border-radius:9px; background:var(--lp-soft); color:var(--lp-blue); font-size:.9rem; }
    .lp-panel-sub { margin:2px 0 0; font-size:12px; font-weight:400; color:var(--lp-muted); }
    .lp-copy { color:#475569; font-size:.9rem; line-height:1.75; overflow-wrap:anywhere; }
    .lp-copy p:last-child { margin-bottom:0; }

    /* ---------- Stat cards ------------------------------------------------- */
    .lp-stat-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-bottom:22px; }
    /* Pages that only need three summary tiles (fewer, non-duplicated facts). */
    .lp-stat-grid.lp-stat-grid-3 { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .lp-stat { position:relative; overflow:hidden; padding:16px 18px; border:1px solid var(--lp-border); border-radius:12px; background:#fff; box-shadow:0 4px 18px rgba(9,23,60,.06); transition:transform .2s ease, box-shadow .2s ease; }
    .lp-stat:hover { transform:translateY(-3px); box-shadow:0 9px 24px rgba(9,23,60,.11); }
    .lp-stat-bar { position:absolute; top:0; left:0; right:0; height:4px; }
    .lp-stat-top { display:flex; align-items:center; gap:12px; }
    .lp-stat-icon { display:flex; align-items:center; justify-content:center; flex:0 0 auto; width:44px; height:44px; border-radius:10px; font-size:1.25rem; }
    .lp-stat-value { font-size:1.5rem; font-weight:700; line-height:1.1; color:var(--lp-navy); font-variant-numeric:tabular-nums; }
    .lp-stat-value-sub { font-size:1rem; color:#9ca3af; }
    .lp-stat-label { margin-top:3px; font-size:10.5px; font-weight:600; letter-spacing:.7px; text-transform:uppercase; color:var(--lp-muted); }
    .lp-stat-note { margin-top:12px; font-size:11.5px; line-height:1.55; color:var(--lp-muted); }
    .lp-stat-note strong { color:var(--lp-navy); font-weight:600; }
    .lp-stat-note a { color:var(--lp-blue); font-weight:600; text-decoration:none !important; }
    .lp-stat-note a:hover { text-decoration:underline !important; }
    @media (max-width:1199px) { .lp-stat-grid, .lp-stat-grid.lp-stat-grid-3 { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:575px) { .lp-stat-grid, .lp-stat-grid.lp-stat-grid-3 { grid-template-columns:1fr; } }

    /* ---------- Data tables ------------------------------------------------ */
    .lp-table-wrap { overflow-x:auto; border:1px solid var(--lp-border); border-radius:10px; }
    .lp-table { width:100%; margin:0; font-size:13.5px; }
    .lp-table thead th { padding:12px 16px; border:0; background:var(--lp-navy); color:#fff; font-size:11.5px; font-weight:600; letter-spacing:.5px; text-transform:uppercase; white-space:nowrap; }
    .lp-table tbody td { padding:12px 16px; vertical-align:middle; border-bottom:1px solid #eff3f8; }
    .lp-table tbody tr { transition:background .18s ease; }
    .lp-table tbody tr:hover { background:var(--lp-soft); }
    .lp-table tbody tr:last-child td { border-bottom:0; }
    .lp-table .lp-col-num { width:56px; color:#94a3b8; font-variant-numeric:tabular-nums; }

    /* ---------- Progress --------------------------------------------------- */
    .lp-progress { height:8px; margin-top:12px; border-radius:99px; background:rgba(255,255,255,.22); overflow:hidden; }
    .lp-progress > span { display:block; height:100%; border-radius:99px; background:#7dd3fc; }

    /* ---------- Empty state ------------------------------------------------ */
    .lp-empty { padding:56px 24px; text-align:center; border:1px dashed #d7e0ec; border-radius:12px; background:#fbfdff; }
    .lp-empty i { display:block; margin-bottom:12px; font-size:2.6rem; color:#cbd5e1; }
    .lp-empty h5 { margin:0 0 6px; font-size:1rem; font-weight:700; color:var(--lp-navy); }
    .lp-empty p { margin:0; font-size:13px; color:var(--lp-muted); }

    /* ---------- Fact list (League Information) ----------------------------- */
    .lp-facts { display:flex; flex-direction:column; margin:0; }
    .lp-facts-row { display:flex; align-items:baseline; justify-content:space-between; gap:14px; padding:10px 0; border-bottom:1px dashed #edf1f7; }
    .lp-facts-row:first-child { padding-top:0; }
    .lp-facts-row:last-child { padding-bottom:0; border-bottom:0; }
    .lp-facts-row dt { flex:0 0 auto; margin:0; font-size:11px; font-weight:600; letter-spacing:.5px; text-transform:uppercase; color:var(--lp-muted); }
    .lp-facts-row dd { flex:1 1 auto; min-width:0; margin:0; text-align:right; font-size:13px; font-weight:600; color:var(--lp-navy); overflow-wrap:anywhere; }

    /* ---------- Documents -------------------------------------------------- */
    .lp-doc-list { display:grid; gap:8px; }
    .lp-doc { display:flex !important; align-items:center; gap:10px; padding:11px 14px !important; border:1px solid #dbe6f2 !important; border-radius:9px !important; background:#f8fbff; color:var(--lp-navy) !important; font-size:12.5px !important; font-weight:600 !important; text-align:left; text-decoration:none !important; transition:background .18s ease, border-color .18s ease, color .18s ease; }
    .lp-doc:hover { background:var(--lp-navy) !important; border-color:var(--lp-navy) !important; color:#fff !important; }
    .lp-doc i { flex:0 0 auto; font-size:.95rem; }
    .lp-doc span { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

    /* ---------- Responsive ------------------------------------------------- */
    @media (max-width:767.98px) {
        .lp-hero-inner { padding:22px 20px 20px; }
        .lp-hero-title { font-size:1.3rem; }
        .lp-hero-bottom { flex-direction:column; align-items:stretch; gap:16px; }
        .lp-hero-actions { align-items:stretch; }
        .lp-hero-actions .lp-btn { width:100%; }
        .lp-countdown { text-align:center; }
        .lp-hero-meta { gap:14px 22px; }
        .lp-tabs-nav { flex-wrap:nowrap; overflow-x:auto; }
        .lp-tabs-nav .nav-link { margin-right:20px; white-space:nowrap; }
    }
</style>
