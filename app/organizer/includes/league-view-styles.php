<?php
/**
 * Shared design layer for the organizer's per-league pages
 * (Overview = league_info.php, Schedule = schedule.php, ...).
 *
 * Tokens mirror includes/dashboard-styles.php so every league view shares one
 * hero banner, breadcrumb, tab strip, status pill, action button, panel and
 * modal treatment. Page-specific rules stay in each page's own <style> block.
 *
 * Usage:  <?php include 'includes/league-view-styles.php'; ?>
 */
?>
<style>
    /* ---------- Tokens ------------------------------------------------------ */
    .lp-page {
        --lp-navy:#09173c; --lp-navy-2:#1a3a7c; --lp-blue:#2563eb;
        --lp-ink:#09173c; --lp-muted:#6b7280; --lp-border:#e5e7eb;
        --lp-soft:#f0f5ff; --lp-radius:10px;
    }

    /* ---------- Breadcrumb (sits above the hero) ---------------------------- */
    .lp-crumbs { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px; font-size:12.5px; color:var(--lp-muted); }
    .lp-crumbs a { display:inline-flex; align-items:center; gap:6px; padding:4px 10px 4px 8px; border-radius:8px; color:var(--lp-ink); font-weight:600; text-decoration:none !important; transition:background .15s ease, color .15s ease; }
    .lp-crumbs a:hover { background:#e8edf7; color:var(--lp-blue); }
    .lp-crumbs a i { font-size:12px; }
    .lp-crumbs .lp-crumb-sep { font-size:13px; color:#64748b; }
    .lp-crumbs .lp-crumb-current { min-width:0; max-width:min(60ch,100%); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--lp-navy); font-weight:600; }

    /* ---------- Hero banner ------------------------------------------------- */
    .lp-hero { position:relative; border-radius:12px; overflow:hidden; margin-bottom:22px; box-shadow:0 8px 26px rgba(9,23,60,.18); }
    .lp-hero-bg { position:absolute; inset:0; background-size:cover; background-position:center; }
    /* Flat navy tint (no gradient) so the organizer hero matches the public
       league view and keeps the white hero copy legible over any artwork. */
    .lp-hero-bg::after { content:''; position:absolute; inset:0; background:rgba(9,23,60,.93); }
    .lp-hero-inner { position:relative; z-index:1; padding:24px 30px 22px; color:#fff; }
    .lp-hero-top { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
    .lp-hero-title { margin:0 0 4px; font-size:1.6rem; font-weight:700; line-height:1.25; letter-spacing:.2px; color:#fff; text-transform:capitalize; }
    .lp-hero-sub { margin-bottom:18px; font-size:13px; color:rgba(255,255,255,.68); }
    .lp-hero-bottom { display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:18px; }
    .lp-hero-meta { display:flex; flex-wrap:wrap; gap:14px 28px; }
    .lp-meta-item { display:flex; align-items:center; gap:9px; font-size:12.5px; color:rgba(255,255,255,.88); }
    .lp-meta-item i { font-size:15px; color:rgba(255,255,255,.5); }
    .lp-meta-item small { display:block; font-size:10px; font-weight:500; letter-spacing:.6px; text-transform:uppercase; color:rgba(255,255,255,.5); }
    .lp-hero-actions { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }

    /* ---------- Status pills ------------------------------------------------ */
    .lp-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; }
    .lp-pill-ghost { background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.2); color:rgba(255,255,255,.92); }
    .lp-pill-open { background:#d1fae5; color:#065f46; }
    .lp-pill-ongoing { background:#dbeafe; color:#1e40af; }
    .lp-pill-upcoming { background:#fef3c7; color:#92400e; }
    .lp-pill-completed { background:#e5e7eb; color:#374151; }
    .lp-pill-draft { background:#ede9fe; color:#5b21b6; }
    /* ---------- Hero action buttons ----------------------------------------- */
    /* SB Admin 2 underlines anchors via `a:not(.btn)` and `a:hover` at
       specificity (0,1,1), which outranks a plain `.lp-btn`. Chip- and
       button-style anchors therefore reset it with !important, mirroring how
       dashboard-styles.php handles `.stat-card-link`. */
    .lp-btn { display:inline-flex; align-items:center; gap:7px; padding:8px 16px; border:1.5px solid transparent; border-radius:8px; font-size:12.5px; font-weight:600; letter-spacing:.2px; text-decoration:none !important; cursor:pointer; transition:transform .15s ease, background .15s ease, border-color .15s ease, color .15s ease; }
    .lp-btn:hover { transform:translateY(-1px); }
    .lp-btn-soft { background:rgba(255,255,255,.14); border-color:rgba(255,255,255,.28); color:#fff; }
    .lp-btn-soft:hover { background:rgba(255,255,255,.26); color:#fff; }
    /* Pending-review action: a deeper amber so it stands out from the
       neutral (white) and destructive (red) hero actions without being neon. */
    .lp-btn-pending { background:#d1880d; border-color:#d98a05; color:#422006; box-shadow:0 3px 10px rgba(160,110,10,.24); }
    .lp-btn-pending:hover { background:#fbbf24; border-color:#d98a05; color:#422006; box-shadow:0 7px 18px rgba(160,110,10,.32); }
    .lp-btn-pending i { color:#422006; }
    .lp-btn-light { background:#fff; border-color:#fff; color:var(--lp-navy); }
    .lp-btn-light:hover { background:#eef4ff; border-color:#eef4ff; color:var(--lp-navy); }
    .lp-btn-danger { background:rgba(220,53,69,.94); border-color:rgba(220,53,69,.94); color:#fff; }
    .lp-btn-danger:hover { background:#c9303d; border-color:#c9303d; color:#fff; }
    .lp-btn-state { background:rgba(255,255,255,.1); border-color:rgba(255,255,255,.2); color:rgba(255,255,255,.78); cursor:default; }
    .lp-btn-state:hover { transform:none; }

    /* ---------- League tabs ------------------------------------------------- */
    .lp-tabs .league-section-tabs { padding:0; }
    .lp-tabs .nav-underline { justify-content:flex-start; border-bottom:1px solid var(--lp-border); margin-bottom:24px !important; }
    .lp-tabs .nav-underline .nav-item { flex:0 0 auto; }
    .lp-tabs .nav-underline .nav-link { margin:0 26px 0 0; padding:10px 2px; font-size:13.5px; font-weight:500; color:var(--lp-muted) !important; }
    .lp-tabs .nav-underline .nav-link:hover { color:var(--lp-navy) !important; }
    .lp-tabs .nav-underline .nav-link.active { color:var(--lp-navy) !important; font-weight:600; border-bottom-color:var(--lp-blue) !important; }
    /* ---------- Panels & fields (cards, modals, forms) ---------------------- */
    .lp-panel { margin-bottom:14px; padding:16px 18px; border:1px solid #eef2f7; border-radius:10px; background:#fff; }
    .lp-panel h6 { display:flex; align-items:center; gap:8px; margin:0 0 14px; font-size:12.5px; font-weight:600; color:var(--lp-ink); }
    .lp-panel h6 i { color:var(--lp-blue); }
    .lp-panel-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 22px; }
    .lp-field-wide { grid-column:1 / -1; }
    .lp-field small { display:block; margin-bottom:3px; font-size:10.5px; font-weight:600; letter-spacing:.6px; text-transform:uppercase; color:#9ca3af; }
    .lp-field span { font-size:13px; color:#334155; word-break:break-word; }
    .lp-prewrap { white-space:pre-wrap; }
    .lp-profile-head { display:flex; align-items:center; gap:18px; margin-bottom:18px; padding:18px; border:1px solid #eef2f7; border-radius:12px; background:#fff; flex-wrap:wrap; }
    @media (max-width:575px) { .lp-panel-grid { grid-template-columns:1fr; } .lp-hero-inner { padding:20px; } }

    /* ---------- Form controls inside league views --------------------------- */
    .lp-form .form-label, .lp-modal .form-label { margin-bottom:5px; font-size:11.5px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; color:var(--lp-muted); }
    .lp-form .form-control, .lp-form .form-select,
    .lp-modal .form-control, .lp-modal .form-select { padding:9px 13px; border:1.5px solid var(--lp-border); border-radius:9px; background:#fff; font-size:13px; }
    .lp-form .form-control:focus, .lp-form .form-select:focus,
    .lp-modal .form-control:focus, .lp-modal .form-select:focus { border-color:var(--lp-blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .lp-form .form-text, .lp-modal .form-text { font-size:11.5px; color:#9ca3af; }

    /* ---------- Modals ------------------------------------------------------ */
    .lp-modal .modal-content { border:none; border-radius:12px; overflow:hidden; box-shadow:0 24px 60px rgba(9,23,60,.28); }
    .lp-modal .modal-header { align-items:flex-start; padding:18px 24px; border-bottom:none; background:linear-gradient(135deg,#09173c 0%,#1a3a7c 100%); }
    .lp-modal .modal-header.bg-danger { background:linear-gradient(135deg,#7f1d1d 0%,#dc3545 100%) !important; }
    .lp-modal .modal-title { margin:0; font-size:1rem; font-weight:600; color:#fff; }
    .lp-modal .modal-subtitle { display:block; margin-top:3px; font-size:11.5px; color:rgba(255,255,255,.66); }
    .lp-modal .modal-header .btn-close { filter:invert(1); opacity:.85; margin-top:2px; }
    .lp-modal .modal-body { padding:22px 24px; background:#f8fafc; }
    .lp-modal .modal-footer { padding:14px 24px; border-top:1px solid var(--lp-border); background:#fff; }
    .lp-modal .modal-footer .btn { padding:8px 18px; border-radius:8px; font-size:13px; font-weight:600; }
    .lp-modal .alert { border-radius:10px; font-size:12.5px; }
    .lp-modal .list-group-item { margin-bottom:10px; padding:14px 16px; border:1px solid #eef2f7; border-radius:10px; }
    .lp-modal .table thead th { border:none; background:#09173c; color:#fff; font-size:11px; letter-spacing:.5px; text-transform:uppercase; }
    .lp-modal .table tbody td { font-size:12.5px; }
    /* ---------- Chips & row actions ----------------------------------------- */
    .lp-row-status { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; }
    .lp-row-status.is-approved, .lp-row-status.is-completed { background:#d1fae5; color:#065f46; }
    .lp-row-status.is-pending { background:#fef3c7; color:#92400e; }
    .lp-row-status.is-scheduled { background:#dbeafe; color:#1e40af; }
    .lp-row-status.is-cancelled { background:#fee2e2; color:#991b1b; }
    .lp-row-status.is-conflict { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; cursor:pointer; }
    .lp-row-status.is-neutral { background:#e5e7eb; color:#374151; }
    .lp-row-status::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; opacity:.7; }
    .lp-row-status.is-conflict::before { content:none; }
    .league-icon-btn { width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; padding:0; border:1px solid transparent; border-radius:10px; font-size:.95rem; line-height:1; text-decoration:none !important; box-shadow:0 2px 5px rgba(9,23,60,.10); transition:transform .15s ease, box-shadow .15s ease, filter .15s ease; }
    .league-icon-btn:hover { transform:translateY(-1px); box-shadow:0 5px 10px rgba(9,23,60,.15); filter:brightness(.97); }
    .league-icon-btn:focus-visible { outline:3px solid rgba(37,99,235,.25); outline-offset:2px; }
    .league-icon-btn:disabled { opacity:.45; box-shadow:none; cursor:not-allowed; }
    .league-icon-btn:disabled:hover { transform:none; box-shadow:none; }
    .league-action-primary { color:#fff; background:#123f70; border-color:#123f70; }
    .league-action-primary:hover { color:#fff; }
    .league-action-warn { color:#7a4b02; background:#fdf0d3; border-color:#f0cf88; }
    .league-action-warn:hover { color:#fff; background:#b45309; border-color:#b45309; }
    .league-action-ok { color:#fff; background:#15803d; border-color:#15803d; }
    .league-action-ok:hover { color:#fff; background:#166534; border-color:#166534; }
    .league-action-muted { color:#4b5563; background:#f3f4f6; border-color:#e5e7eb; }
    .league-action-muted:hover { color:#b42318; background:#fff; border-color:#dfa1a1; }
    .lp-link-btn { display:inline-flex; align-items:center; gap:7px; padding:7px 16px; border:1.5px solid var(--lp-border); border-radius:8px; background:#fff; color:var(--lp-navy); font-size:12px; font-weight:600; text-decoration:none !important; transition:border-color .2s ease, background .2s ease, color .2s ease; }
    .lp-link-btn:hover { border-color:var(--lp-blue); background:var(--lp-soft); color:var(--lp-blue); }
    /* Matchup separator used by the schedule board. */
    .lp-vs { display:inline-block; margin:0 8px; font-size:10.5px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#9ca3af; }

    /* ---------- Progress + empty state -------------------------------------- */
    .lp-progress { height:6px; margin-top:10px; border-radius:99px; background:#eef2f7; overflow:hidden; }
    .lp-progress > span { display:block; height:100%; border-radius:99px; background:linear-gradient(90deg,var(--lp-navy),var(--lp-blue)); }
    .lp-empty { padding:56px 24px; text-align:center; }
    .lp-empty i { display:block; margin-bottom:12px; font-size:2.6rem; color:#d1d5db; }
    .lp-empty p { margin:0; font-size:13.5px; color:#9ca3af; }
                            /* Give the shared league hero a clear vertical rhythm. */
                            .lp-page .lp-hero-inner { padding:30px 32px; }
                            .lp-page .lp-hero-top { margin-bottom:18px; }
                            .lp-page .lp-hero-title { margin-bottom:22px; }
                            .lp-page .lp-hero-meta { gap:18px 32px; }
                            @media (max-width:575px) {
                                .lp-page .lp-hero-inner { padding:24px 20px; }
                                .lp-page .lp-hero-top { margin-bottom:16px; }
                                .lp-page .lp-hero-title { margin-bottom:20px; }
                                .lp-page .lp-hero-meta { gap:16px 24px; }
                            }
</style>
