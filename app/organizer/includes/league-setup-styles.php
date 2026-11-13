<?php
/**
 * Setup page (setup.php) — page-specific styles only.
 *
 * Shared hero / breadcrumb / pills / buttons / tabs / panels live in
 * includes/league-view-styles.php; the section shell (.activity-section),
 * switches, chips and group lists come from includes/league-standing-styles.php.
 *
 * Everything here is prefixed .lsp-page so it cannot leak into the other
 * per-league pages, and every value comes from the token block below — one
 * type scale (15 / 13 / 12 / 11), one radius scale (10 / 8 / pill) and one
 * border colour, so the wizard looks like one page.
 */
?>
<style>
    /* ---------- Tokens ---------------------------------------------------- */
    .lsp-page {
        --lsp-line:#e5e7eb; --lsp-line-soft:#eef2f7;
        --lsp-ink:#09173c; --lsp-ink-2:#334155; --lsp-muted:#6b7280; --lsp-faint:#94a3b8;
        --lsp-navy:#123f70; --lsp-blue:#2563eb; --lsp-soft:#f8fafc; --lsp-soft-blue:#f4f8ff;
        --lsp-ok:#15803d; --lsp-ok-soft:#f2fdf7; --lsp-ok-line:#bbf7d0;
        --lsp-warn:#b45309; --lsp-warn-soft:#fffbeb; --lsp-warn-line:#fcd9a4;
        --lsp-r:10px; --lsp-r-sm:8px; --lsp-pill:999px;
        --lsp-fs-title:15px; --lsp-fs-body:13px; --lsp-fs-help:12px; --lsp-fs-label:11px;
        --lsp-shadow:0 4px 20px rgba(9,23,60,.08);
    }

    /* ---------- Text ------------------------------------------------------ */
    .lsp-page .lsp-lead { margin:0 0 14px; font-size:var(--lsp-fs-help); line-height:1.6; color:var(--lsp-muted); }
    .lsp-page .lsp-hint { margin:0 0 12px; font-size:var(--lsp-fs-help); line-height:1.6; color:var(--lsp-muted); }
    .lsp-page .lsp-help { margin-top:5px; font-size:var(--lsp-fs-label); line-height:1.45; color:var(--lsp-faint); }
    .lsp-page .lsp-alt { margin:14px 0 0; font-size:var(--lsp-fs-help); color:var(--lsp-muted); }
    .lsp-page .lsp-muted-line { display:flex; align-items:center; gap:7px; margin:0; font-size:var(--lsp-fs-help); color:var(--lsp-muted); }
    .lsp-page .lsp-mt { margin-top:14px; }
    .lsp-page .lsp-lead a, .lsp-page .lsp-hint a, .lsp-page .lsp-alt a,
    .lsp-page .lsp-more a, .lsp-page .lsp-note a, .lsp-page .lsp-foot a { color:var(--lsp-navy); font-weight:600; text-decoration:none; }
    .lsp-page .lsp-lead a:hover, .lsp-page .lsp-hint a:hover, .lsp-page .lsp-alt a:hover,
    .lsp-page .lsp-more a:hover, .lsp-page .lsp-note a:hover, .lsp-page .lsp-foot a:hover { color:var(--lsp-blue); text-decoration:underline; }
    .lsp-page .lsp-linkish { padding:0; border:0; background:none; color:var(--lsp-navy); font:inherit; font-weight:600; text-decoration:underline; cursor:pointer; }
    .lsp-page .lsp-linkish:hover { color:var(--lsp-blue); }
    .lsp-page .lsp-subhead { margin:18px 0 10px; font-size:var(--lsp-fs-label); font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:var(--lsp-faint); }

    /* ---------- Task card (the wizard) ------------------------------------ */
    .lsp-page .lsp-wizard { margin-bottom:20px; }
    .lsp-page .lsp-task { margin:0; box-shadow:var(--lsp-shadow); }
    .lsp-page .activity-title { flex-wrap:wrap; row-gap:4px; }
    .lsp-page .lsp-task .activity-header { padding:18px 24px 14px; align-items:center; }
    .lsp-page .lsp-task .activity-title { font-size:var(--lsp-fs-title); }
    .lsp-page .lsp-task .activity-title i { color:var(--lsp-navy); }
    .lsp-page .lsp-task .lp-section-body { padding:18px 24px 22px; }
    .lsp-page .lsp-foot { display:flex; align-items:center; flex-wrap:wrap; gap:8px; padding:12px 24px; border-top:1px solid var(--lsp-line-soft); background:var(--lsp-soft); font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-foot i { color:var(--lsp-faint); }
    .lsp-page .lsp-foot a { margin-left:auto; }

    /* ---------- Steps ----------------------------------------------------- */
    .lsp-page .lsp-steps { display:flex; align-items:center; margin:0; padding:0 24px 4px; list-style:none; overflow-x:auto; }
    .lsp-page .lsp-step { display:flex; align-items:center; gap:8px; flex:0 0 auto; }
    .lsp-page .lsp-step::after { content:''; flex:1 1 20px; min-width:18px; height:2px; margin:0 10px; border-radius:2px; background:var(--lsp-line-soft); }
    .lsp-page .lsp-step:last-child::after { content:none; }
    .lsp-page .lsp-step-dot { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:24px; height:24px; border:1.5px solid var(--lsp-line); border-radius:50%; background:#fff; color:var(--lsp-faint); font-size:var(--lsp-fs-label); font-weight:700; }
    .lsp-page .lsp-step-dot i { font-size:12px; }
    .lsp-page .lsp-step-label { font-size:var(--lsp-fs-label); font-weight:600; color:var(--lsp-muted); white-space:nowrap; }
    .lsp-page .lsp-step.is-done .lsp-step-dot { border-color:var(--lsp-ok-line); background:var(--lsp-ok-soft); color:var(--lsp-ok); }
    .lsp-page .lsp-step.is-done .lsp-step-label { color:var(--lsp-ink-2); }
    .lsp-page .lsp-step.is-done::after { background:var(--lsp-ok-line); }
    .lsp-page .lsp-step.is-current .lsp-step-dot { border-color:var(--lsp-navy); background:var(--lsp-navy); color:#fff; box-shadow:0 0 0 3px rgba(18,63,112,.12); }
    .lsp-page .lsp-step.is-current .lsp-step-label { color:var(--lsp-ink); }

    /* ---------- Actions + forms ------------------------------------------- */
    .lsp-page .lsp-actions { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin-top:16px; }
    .lsp-page .lsp-actions .btn, .lsp-page .lsp-actions .lp-link-btn,
    .lsp-page .lsp-head-actions .btn, .lsp-page .lsp-advance-actions .btn,
    .lsp-page .lsp-inline-form .btn, .lsp-page .lsp-assign .btn { padding:8px 16px; border-radius:var(--lsp-r-sm); font-size:var(--lsp-fs-help); font-weight:600; box-shadow:none; }
    .lsp-page .lsp-actions .lp-link-btn { border:1.5px solid var(--lsp-line); }
    /* Single fixture-generation block on the Matches step (pattern + preview + generate). */
    .lsp-page .lsp-generate { display:flex; align-items:flex-end; flex-wrap:wrap; gap:10px; margin-top:14px; padding:13px 14px; border:1px solid var(--lsp-line-soft); border-radius:var(--lsp-r-sm); background:var(--lsp-soft); }
    .lsp-page .lsp-generate-field { flex:1 1 240px; min-width:0; }
    .lsp-page .lsp-generate-field .form-select { width:100%; }
    .lsp-page .lsp-generate-actions { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
    .lsp-page .btn-primary { background:var(--lsp-navy); border-color:var(--lsp-navy); }
    .lsp-page .btn-primary:hover, .lsp-page .btn-primary:focus { background:#0d3160; border-color:#0d3160; }
    .lsp-page .btn-outline-primary { color:var(--lsp-navy); border:1.5px solid var(--lsp-line); background:#fff; }
    .lsp-page .btn-outline-primary:hover, .lsp-page .btn-outline-primary:focus { color:var(--lsp-blue); border-color:var(--lsp-blue); background:var(--lsp-soft-blue); }
    .lsp-page .form-label { margin-bottom:5px; font-size:var(--lsp-fs-label); font-weight:600; letter-spacing:.2px; color:var(--lsp-ink-2); }
    .lsp-page .form-control, .lsp-page .form-select { padding:8px 12px; border:1.5px solid var(--lsp-line); border-radius:var(--lsp-r-sm); font-size:var(--lsp-fs-help); color:var(--lsp-ink); }
    .lsp-page .form-control:focus, .lsp-page .form-select:focus { border-color:var(--lsp-blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .lsp-page .form-text { font-size:var(--lsp-fs-label); color:var(--lsp-faint); }
    .lsp-page .lsp-check { margin:0; font-size:var(--lsp-fs-help); color:var(--lsp-ink-2); }
    .lsp-page .lsp-check .form-check-label { cursor:pointer; }

    /* ---------- Collapsed explanation ------------------------------------- */
    .lsp-page .lsp-more { margin-top:16px; border:1px solid var(--lsp-line-soft); border-radius:var(--lsp-r-sm); background:var(--lsp-soft); }
    .lsp-page .lsp-more > summary { display:flex; align-items:center; gap:7px; padding:9px 14px; font-size:var(--lsp-fs-label); font-weight:600; color:var(--lsp-navy); cursor:pointer; list-style:none; }
    .lsp-page .lsp-more > summary::-webkit-details-marker { display:none; }
    .lsp-page .lsp-more > summary::before { content:'▸'; color:var(--lsp-faint); transition:transform .15s ease; }
    .lsp-page .lsp-more[open] > summary::before { content:'▾'; }
    .lsp-page .lsp-more > summary:focus-visible { outline:2px solid rgba(37,99,235,.35); outline-offset:-2px; border-radius:var(--lsp-r-sm); }
    .lsp-page .lsp-more p { margin:0; padding:0 14px 12px; font-size:var(--lsp-fs-help); line-height:1.6; color:var(--lsp-muted); }

    /* ---------- Notes (one calmer treatment for every callout) ------------- */
    .lsp-page .lsp-note, .lsp-page .lsp-next { display:flex; align-items:flex-start; gap:9px; margin:12px 0; padding:10px 14px; border:1px solid var(--lsp-line); border-left:3px solid var(--lsp-blue); border-radius:var(--lsp-r-sm); background:var(--lsp-soft); font-size:var(--lsp-fs-help); line-height:1.55; color:var(--lsp-ink-2); }
    .lsp-page .lsp-note i, .lsp-page .lsp-next i { margin-top:1px; color:var(--lsp-blue); }
    .lsp-page .lsp-note b, .lsp-page .lsp-next b { color:var(--lsp-ink); }

    /* ---------- Structure cards ------------------------------------------- */
    .lsp-page .lsp-structure-picker { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin:0; }
    .lsp-page .lsp-structure-form { display:flex; margin:0; }
    .lsp-page .lsp-structure-option { display:flex; flex-direction:column; align-items:flex-start; gap:6px; width:100%; margin:0; padding:16px 18px; border:1.5px solid var(--lsp-line); border-radius:var(--lsp-r); background:#fff; color:var(--lsp-ink); font:inherit; text-align:left; cursor:pointer; transition:border-color .18s ease, box-shadow .18s ease, background .18s ease; }
    .lsp-page .lsp-structure-option:hover, .lsp-page .lsp-structure-option:focus-visible { border-color:var(--lsp-blue); background:var(--lsp-soft-blue); box-shadow:0 6px 16px rgba(18,63,112,.10); }
    .lsp-page .lsp-structure-option:focus-visible { outline:3px solid rgba(37,99,235,.25); outline-offset:2px; }
    .lsp-page .lsp-structure-option.is-recommended { border-color:var(--lsp-navy); }
    .lsp-page .lsp-structure-option.is-current { border-color:var(--lsp-ok-line); background:var(--lsp-ok-soft); cursor:default; }
    .lsp-page .lsp-structure-option.is-current:hover { box-shadow:none; }
    .lsp-page .lsp-structure-head { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
    .lsp-page .lsp-structure-head b { font-size:var(--lsp-fs-body); font-weight:600; color:var(--lsp-ink); }
    .lsp-page .lsp-structure-badge { padding:2px 9px; border-radius:var(--lsp-pill); background:var(--lsp-navy); color:#fff; font-size:10px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; }
    .lsp-page .lsp-structure-badge.is-current { background:var(--lsp-ok); }
    .lsp-page .lsp-structure-text { font-size:var(--lsp-fs-help); line-height:1.5; color:var(--lsp-muted); }
    .lsp-page .lsp-structure-creates { display:flex; align-items:flex-start; gap:6px; margin-top:2px; font-size:var(--lsp-fs-label); line-height:1.5; color:var(--lsp-ink-2); }
    .lsp-page .lsp-structure-creates i { margin-top:1px; color:var(--lsp-faint); }
    .lsp-page .lsp-structure-cta { display:inline-flex; align-items:center; gap:6px; width:100%; margin-top:auto; padding-top:10px; border-top:1px solid var(--lsp-line-soft); font-size:var(--lsp-fs-label); font-weight:700; color:var(--lsp-navy); }
    .lsp-page .lsp-structure-option.is-current .lsp-structure-cta { border-top-color:var(--lsp-ok-line); color:var(--lsp-ok); }
    .lsp-page .lsp-structure-option:disabled { cursor:not-allowed; opacity:1; box-shadow:none; }
    .lsp-page .lsp-structure-option:disabled:not(.is-current) { background:var(--lsp-soft); border-color:var(--lsp-line); }
    .lsp-page .lsp-structure-option:disabled:not(.is-current) .lsp-structure-head b,
    .lsp-page .lsp-structure-option:disabled:not(.is-current) .lsp-structure-text,
    .lsp-page .lsp-structure-option:disabled:not(.is-current) .lsp-structure-creates,
    .lsp-page .lsp-structure-option:disabled:not(.is-current) .lsp-structure-cta { color:var(--lsp-faint); }
    .lsp-page .lsp-structure-option:disabled:not(.is-current) .lsp-structure-cta { border-top-color:var(--lsp-line-soft); }

    /* ---------- Structure pipeline (read-only overview) -------------------- */
    .lsp-page .lsp-pipeline { display:flex; align-items:stretch; flex-wrap:wrap; gap:10px; margin:0 0 14px; padding:0; list-style:none; }
    .lsp-page .lsp-pipe-step { display:flex; flex-direction:column; gap:5px; flex:1 1 180px; min-width:180px; padding:13px 15px; border:1px solid var(--lsp-line); border-radius:var(--lsp-r); background:var(--lsp-soft); }
    .lsp-page .lsp-pipe-head { display:flex; align-items:center; gap:8px; }
    .lsp-page .lsp-pipe-num { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:22px; height:22px; border-radius:50%; background:var(--lsp-navy); color:#fff; font-size:var(--lsp-fs-label); font-weight:700; }
    .lsp-page .lsp-pipe-head b { font-size:var(--lsp-fs-body); line-height:1.3; color:var(--lsp-ink); text-transform:capitalize; }
    .lsp-page .lsp-pipe-type { align-self:flex-start; padding:2px 9px; border-radius:var(--lsp-pill); background:#e8edf7; color:var(--lsp-navy); font-size:10px; font-weight:600; }
    .lsp-page .lsp-pipe-meta { font-size:var(--lsp-fs-label); line-height:1.45; color:var(--lsp-muted); }
    .lsp-page .lsp-pipe-arrow { display:flex; align-items:center; justify-content:center; flex:0 0 auto; color:#9fb0c9; font-size:1rem; }

    /* ---------- Status chips ---------------------------------------------- */
    .lsp-page .lsp-status { display:flex; flex-wrap:wrap; gap:8px; margin:0; }
    .lsp-page .lsp-status-item { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border:1px solid var(--lsp-line); border-radius:var(--lsp-pill); background:var(--lsp-soft); font-size:var(--lsp-fs-label); color:var(--lsp-ink-2); }
    .lsp-page .lsp-status-item b { font-size:var(--lsp-fs-help); color:var(--lsp-ink); }
    .lsp-page .lsp-status-item i { color:var(--lsp-faint); }
    .lsp-page .lsp-status-item.is-done { border-color:var(--lsp-ok-line); background:var(--lsp-ok-soft); color:var(--lsp-ok); }
    .lsp-page .lsp-status-item.is-done b { color:var(--lsp-ok); }
    .lsp-page .lsp-status-item.is-done i { color:var(--lsp-ok); }
    .lsp-page .lsp-status-item.is-todo { border-color:var(--lsp-warn-line); background:var(--lsp-warn-soft); color:var(--lsp-warn); }
    .lsp-page .lsp-status-item.is-todo b { color:var(--lsp-warn); }
    .lsp-page .lsp-status-item.is-todo i { color:var(--lsp-warn); }

    /* ---------- Section heads --------------------------------------------- */
    .lsp-page .lsp-head-actions { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
    .lsp-page .lsp-count { display:inline-flex; align-items:center; gap:6px; padding:5px 11px; border:1px solid var(--lsp-line); border-radius:var(--lsp-pill); background:#fff; font-size:var(--lsp-fs-label); font-weight:600; color:var(--lsp-ink-2); }
    .lsp-page .lsp-count i { color:var(--lsp-faint); }

    /* ---------- Stage editor ---------------------------------------------- */
    .lsp-page .lsp-stage-list { display:flex; flex-direction:column; gap:12px; margin:0; padding:0; list-style:none; }
    .lsp-page .lsp-stage { border:1px solid var(--lsp-line); border-radius:var(--lsp-r); background:#fff; overflow:hidden; }
    .lsp-page .lsp-stage-head { display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border-bottom:1px solid var(--lsp-line-soft); background:var(--lsp-soft); }
    .lsp-page .lsp-stage-num { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:26px; height:26px; border-radius:50%; background:var(--lsp-navy); color:#fff; font-size:var(--lsp-fs-help); font-weight:700; }
    .lsp-page .lsp-stage-title { flex:1 1 auto; min-width:0; }
    .lsp-page .lsp-stage-title b { display:inline-block; margin-right:8px; font-size:var(--lsp-fs-body); color:var(--lsp-ink); text-transform:capitalize; }
    .lsp-page .lsp-stage-sub { display:block; margin-top:4px; font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-stage-body { padding:14px 16px 16px; }
    .lsp-page .lsp-groups { display:grid; grid-template-columns:repeat(auto-fit,minmax(288px,1fr)); gap:12px; }
    .lsp-page .lsp-group { display:flex; flex-direction:column; gap:10px; padding:13px 14px; border:1px solid var(--lsp-line-soft); border-radius:var(--lsp-r-sm); background:var(--lsp-soft); }
    .lsp-page .lsp-group-head { display:flex; align-items:center; gap:8px; }
    .lsp-page .lsp-group-head b { min-width:0; font-size:var(--lsp-fs-help); color:var(--lsp-ink); }
    .lsp-page .lsp-group-count { padding:2px 9px; border-radius:var(--lsp-pill); background:#e8edf7; color:var(--lsp-ink-2); font-size:10px; font-weight:600; }
    .lsp-page .lsp-group-sources { font-size:var(--lsp-fs-label); line-height:1.45; color:var(--lsp-muted); }
    .lsp-page .lsp-group-chips { display:flex; flex-wrap:wrap; gap:7px; }
    .lsp-page .lsp-assign { display:flex; align-items:center; gap:6px; margin:0; }
    .lsp-page .lsp-assign .form-select { flex:1 1 auto; min-width:0; padding:6px 10px; font-size:var(--lsp-fs-help); border-radius:var(--lsp-r-sm); }
    .lsp-page .lsp-assign .btn { flex:0 0 auto; padding:6px 12px; }

    /* ---------- Collapsible blocks ---------------------------------------- */
    .lsp-page .lsp-details { border:1px solid var(--lsp-line-soft); border-radius:var(--lsp-r-sm); background:#fff; }
    .lsp-page .lsp-details > summary { display:flex; align-items:center; gap:7px; padding:9px 13px; font-size:var(--lsp-fs-label); font-weight:600; color:var(--lsp-navy); cursor:pointer; list-style:none; }
    .lsp-page .lsp-details > summary::-webkit-details-marker { display:none; }
    .lsp-page .lsp-details > summary::before { content:'▸'; font-size:11px; color:var(--lsp-faint); transition:transform .15s ease; }
    .lsp-page .lsp-details[open] > summary::before { transform:rotate(90deg); }
    .lsp-page .lsp-details > summary:hover { background:var(--lsp-soft); }
    .lsp-page .lsp-details > summary:focus-visible { outline:2px solid rgba(37,99,235,.35); outline-offset:-2px; border-radius:var(--lsp-r-sm); }
    .lsp-page .lsp-details-body { padding:0 13px 12px; }
    .lsp-page .lsp-details .table { margin:0; font-size:var(--lsp-fs-help); }
    .lsp-page .lsp-details .table thead th { padding:7px 9px; background:#f1f5f9; color:var(--lsp-ink-2); font-size:10px; letter-spacing:.4px; text-transform:uppercase; white-space:nowrap; }
    .lsp-page .lsp-details .table tbody td { padding:7px 9px; }

    /* ---------- Empty states / inline forms -------------------------------- */
    .lsp-page .lsp-empty { padding:28px 20px; text-align:center; border:1px dashed var(--lsp-line); border-radius:var(--lsp-r); background:var(--lsp-soft); }
    .lsp-page .lsp-empty i { display:block; margin-bottom:8px; font-size:1.6rem; color:#b9c6da; }
    .lsp-page .lsp-empty b { display:block; margin-bottom:4px; font-size:var(--lsp-fs-body); color:var(--lsp-ink); }
    .lsp-page .lsp-empty small { display:block; max-width:52ch; margin:0 auto 14px; font-size:var(--lsp-fs-help); line-height:1.55; color:var(--lsp-muted); }
    .lsp-page .lsp-inline-form { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin:0; }
    .lsp-page .lsp-inline-form .form-control { flex:1 1 200px; min-width:0; }
    .lsp-page .lsp-add-group { margin-top:14px; padding-top:14px; border-top:1px solid var(--lsp-line-soft); }

    /* ---------- Advance qualifiers ---------------------------------------- */
    .lsp-page .lsp-advance .form-select, .lsp-page .lsp-advance .form-control { padding:8px 12px; }
    .lsp-page .lsp-advance-actions { display:flex; align-items:center; flex-wrap:wrap; gap:12px; margin-top:16px; }

    /* ---------- Modal presets --------------------------------------------- */
    .lsp-page .lsp-presets { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 16px; }
    .lsp-page .lsp-preset { padding:6px 13px; border:1px solid var(--lsp-line); border-radius:var(--lsp-pill); background:#fff; color:var(--lsp-navy); font-size:var(--lsp-fs-label); font-weight:600; transition:border-color .15s ease, background .15s ease, color .15s ease; }
    .lsp-page .lsp-preset:hover, .lsp-page .lsp-preset:focus-visible { border-color:var(--lsp-navy); background:var(--lsp-soft-blue); }
    .lsp-page .lsp-chip-row { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .lsp-page .lsp-chip-btn { padding:4px 11px; border:1px solid var(--lsp-line); border-radius:var(--lsp-pill); background:#fff; color:var(--lsp-ink-2); font-size:var(--lsp-fs-label); font-weight:600; }
    .lsp-page .lsp-chip-btn:hover { border-color:var(--lsp-navy); color:var(--lsp-navy); background:var(--lsp-soft-blue); }

    /* ---------- Compact manual setup -------------------------------------- */
    .lsp-page .lsp-manual-tools { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin:0 0 12px; }
    .lsp-page .lsp-manual-tools .btn { font-size:var(--lsp-fs-help); font-weight:600; }
    .lsp-page .lsp-quick-stage { margin:0 0 16px; padding:14px 16px; border:1px solid var(--lsp-line); border-radius:var(--lsp-r); background:#fff; }
    .lsp-page .lsp-quick-stage-head { display:flex; align-items:baseline; justify-content:space-between; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
    .lsp-page .lsp-quick-stage-head b { font-size:var(--lsp-fs-help); color:var(--lsp-ink); }
    .lsp-page .lsp-quick-stage-head small { font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-quick-stage-form .form-label { margin-bottom:4px; font-size:var(--lsp-fs-label); }
    .lsp-page .lsp-quick-stage-form .form-control, .lsp-page .lsp-quick-stage-form .form-select { padding:7px 10px; font-size:var(--lsp-fs-help); }
    .lsp-page .lsp-quick-stage-form .form-text { font-size:var(--lsp-fs-label); }
    .lsp-page .lsp-stage-manage { margin:0; }
    .lsp-page .lsp-stage-manage > summary { background:var(--lsp-soft-blue); border-radius:var(--lsp-r-sm); }
    .lsp-page .lsp-stage-manage > .lsp-details-body { padding-top:10px; }

    /* ---------- Setup progress header ------------------------------------- */
    .lsp-page .lsp-progress-card { position:relative; margin:0 0 20px; padding:22px 24px 20px; border:1px solid var(--lsp-line); border-radius:var(--lsp-r); background:linear-gradient(135deg,#fff 0%,var(--lsp-soft-blue) 100%); box-shadow:var(--lsp-shadow); overflow:hidden; }
    .lsp-page .lsp-progress-card::before { content:''; position:absolute; inset:0 auto 0 0; width:4px; background:linear-gradient(180deg,var(--lsp-navy),var(--lsp-blue)); }
    .lsp-page .lsp-progress-top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .lsp-page .lsp-progress-copy { min-width:0; }
    .lsp-page .lsp-progress-eyebrow { display:inline-flex; align-items:center; gap:6px; font-size:10px; font-weight:700; letter-spacing:.7px; text-transform:uppercase; color:var(--lsp-muted); }
    .lsp-page .lsp-progress-eyebrow i { color:var(--lsp-navy); }
    .lsp-page .lsp-progress-title { margin:4px 0 4px; font-size:18px; font-weight:700; line-height:1.25; color:var(--lsp-ink); }
    .lsp-page .lsp-progress-lead { margin:0; max-width:64ch; font-size:var(--lsp-fs-help); line-height:1.55; color:var(--lsp-ink-2); }
    .lsp-page .lsp-progress-lead strong { color:var(--lsp-navy); }
    .lsp-page .lsp-progress-lead span { color:var(--lsp-muted); }
    .lsp-page .lsp-progress-meter { display:flex; align-items:baseline; gap:6px; padding:8px 15px; border:1px solid var(--lsp-line); border-radius:var(--lsp-pill); background:#fff; box-shadow:0 2px 8px rgba(9,23,60,.05); }
    .lsp-page .lsp-progress-meter b { font-size:21px; font-weight:700; line-height:1; color:var(--lsp-navy); }
    .lsp-page .lsp-progress-meter small { font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-progress-track { position:relative; height:10px; margin:16px 0 18px; border-radius:var(--lsp-pill); background:#e8eef8; overflow:hidden; }
    .lsp-page .lsp-progress-fill { display:block; height:100%; border-radius:var(--lsp-pill); background:linear-gradient(90deg,var(--lsp-navy),var(--lsp-blue)); transform-origin:left center; animation:lspProgressIn .9s cubic-bezier(.22,.61,.36,1) both; }
    @keyframes lspProgressIn { from { transform:scaleX(0); } to { transform:scaleX(1); } }
    @media (prefers-reduced-motion:reduce) { .lsp-page .lsp-progress-fill { animation:none; } }

    .lsp-page .lsp-milestones { display:flex; align-items:flex-start; gap:0; margin:0; padding:4px 2px 6px; list-style:none; overflow-x:auto; scrollbar-width:thin; }
    .lsp-page .lsp-milestone { position:relative; flex:1 0 150px; min-width:150px; z-index:0; }
    .lsp-page .lsp-milestone:not(:last-child)::after { content:''; position:absolute; top:15px; left:50%; width:100%; height:2px; background:var(--lsp-line-soft); pointer-events:none; }
    .lsp-page .lsp-milestone.is-done:not(:last-child)::after { background:var(--lsp-ok-line); }
    .lsp-page .lsp-milestone a { position:relative; z-index:1; display:flex; flex-direction:column; align-items:center; gap:7px; padding:0 8px; border:0; background:none; text-align:center; text-decoration:none; }
    .lsp-page .lsp-milestone a:focus-visible { outline:none; }
    .lsp-page .lsp-milestone a:focus-visible .lsp-milestone-dot { box-shadow:0 0 0 4px rgba(37,99,235,.25); }
    .lsp-page .lsp-milestone-dot { position:relative; z-index:1; display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border:2px solid var(--lsp-line); border-radius:50%; background:#fff; color:var(--lsp-faint); transition:border-color .18s ease, background .18s ease, color .18s ease, box-shadow .18s ease; }
    .lsp-page .lsp-milestone-dot i { font-size:12px; }
    .lsp-page .lsp-milestone-copy { min-width:0; width:100%; }
    .lsp-page .lsp-milestone-copy b { display:block; font-size:var(--lsp-fs-help); font-weight:600; line-height:1.3; color:var(--lsp-ink-2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .lsp-page .lsp-milestone-copy small { display:block; max-width:160px; margin:2px auto 0; font-size:10.5px; line-height:1.35; color:var(--lsp-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .lsp-page .lsp-milestone.is-done .lsp-milestone-dot { border-color:var(--lsp-ok-line); background:var(--lsp-ok-soft); color:var(--lsp-ok); }
    .lsp-page .lsp-milestone.is-done .lsp-milestone-copy b { color:var(--lsp-ok); }
    .lsp-page .lsp-milestone.is-current .lsp-milestone-dot { border-color:var(--lsp-navy); background:var(--lsp-navy); color:#fff; }
    .lsp-page .lsp-milestone.is-current .lsp-milestone-copy b { color:var(--lsp-navy); }
    .lsp-page .lsp-milestone.is-active .lsp-milestone-dot { box-shadow:0 0 0 4px rgba(37,99,235,.16); }
    .lsp-page .lsp-milestone.is-active .lsp-milestone-copy b { font-weight:700; color:var(--lsp-ink); }
    .lsp-page .lsp-milestone:hover .lsp-milestone-dot { border-color:var(--lsp-blue); }
    .lsp-page .lsp-milestone.is-current:hover .lsp-milestone-dot { border-color:var(--lsp-navy); }

    .lsp-page .lsp-progress-actions { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin-top:16px; }
    .lsp-page .lsp-progress-actions .btn, .lsp-page .lsp-progress-actions .lp-link-btn { padding:8px 16px; border-radius:var(--lsp-r-sm); font-size:var(--lsp-fs-help); font-weight:600; box-shadow:none; }
    .lsp-page .lsp-wizard-counter { display:inline-flex; align-items:center; margin-left:auto; padding:6px 12px; border:1px solid var(--lsp-line); border-radius:var(--lsp-pill); background:#fff; font-size:var(--lsp-fs-label); font-weight:600; letter-spacing:.2px; color:var(--lsp-ink-2); white-space:nowrap; }
    .lsp-page .lsp-progress-hint { display:flex; align-items:flex-start; gap:7px; margin:12px 0 0; font-size:var(--lsp-fs-label); line-height:1.5; color:var(--lsp-muted); }
    .lsp-page .lsp-progress-hint i { margin-top:1px; color:#c98a13; }


    /* ---------- Guided step panels (one at a time) ------------------------ */
    .lsp-page .lsp-steps-wrap { display:flex; flex-direction:column; gap:12px; margin:0 0 24px; scroll-margin-top:80px; }
    .lsp-page .lsp-step-card { display:none; border:1px solid var(--lsp-line); border-radius:var(--lsp-r); background:#fff; box-shadow:0 2px 10px rgba(9,23,60,.04); overflow:hidden; transition:border-color .2s ease, box-shadow .2s ease; }
    .lsp-page .lsp-step-card.is-active { display:block; border-color:var(--lsp-navy); box-shadow:var(--lsp-shadow); animation:lspStepIn .25s ease both; }
    @keyframes lspStepIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
    @media (prefers-reduced-motion:reduce) { .lsp-page .lsp-step-card.is-active { animation:none; } }
    .lsp-page .lsp-step-card.is-current { border-color:var(--lsp-navy); }
    .lsp-page .lsp-step-summary { display:flex; align-items:center; gap:13px; padding:15px 20px; background:var(--lsp-soft); border-bottom:1px solid var(--lsp-line-soft); }
    .lsp-page .lsp-step-index { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:30px; height:30px; border:1.5px solid var(--lsp-line); border-radius:50%; background:#fff; color:var(--lsp-ink-2); font-size:var(--lsp-fs-help); font-weight:700; }
    .lsp-page .lsp-step-heading { flex:1 1 auto; min-width:0; display:flex; flex-direction:column; gap:2px; }
    .lsp-page .lsp-step-title { display:flex; align-items:center; gap:7px; font-size:var(--lsp-fs-body); font-weight:600; color:var(--lsp-ink); }
    .lsp-page .lsp-step-title i { color:var(--lsp-navy); }
    .lsp-page .lsp-step-detail { font-size:var(--lsp-fs-label); line-height:1.4; color:var(--lsp-muted); }
    .lsp-page .lsp-step-status { display:inline-flex; align-items:center; gap:5px; flex:0 0 auto; padding:4px 10px; border:1px solid var(--lsp-line); border-radius:var(--lsp-pill); background:#fff; font-size:10.5px; font-weight:600; letter-spacing:.2px; color:var(--lsp-muted); }
    .lsp-page .lsp-step-status.is-done { border-color:var(--lsp-ok-line); background:var(--lsp-ok-soft); color:var(--lsp-ok); }
    .lsp-page .lsp-step-status.is-current { border-color:var(--lsp-navy); background:var(--lsp-navy); color:#fff; }
    .lsp-page .lsp-step-card.is-done .lsp-step-index { border-color:var(--lsp-ok-line); background:var(--lsp-ok-soft); color:var(--lsp-ok); }
    .lsp-page .lsp-step-card.is-current .lsp-step-index { border-color:var(--lsp-navy); background:var(--lsp-navy); color:#fff; }
    .lsp-page .lsp-step-body { padding:2px 20px 20px; }
    .lsp-page .lsp-step-body > .lsp-lead:first-child { margin-top:14px; }
    .lsp-page .lsp-step-nav { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin-top:18px; padding-top:14px; border-top:1px solid var(--lsp-line-soft); }
    .lsp-page .lsp-step-nav .lsp-step-next { margin-left:auto; }
    .lsp-page .lsp-step-nav .lp-link-btn { padding:7px 12px; font-size:var(--lsp-fs-help); }
    .lsp-page .lsp-step-nav .btn { padding:8px 16px; border-radius:var(--lsp-r-sm); font-size:var(--lsp-fs-help); font-weight:600; }
    .lsp-page .lsp-step-body .btn { border-radius:var(--lsp-r-sm); font-size:var(--lsp-fs-help); font-weight:600; }
    .lsp-page .lsp-step-card, .lsp-page .lsp-progress-card { scroll-margin-top:80px; }

    /* ---------- Nested disclosures ---------------------------------------- */
    .lsp-page .lsp-disclosure { margin-top:16px; border:1px solid var(--lsp-line); border-radius:var(--lsp-r-sm); background:var(--lsp-soft); }
    .lsp-page .lsp-disclosure > summary { display:flex; align-items:center; gap:7px; padding:11px 14px; font-size:var(--lsp-fs-help); font-weight:600; color:var(--lsp-navy); cursor:pointer; list-style:none; }
    .lsp-page .lsp-disclosure > summary::-webkit-details-marker { display:none; }
    .lsp-page .lsp-disclosure > summary::before { content:'▸'; color:var(--lsp-faint); transition:transform .15s ease; }
    .lsp-page .lsp-disclosure[open] > summary::before { transform:rotate(90deg); }
    .lsp-page .lsp-disclosure > summary:hover { background:var(--lsp-soft-blue); }
    .lsp-page .lsp-disclosure > summary:focus-visible { outline:2px solid rgba(37,99,235,.35); outline-offset:-2px; border-radius:var(--lsp-r-sm); }
    .lsp-page .lsp-disclosure-body { padding:0 14px 14px; }
    .lsp-page .lsp-subdetails { margin:0; border-top:1px solid var(--lsp-line-soft); background:#fff; }
    .lsp-page .lsp-subdetails > summary { display:flex; align-items:center; gap:7px; padding:9px 15px; font-size:var(--lsp-fs-label); font-weight:600; color:var(--lsp-navy); cursor:pointer; list-style:none; }
    .lsp-page .lsp-subdetails > summary::-webkit-details-marker { display:none; }
    .lsp-page .lsp-subdetails > summary::before { content:'▸'; color:var(--lsp-faint); transition:transform .15s ease; }
    .lsp-page .lsp-subdetails[open] > summary::before { transform:rotate(90deg); }
    .lsp-page .lsp-subdetails > summary:hover { background:var(--lsp-soft); }
    .lsp-page .lsp-subdetails .lsp-stage-form { margin:0; padding:4px 15px 16px; border-top:1px solid var(--lsp-line-soft); }

    /* ---------- Structure summary + chips --------------------------------- */
    .lsp-page .lsp-structure-current { display:flex; align-items:center; gap:12px; margin-bottom:14px; padding:13px 16px; border:1px solid var(--lsp-ok-line); border-radius:var(--lsp-r-sm); background:var(--lsp-ok-soft); }
    .lsp-page .lsp-structure-current.is-custom { border-color:var(--lsp-warn-line); background:var(--lsp-warn-soft); }
    .lsp-page .lsp-structure-current-icon { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:36px; height:36px; border-radius:50%; background:#fff; color:var(--lsp-ok); font-size:15px; }
    .lsp-page .lsp-structure-current.is-custom .lsp-structure-current-icon { color:var(--lsp-warn); }
    .lsp-page .lsp-structure-current-copy { flex:1 1 auto; min-width:0; }
    .lsp-page .lsp-structure-current-copy b { display:block; font-size:var(--lsp-fs-body); font-weight:600; color:var(--lsp-ink); }
    .lsp-page .lsp-structure-current-copy small { display:block; margin-top:2px; font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-chip { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border:1px solid var(--lsp-line); border-radius:var(--lsp-pill); background:#fff; font-size:10.5px; font-weight:600; color:var(--lsp-ink-2); white-space:nowrap; }
    .lsp-page .lsp-chip.is-ok { border-color:var(--lsp-ok-line); background:var(--lsp-ok-soft); color:var(--lsp-ok); }
    .lsp-page .lsp-chip.is-warn { border-color:var(--lsp-warn-line); background:var(--lsp-warn-soft); color:var(--lsp-warn); }
    .lsp-page .lsp-note.is-warn { border-left-color:var(--lsp-warn); background:var(--lsp-warn-soft); }
    .lsp-page .lsp-note.is-warn i { color:var(--lsp-warn); }
    .lsp-page .lsp-note.is-ok { border-left-color:var(--lsp-ok); background:var(--lsp-ok-soft); }
    .lsp-page .lsp-note.is-ok i { color:var(--lsp-ok); }

    /* ---------- Stage rows ------------------------------------------------ */
    .lsp-page .lsp-stage-rows { display:flex; flex-direction:column; gap:12px; margin:0 0 16px; padding:0; list-style:none; }
    .lsp-page .lsp-stage-row { border:1px solid var(--lsp-line); border-radius:var(--lsp-r); background:#fff; overflow:hidden; }
    .lsp-page .lsp-stage-row-head { display:flex; align-items:flex-start; gap:11px; padding:13px 15px; border-bottom:1px solid var(--lsp-line-soft); background:var(--lsp-soft); }
    .lsp-page .lsp-stage-num { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:28px; height:28px; border-radius:50%; background:var(--lsp-navy); color:#fff; font-size:var(--lsp-fs-help); font-weight:700; }
    .lsp-page .lsp-stage-row-title { flex:1 1 auto; min-width:0; display:flex; align-items:center; flex-wrap:wrap; gap:7px; }
    .lsp-page .lsp-stage-row-title b { font-size:var(--lsp-fs-body); font-weight:600; color:var(--lsp-ink); }
    .lsp-page .lsp-stage-row-title small { flex:1 1 100%; font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-stage-row-tools { display:inline-flex; align-items:center; gap:5px; flex:0 0 auto; }
    .lsp-page .lsp-tool-form { display:inline-flex; margin:0; }
    .lsp-page .lsp-icon-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; padding:0; border:1px solid var(--lsp-line); border-radius:var(--lsp-r-sm); background:#fff; color:var(--lsp-ink-2); font-size:12px; transition:border-color .15s ease, background .15s ease, color .15s ease; }
    .lsp-page .lsp-icon-btn:hover { border-color:var(--lsp-blue); color:var(--lsp-blue); background:var(--lsp-soft-blue); }
    .lsp-page .lsp-icon-btn.is-danger:hover { border-color:#fecaca; background:#fef2f2; color:#b91c1c; }
    .lsp-page .lsp-stage-row-body { padding:12px 15px 14px; }
    .lsp-page .lsp-group-chips { display:flex; flex-wrap:wrap; align-items:center; gap:7px; }
    .lsp-page .lsp-group-chip { display:inline-flex; align-items:center; gap:7px; padding:5px 6px 5px 11px; border:1px solid var(--lsp-line); border-radius:var(--lsp-pill); background:#fff; font-size:var(--lsp-fs-label); }
    .lsp-page .lsp-group-chip b { font-weight:600; color:var(--lsp-ink); }
    .lsp-page .lsp-group-chip small { color:var(--lsp-muted); }
    .lsp-page .lsp-chip-remove { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; padding:0; border:0; border-radius:50%; background:#f1f5f9; color:#64748b; font-size:12px; line-height:1; transition:background .15s ease, color .15s ease; }
    .lsp-page .lsp-chip-remove:hover { background:#fee2e2; color:#b91c1c; }
    .lsp-page .lsp-group-add { flex:1 1 240px; }
    .lsp-page .lsp-group-add .form-control { flex:1 1 130px; }

    /* ---------- Team placement -------------------------------------------- */
    .lsp-page .lsp-team-stage { margin-top:12px; border:1px solid var(--lsp-line); border-radius:var(--lsp-r); background:#fff; overflow:hidden; }
    .lsp-page .lsp-team-stage > summary { display:flex; align-items:center; gap:12px; padding:12px 15px; background:var(--lsp-soft); cursor:pointer; list-style:none; }
    .lsp-page .lsp-team-stage > summary::-webkit-details-marker { display:none; }
    .lsp-page .lsp-team-stage > summary:hover { background:var(--lsp-soft-blue); }
    .lsp-page .lsp-team-stage > summary > i { margin-left:auto; color:var(--lsp-faint); transition:transform .2s ease; }
    .lsp-page .lsp-team-stage[open] > summary > i { transform:rotate(180deg); }
    .lsp-page .lsp-team-stage-title { display:flex; align-items:center; gap:8px; flex:1 1 auto; min-width:0; }
    .lsp-page .lsp-team-stage-title b { font-size:var(--lsp-fs-body); font-weight:600; color:var(--lsp-ink); }
    .lsp-page .lsp-team-stage-meta { flex:0 0 auto; font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-team-stage-body { padding:14px 15px 16px; border-top:1px solid var(--lsp-line-soft); }
    .lsp-page .lsp-team-meter { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .lsp-page .lsp-team-meter-copy b { display:block; font-size:var(--lsp-fs-help); color:var(--lsp-ink); }
    .lsp-page .lsp-team-meter-copy small { display:block; margin-top:2px; font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-team-meter-value { font-size:18px; font-weight:700; color:var(--lsp-navy); }
    .lsp-page .lsp-team-progress { height:6px; margin:9px 0 12px; background:#e2e8f0; }
    .lsp-page .lsp-team-progress > span { background:linear-gradient(90deg,var(--lsp-navy),var(--lsp-blue)); }
    .lsp-page .lsp-team-autofill { margin:0 0 14px; }
    .lsp-page .lsp-team-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:10px; }
    .lsp-page .lsp-team-group { display:flex; flex-direction:column; gap:8px; padding:12px 13px; border:1px solid var(--lsp-line-soft); border-radius:var(--lsp-r-sm); background:var(--lsp-soft); }
    .lsp-page .lsp-team-group-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .lsp-page .lsp-team-group-head b { font-size:var(--lsp-fs-help); color:var(--lsp-ink); }
    .lsp-page .lsp-team-group-count { display:inline-flex; align-items:center; justify-content:center; min-width:22px; height:20px; padding:0 7px; border-radius:var(--lsp-pill); background:#e8edf7; color:var(--lsp-ink-2); font-size:10.5px; font-weight:600; }
    .lsp-page .lsp-team-chips { display:flex; flex-wrap:wrap; gap:6px; min-height:22px; }
    .lsp-page .lsp-team-chip { display:inline-flex; align-items:center; gap:5px; padding:4px 6px 4px 10px; border:1px solid var(--lsp-line); border-radius:var(--lsp-pill); background:#fff; font-size:var(--lsp-fs-label); color:var(--lsp-ink-2); }
    .lsp-page .lsp-team-chip.is-waiting { padding-right:10px; border-style:dashed; color:var(--lsp-muted); }
    .lsp-page .lsp-team-empty { font-size:var(--lsp-fs-label); font-style:italic; color:var(--lsp-faint); }
    .lsp-page .lsp-team-add { display:flex; gap:6px; margin:0; }
    .lsp-page .lsp-team-add .form-select { flex:1 1 auto; min-width:0; }
    .lsp-page .lsp-team-add .btn { flex:0 0 auto; padding:4px 9px; }
    .lsp-page .lsp-unplaced { margin-top:14px; padding-top:12px; border-top:1px solid var(--lsp-line-soft); }
    .lsp-page .lsp-unplaced > b { display:flex; align-items:center; gap:6px; margin-bottom:8px; font-size:var(--lsp-fs-label); color:var(--lsp-muted); }

    /* ---------- Match generation + results -------------------------------- */
    .lsp-page .lsp-match-target { display:flex; align-items:center; gap:11px; margin-bottom:12px; padding:12px 14px; border:1px solid var(--lsp-line); border-radius:var(--lsp-r-sm); background:var(--lsp-soft-blue); }
    .lsp-page .lsp-match-target-icon { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:34px; height:34px; border-radius:50%; background:#fff; color:var(--lsp-navy); }
    .lsp-page .lsp-match-target small { display:block; font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-match-target b { display:block; font-size:var(--lsp-fs-body); color:var(--lsp-ink); }
    .lsp-page .lsp-match-list { display:flex; flex-direction:column; gap:8px; }
    .lsp-page .lsp-match-row { display:grid; grid-template-columns:minmax(150px,1.1fr) minmax(120px,2fr) auto; gap:12px; align-items:center; padding:10px 13px; border:1px solid var(--lsp-line-soft); border-radius:var(--lsp-r-sm); background:var(--lsp-soft); }
    .lsp-page .lsp-match-row-copy b { display:block; font-size:var(--lsp-fs-help); color:var(--lsp-ink); }
    .lsp-page .lsp-match-row-copy small { font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-match-row .lp-progress { height:6px; margin:0; background:#e2e8f0; }
    .lsp-page .lsp-match-row .lp-progress > span { background:linear-gradient(90deg,var(--lsp-navy),var(--lsp-blue)); }
    .lsp-page .lsp-finish-meter { display:flex; align-items:center; gap:16px; margin-bottom:16px; padding:15px 17px; border:1px solid var(--lsp-line); border-radius:var(--lsp-r); background:var(--lsp-soft); }
    .lsp-page .lsp-finish-value { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:64px; height:64px; border:2px solid var(--lsp-navy); border-radius:50%; background:#fff; color:var(--lsp-navy); font-size:16px; font-weight:700; }
    .lsp-page .lsp-finish-copy { flex:1 1 auto; min-width:0; }
    .lsp-page .lsp-finish-copy b { display:block; font-size:var(--lsp-fs-body); color:var(--lsp-ink); }
    .lsp-page .lsp-finish-copy small { display:block; margin-top:2px; font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-finish-progress { height:6px; margin-top:9px; background:#e2e8f0; }
    .lsp-page .lsp-finish-progress > span { background:linear-gradient(90deg,var(--lsp-navy),var(--lsp-blue)); }
    .lsp-page .lsp-finish-block { margin-top:14px; }
    .lsp-page .lsp-finish-block.is-complete { display:flex; align-items:flex-start; gap:13px; padding:14px 16px; border:1px solid var(--lsp-ok-line); border-radius:var(--lsp-r-sm); background:var(--lsp-ok-soft); }
    .lsp-page .lsp-finish-trophy { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:42px; height:42px; border-radius:50%; background:#fff; color:#b8860b; font-size:18px; }
    .lsp-page .lsp-finish-block.is-complete .lsp-subhead { margin-top:0; }
    .lsp-page .lsp-finish-links { margin-top:18px; }

    @media (max-width:991px) {
        .lsp-page .lsp-structure-picker { grid-template-columns:1fr; }
    }
    @media (max-width:575px) {
        .lsp-page .lsp-task .activity-header { padding:16px 18px 12px; }
        .lsp-page .lsp-task .lp-section-body { padding:16px 18px 18px; }
        .lsp-page .lsp-steps { padding:0 18px 4px; }
        .lsp-page .lsp-foot { padding:12px 18px; }
        .lsp-page .lsp-foot a { margin-left:0; }
        .lsp-page .lsp-stage-head { flex-wrap:wrap; }
        .lsp-page .lsp-groups { grid-template-columns:1fr; }
        .lsp-page .lsp-pipe-step { flex:1 1 100%; }
        .lsp-page .lsp-pipe-arrow { display:none; }
    }
    /* ---------- Compact 3-step header ------------------------------------- */
    .lsp-page .lsp-wizard-head { display:flex; flex-wrap:wrap; align-items:center; gap:14px 18px; margin:0 0 18px; padding:16px 20px; border:1px solid var(--lsp-line); border-radius:var(--lsp-r); background:#fff; box-shadow:var(--lsp-shadow); }
    .lsp-page .lsp-wizard-copy { flex:1 1 320px; min-width:0; }
    .lsp-page .lsp-wizard-eyebrow { display:block; font-size:10px; font-weight:700; letter-spacing:.7px; text-transform:uppercase; color:var(--lsp-muted); }
    .lsp-page .lsp-wizard-title { margin:2px 0 2px; font-size:17px; font-weight:700; color:var(--lsp-ink); }
    .lsp-page .lsp-wizard-hint { margin:0; font-size:var(--lsp-fs-help); line-height:1.5; color:var(--lsp-muted); }
    .lsp-page .lsp-wizard-steps { display:flex; align-items:center; gap:18px; margin:0; padding:0; list-style:none; }
    .lsp-page .lsp-wizard-step a { display:flex; align-items:center; gap:7px; color:var(--lsp-muted); font-size:var(--lsp-fs-help); font-weight:600; text-decoration:none; white-space:nowrap; }
    .lsp-page .lsp-wizard-dot { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border:1.5px solid var(--lsp-line); border-radius:50%; background:#fff; font-size:11px; font-weight:700; color:var(--lsp-muted); }
    .lsp-page .lsp-wizard-step.is-done .lsp-wizard-dot { border-color:var(--lsp-ok-line); background:var(--lsp-ok-soft); color:var(--lsp-ok); }
    .lsp-page .lsp-wizard-step.is-active a { color:var(--lsp-ink); }
    .lsp-page .lsp-wizard-step.is-active .lsp-wizard-dot { border-color:var(--lsp-navy); background:var(--lsp-navy); color:#fff; }
    .lsp-page .lsp-wizard-step a:hover .lsp-wizard-dot { border-color:var(--lsp-blue); }
    .lsp-page .lsp-wizard-track { flex:1 1 100%; height:4px; margin-top:2px; border-radius:var(--lsp-pill); background:var(--lsp-line-soft); overflow:hidden; }
    .lsp-page .lsp-wizard-track span { display:block; height:100%; border-radius:var(--lsp-pill); background:linear-gradient(90deg,var(--lsp-navy),var(--lsp-blue)); transition:width .3s ease; }

    /* ---------- Format cards / compact summaries --------------------------- */
    .lsp-page .lsp-format-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin:12px 0 0; }
    .lsp-page .lsp-format-form-setup { display:block; margin:0; }
    .lsp-page .lsp-format-card { display:flex; align-items:center; gap:10px; width:100%; padding:12px 13px; border:1.5px solid var(--lsp-line); border-radius:var(--lsp-r); background:#fff; color:var(--lsp-ink); text-align:left; font:inherit; cursor:pointer; transition:border-color .15s ease, background .15s ease, box-shadow .15s ease; }
    .lsp-page .lsp-format-card:hover, .lsp-page .lsp-format-card:focus-within { border-color:var(--lsp-blue); background:var(--lsp-soft-blue); box-shadow:0 4px 12px rgba(18,63,112,.08); }
    .lsp-page .lsp-format-card:focus-within { outline:3px solid rgba(37,99,235,.25); outline-offset:1px; }
    .lsp-page .lsp-format-card.is-recommended { border-color:var(--lsp-navy); }
    .lsp-page .lsp-format-card.is-current { border-color:var(--lsp-ok-line); background:var(--lsp-ok-soft); }
    .lsp-page .lsp-format-card.is-selected { border-color:var(--lsp-navy); box-shadow:0 0 0 3px rgba(18,63,112,.12); }
    .lsp-page .lsp-format-radio { flex:0 0 auto; width:16px; height:16px; margin:0; accent-color:var(--lsp-navy); cursor:pointer; }
    .lsp-page .lsp-format-icon { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:32px; height:32px; border-radius:50%; background:var(--lsp-soft); color:var(--lsp-navy); }
    .lsp-page .lsp-format-copy { min-width:0; flex:1 1 auto; }
    .lsp-page .lsp-format-copy b { display:block; font-size:var(--lsp-fs-help); font-weight:600; color:var(--lsp-ink); }
    .lsp-page .lsp-format-copy small { display:block; margin-top:2px; font-size:var(--lsp-fs-label); line-height:1.35; color:var(--lsp-muted); }
    .lsp-page .lsp-format-mark { color:var(--lsp-ok); }

    .lsp-page .lsp-simple-summary { display:flex; align-items:center; gap:11px; margin:14px 0 8px; padding:11px 13px; border:1px solid var(--lsp-line-soft); border-radius:var(--lsp-r-sm); background:var(--lsp-soft); }
    .lsp-page .lsp-simple-summary-icon { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:32px; height:32px; border-radius:50%; background:#fff; color:var(--lsp-navy); }
    .lsp-page .lsp-simple-summary-copy { flex:1 1 auto; min-width:0; }
    .lsp-page .lsp-simple-summary-copy b { display:block; font-size:var(--lsp-fs-help); color:var(--lsp-ink); }
    .lsp-page .lsp-simple-summary-copy small { display:block; margin-top:2px; font-size:var(--lsp-fs-label); color:var(--lsp-muted); }
    .lsp-page .lsp-simple-summary-value { font-size:16px; font-weight:700; color:var(--lsp-navy); }
    .lsp-page .lsp-simple-progress { height:6px; margin:0 0 14px; background:#e2e8f0; }
    .lsp-page .lsp-simple-progress > span { background:linear-gradient(90deg,var(--lsp-navy),var(--lsp-blue)); }
    .lsp-page .lsp-simple-groups { display:flex; flex-wrap:wrap; gap:7px; margin-top:12px; }
    .lsp-page .lsp-simple-next { margin-top:16px; padding-top:14px; border-top:1px solid var(--lsp-line-soft); }
    .lsp-page .lsp-simple-next > b { display:block; font-size:var(--lsp-fs-body); color:var(--lsp-ink); }
    .lsp-page .lsp-simple-done { display:flex; align-items:center; gap:8px; margin:14px 0 10px; padding:12px 14px; border:1px solid var(--lsp-ok-line); border-radius:var(--lsp-r-sm); background:var(--lsp-ok-soft); font-size:var(--lsp-fs-help); font-weight:600; color:var(--lsp-ok); }
    .lsp-page .lsp-simple-links { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; padding-top:12px; border-top:1px solid var(--lsp-line-soft); }
    .lsp-page .lsp-playoff-form { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin:12px 0 6px; }
    .lsp-page .lsp-playoff-form label, .lsp-page .lsp-playoff-form span { font-size:var(--lsp-fs-help); color:var(--lsp-ink-2); margin:0; }
    .lsp-page .lsp-playoff-form .form-control { width:76px; }
    .lsp-page .lsp-playoff-form .btn { margin-left:auto; }

    /* ---------- Danger zone: discard setup -------------------------------- */
    .lsp-page .lsp-danger-zone { margin:18px 0 8px; }
    .lsp-page .lsp-danger { border:1px solid #fecaca; border-radius:var(--lsp-r); background:#fff; }
    .lsp-page .lsp-danger > summary { display:flex; align-items:center; gap:8px; padding:13px 18px; color:#b42335; font-size:var(--lsp-fs-help); font-weight:600; cursor:pointer; list-style:none; }
    .lsp-page .lsp-danger > summary::-webkit-details-marker { display:none; }
    .lsp-page .lsp-danger > summary::before { content:'▸'; color:#d97a86; transition:transform .15s ease; }
    .lsp-page .lsp-danger[open] > summary::before { transform:rotate(90deg); }
    .lsp-page .lsp-danger > summary:hover { background:#fff7f7; }
    .lsp-page .lsp-danger > summary:focus-visible { outline:2px solid rgba(180,35,53,.35); outline-offset:-2px; border-radius:var(--lsp-r); }
    .lsp-page .lsp-danger-body { padding:0 18px 18px; }
    .lsp-page .lsp-danger-body p { margin:0 0 12px; font-size:var(--lsp-fs-help); line-height:1.55; color:var(--lsp-muted); }
    .lsp-page .lsp-danger-form { display:flex; align-items:flex-end; flex-wrap:wrap; gap:10px; }
    .lsp-page .lsp-danger-form .form-control { max-width:180px; }
    .lsp-page .lsp-danger-form .btn-danger { background:#b42335; border-color:#b42335; border-radius:var(--lsp-r-sm); font-size:var(--lsp-fs-help); font-weight:600; }
    .lsp-page .lsp-danger-form .btn-danger:hover, .lsp-page .lsp-danger-form .btn-danger:focus { background:#951b2b; border-color:#951b2b; }

    @media (max-width:1100px) {
        .lsp-page .lsp-wizard-counter { margin-left:0; }
    }
    @media (max-width:767px) {
        .lsp-page .lsp-match-row { grid-template-columns:1fr; }
        .lsp-page .lsp-match-row .lsp-chip { justify-self:start; }
        .lsp-page .lsp-stage-row-head { flex-wrap:wrap; }
        .lsp-page .lsp-stage-row-tools { margin-left:39px; }
        .lsp-page .lsp-format-grid { grid-template-columns:1fr; }
        .lsp-page .lsp-wizard-steps { width:100%; justify-content:space-between; gap:8px; }
        .lsp-page .lsp-wizard-step a { font-size:var(--lsp-fs-label); }
    }
    @media (max-width:575px) {
        .lsp-page .lsp-progress-card { padding:18px; }
        .lsp-page .lsp-wizard-head { padding:14px; }
        .lsp-page .lsp-wizard-title { font-size:15px; }
        .lsp-page .lsp-playoff-form .btn { margin-left:0; }
        .lsp-page .lsp-milestone { flex-basis:128px; min-width:128px; }
        .lsp-page .lsp-wizard-counter { margin-left:0; }
        .lsp-page .lsp-step-summary { gap:10px; padding:13px 15px; }
        .lsp-page .lsp-step-status { display:none; }
        .lsp-page .lsp-step-body { padding:2px 15px 16px; }
        .lsp-page .lsp-step-nav .lsp-step-next { margin-left:0; }
        .lsp-page .lsp-stage-row-tools { margin-left:0; }
        .lsp-page .lsp-team-stage-meta { display:none; }
        .lsp-page .lsp-finish-meter { flex-direction:column; align-items:flex-start; }
    }

    /* ---------- Editable format settings + advance form ----------------- */
    .lsp-page .lsp-format-settings {
        display:none; margin:0 0 14px; padding:14px 16px;
        border:1px solid #e6edf5; border-radius:14px; background:#f8fafc;
    }
    .lsp-page .lsp-format-settings.is-active { display:block; }
    .lsp-page .lsp-format-settings-head { display:flex; flex-wrap:wrap; align-items:baseline; gap:6px 12px; margin:0 0 10px; }
    .lsp-page .lsp-format-settings-head b { font-size:13px; color:#0f172a; }
    .lsp-page .lsp-format-settings-head small { font-size:11.5px; color:#64748b; }
    .lsp-page .lsp-recommendation {
        display:flex; flex-wrap:wrap; align-items:center; gap:10px 14px;
        margin:12px 0 4px; padding:10px 12px;
        border:1px solid #d7e3f5; border-radius:12px; background:#f4f8ff;
    }
    .lsp-page .lsp-recommendation.is-warning {
        border-color:#f5d9a8; background:#fffaf0; color:#7a4b02;
        font-size:12px; line-height:1.5;
    }
    .lsp-page .lsp-recommendation-copy { flex:1 1 260px; display:flex; flex-direction:column; gap:3px; }
    .lsp-page .lsp-recommendation-copy b { font-size:12.5px; color:#0f172a; }
    .lsp-page .lsp-recommendation-copy small { font-size:11.5px; color:#475569; line-height:1.5; }
    .lsp-page .lsp-recommendation .btn { flex:0 0 auto; }
    .lsp-page .lsp-setting-row { display:flex; flex-wrap:wrap; align-items:center; gap:8px 12px; margin:0 0 10px; }
    .lsp-page .lsp-setting-row > label { flex:1 1 300px; margin:0; font-size:12px; font-weight:600; color:#0f172a; }
    .lsp-page .lsp-setting-row > input,
    .lsp-page .lsp-setting-row > .form-control { flex:0 0 110px; width:110px; }
    .lsp-page .lsp-setting-row > span { font-size:12px; color:#475569; }
    .lsp-page .lsp-format-settings-actions { display:flex; flex-wrap:wrap; align-items:center; gap:8px 14px; margin:2px 0 14px; }
    .lsp-page .lsp-format-settings-actions small { font-size:11.5px; color:#64748b; }
    .lsp-page .lsp-advance-form { display:flex; flex-direction:column; gap:10px; margin:12px 0 0; padding:14px 16px; border:1px solid #e6edf5; border-radius:14px; background:#f8fafc; }
    .lsp-page .lsp-check { display:inline-flex; align-items:flex-start; gap:8px; margin:0; font-size:12px; color:#475569; }
    .lsp-page .lsp-check input { margin-top:2px; }
</style>
