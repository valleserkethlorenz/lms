<?php
require_once __DIR__ . '/config/leagueSession.php';
require_once __DIR__ . '/config/dbconnect.php';
require_once __DIR__ . '/includes/eligibilityValidator.php';
require_once __DIR__ . '/includes/rosterSize.php';

$time = strtotime("-10 year", time());
$date = date("Y-m-d", $time);
require_once __DIR__ . '/includes/header.php';
if ((int) ($_SESSION['lms_role'] ?? 0) === 2) {
    require_once __DIR__ . '/includes/bar-tr-league.php'; // Team Representative portal chrome
} else {
    require_once __DIR__ . '/includes/bar.php'; // public navbar
}
require_once __DIR__ . '/includes/league_header.php';

// Initialize variables from league_header.php
$id = $id ?? 0;
$league_row = $league_row ?? [];
$start_date = $start_date ?? '';
$end_date = $end_date ?? '';
$guidelineDocuments = [];
$organizer = [];
if ($id > 0) {
    $guidelineStmt = $conn->prepare("SELECT id,stored_name,original_name FROM tbl_league_guideline_document WHERE league_id=? AND is_archived=0 ORDER BY id");
    $guidelineStmt->bind_param('i', $id);
    $guidelineStmt->execute();
    $guidelineResult = $guidelineStmt->get_result();
    while ($guideline = $guidelineResult->fetch_assoc()) $guidelineDocuments[] = $guideline;
    $guidelineStmt->close();
}

// Fetch organizer details
$created_by = $league_row['created_by'] ?? 0;
if ($created_by > 0) {
    $orgStmt = $conn->prepare("SELECT id, fname, lname, mname, suffix, phone, email, gender, date_of_birth, address, years_experience, credentials, profile_image, created FROM tbl_user WHERE id=?");
    $orgStmt->bind_param('i', $created_by);
    $orgStmt->execute();
    $orgResult = $orgStmt->get_result();
    $organizer = $orgResult->fetch_assoc() ?: [];
    $orgStmt->close();
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    /* ==========================================================
       League Overview — page-specific body design only.
       The breadcrumb, hero banner and tab strip live in
       includes/league_header.php and are intentionally untouched.
       Shared tokens (--lp-*) come from includes/league-view-styles.php;
       everything below restyles just the Overview page body.
       ========================================================== */

    /* ---------- Section eyebrow ------------------------------------------- */
    .ov-eyebrow { display:flex; align-items:center; gap:10px; margin:0 0 10px; padding-left:2px; font-size:10.5px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:#8593a8; }
    .ov-eyebrow::after { content:''; flex:1 1 auto; height:1px; background:linear-gradient(90deg,#e4ebf5,transparent); }

    /* ---------- Snapshot strip -------------------------------------------- */
    .ov-snapshot { margin-bottom:20px; overflow:hidden; border:1px solid var(--lp-border); border-radius:14px; background:#fff; box-shadow:0 10px 28px rgba(9,23,60,.07); }
    .ov-snapshot-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); }
    .ov-metric { position:relative; display:flex; flex-direction:column; gap:7px; padding:20px 24px 18px; }
    .ov-metric + .ov-metric { border-left:1px solid #eef2f7; }
    .ov-metric::before { content:''; position:absolute; inset:0 0 auto; height:3px; background:var(--ov-accent,#1a3a7c); }
    .ov-metric-label { display:inline-flex; align-items:center; gap:7px; font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#6b7a92; }
    .ov-metric-label i { font-size:12.5px; color:var(--ov-accent,#1a3a7c); }
    .ov-metric-value { display:flex; align-items:baseline; gap:7px; font-size:1.9rem; font-weight:700; line-height:1.05; color:var(--lp-navy); font-variant-numeric:tabular-nums; }
    .ov-metric-value small { font-size:.95rem; font-weight:600; color:#9aa8bd; }
    .ov-metric-note { font-size:11.5px; line-height:1.55; color:#6b7a92; }
    .ov-metric-note b { color:var(--lp-navy); font-weight:600; }
    .ov-state { display:inline-flex; align-items:center; gap:9px; font-size:1.45rem; font-weight:700; line-height:1.2; color:var(--lp-navy); }
    .ov-state.is-open { color:#15803d; }
    .ov-state.is-upcoming { color:#b45309; }
    .ov-state.is-closed { color:#64748b; }
    .ov-dot { flex:0 0 auto; width:9px; height:9px; border-radius:50%; background:#94a3b8; box-shadow:0 0 0 3px rgba(148,163,184,.16); }
    .ov-state.is-open .ov-dot { background:#16a34a; box-shadow:0 0 0 3px rgba(22,163,74,.16); }
    .ov-state.is-upcoming .ov-dot { background:#d97706; box-shadow:0 0 0 3px rgba(217,119,6,.16); }
    .ov-snapshot-foot { display:flex; align-items:center; gap:16px; padding:13px 24px; border-top:1px solid #eef2f7; background:linear-gradient(180deg,#fbfdff,#f7fafd); }
    .ov-meter { flex:1 1 auto; height:8px; border-radius:99px; background:#e9eff7; overflow:hidden; }
    .ov-meter span { display:block; height:100%; border-radius:99px; background:linear-gradient(90deg,#09173c,#2563eb); }
    .ov-meter-caption { flex:0 0 auto; font-size:11.5px; color:#6b7a92; }
    .ov-meter-caption b { color:var(--lp-navy); font-weight:700; }

    /* ---------- Column layout --------------------------------------------- */
    .ov-grid { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr); gap:18px; align-items:start; }
    .ov-stack { display:flex; flex-direction:column; gap:18px; min-width:0; }
    .ov-stack > .lp-panel { margin-bottom:0; }

    /* ---------- Panels ---------------------------------------------------- */
    .ov-panel { padding:20px 22px; border-radius:14px; box-shadow:0 8px 24px rgba(9,23,60,.055); }
    .ov-panel .lp-panel-head { margin:0 0 16px; padding-bottom:13px; }
    .ov-panel .lp-panel-icon { width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,#eef4ff,#e1eaf9); color:#1d4ed8; box-shadow:inset 0 0 0 1px rgba(37,99,235,.08); }
    .ov-panel-heading { min-width:0; }
    .ov-panel-heading .lp-panel-sub { margin-top:3px; }
    .ov-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:20px; background:#eef3fa; color:#1a3a7c; font-size:11px; font-weight:600; white-space:nowrap; }
    .ov-chip i { font-size:12px; }

    /* ---------- Prose ----------------------------------------------------- */
    .ov-prose { font-size:13.5px; line-height:1.8; color:#4b5c72; overflow-wrap:anywhere; }
    .ov-prose.is-lead { padding-left:15px; border-left:3px solid #cbdaf1; font-size:14px; line-height:1.78; color:#374a63; }
    .ov-prose.is-muted { margin:0; padding:16px 18px; border:1px dashed #dbe4f0; border-radius:10px; background:#fbfdff; font-size:13px; color:#8494a8; }
    .ov-subblock { margin-top:18px; padding-top:16px; border-top:1px solid #eef2f7; }
    .ov-subblock-title { display:flex; align-items:center; gap:8px; margin:0 0 9px; font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#6b7a92; }
    .ov-subblock-title i { font-size:13px; color:#94a3b8; }

    /* ---------- Announcement feed (rendered by loadPublicAnnouncements) ---- */
    .ov-feed { position:relative; display:flex; flex-direction:column; gap:14px; }
    .ov-feed.has-items::before { content:''; position:absolute; top:8px; bottom:8px; left:17px; width:2px; background:linear-gradient(180deg,#dce7f6,#eef2f7); }
    .ov-news { position:relative; display:flex; align-items:flex-start; gap:14px; }
    .ov-news-icon { position:relative; z-index:1; display:flex; align-items:center; justify-content:center; flex:0 0 auto; width:36px; height:36px; border:1px solid #dbe7f8; border-radius:50%; background:#eef4ff; color:#2563eb; font-size:14px; }
    .ov-news-card { flex:1 1 auto; min-width:0; padding:12px 16px 13px; border:1px solid #e8eef7; border-radius:12px; background:#fbfdff; transition:border-color .18s ease, background .18s ease, box-shadow .18s ease; }
    .ov-news-card:hover { border-color:#cfe0f7; background:#fff; box-shadow:0 10px 22px rgba(9,23,60,.06); }
    .ov-news-title { margin:0 0 5px; font-size:13.5px; font-weight:700; line-height:1.4; color:var(--lp-navy); }
    .ov-news-text { margin:0 0 9px; font-size:13px; line-height:1.7; color:#4b5c72; overflow-wrap:anywhere; }
    .ov-news-meta { display:inline-flex; align-items:center; gap:6px; font-size:11px; color:#94a3b8; }

    /* ---------- Empty state ----------------------------------------------- */
    .ov-empty { display:flex; flex-direction:column; align-items:center; gap:3px; padding:32px 20px; border:1px dashed #dbe4f0; border-radius:12px; background:#fbfdff; text-align:center; }
    .ov-empty i { margin-bottom:4px; font-size:1.5rem; color:#c3cfdf; }
    .ov-empty strong { font-size:13px; font-weight:600; color:var(--lp-navy); }
    .ov-empty span { font-size:12px; color:#8494a8; }

    /* ---------- Organizer card -------------------------------------------- */
    .ov-org { display:flex; align-items:center; gap:14px; }
    .ov-org-avatar { display:flex; align-items:center; justify-content:center; flex:0 0 auto; width:54px; height:54px; overflow:hidden; border-radius:16px; background:linear-gradient(135deg,#0b1f4d,#24509b); color:#fff; font-size:.95rem; font-weight:700; letter-spacing:.5px; box-shadow:0 8px 18px rgba(9,23,60,.2); }
    .ov-org-avatar img { width:100%; height:100%; object-fit:cover; }
    .ov-org-name { display:block; font-size:14px; font-weight:700; line-height:1.35; color:var(--lp-navy); text-transform:capitalize; }
    .ov-org-role { display:block; margin-top:2px; font-size:11.5px; color:#6b7a92; }
    .ov-org-btn { display:flex; align-items:center; justify-content:space-between; gap:10px; width:100%; margin-top:16px; padding:9px 14px; border:1.5px solid #dbe6f2; border-radius:10px; background:#f8fbff; color:var(--lp-navy); font-size:12.5px; font-weight:600; cursor:pointer; transition:background .18s ease, border-color .18s ease, color .18s ease; }
    .ov-org-btn span { display:inline-flex; align-items:center; gap:8px; }
    .ov-org-btn:hover { border-color:var(--lp-navy); background:var(--lp-navy); color:#fff; }

    /* ---------- Entry requirements grid ----------------------------------- */
    .ov-rules { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin:0; }
    .ov-rule { display:flex; flex-direction:column; gap:5px; min-width:0; padding:13px 15px; border:1px solid #e8eef7; border-radius:11px; background:#fbfdff; }
    .ov-rule dt { display:inline-flex; align-items:center; gap:8px; margin:0; font-size:10.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#7b8aa1; }
    .ov-rule dt i { font-size:12.5px; color:#2563eb; }
    .ov-rule dd { margin:0; font-size:13.5px; font-weight:700; line-height:1.4; color:var(--lp-navy); overflow-wrap:anywhere; }

    /* ---------- Documents ------------------------------------------------- */
    .ov-doc { display:flex !important; align-items:center; gap:11px; padding:10px 12px; border:1px solid #e8eef7; border-radius:10px; background:#fbfdff; color:var(--lp-navy) !important; text-decoration:none !important; transition:border-color .18s ease, background .18s ease, box-shadow .18s ease; }
    .ov-doc + .ov-doc { margin-top:8px; }
    .ov-doc:hover { border-color:#cfe0f7; background:#fff; box-shadow:0 10px 22px rgba(9,23,60,.07); color:var(--lp-navy) !important; }
    .ov-doc-icon { display:flex; align-items:center; justify-content:center; flex:0 0 auto; width:32px; height:32px; border-radius:9px; background:#fdeef0; color:#be123c; font-size:14px; }
    .ov-doc-name { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:600; }
    .ov-doc-arrow { flex:0 0 auto; font-size:13px; color:#a4b2c6; transition:color .18s ease, transform .18s ease; }
    .ov-doc:hover .ov-doc-arrow { color:var(--lp-blue); transform:translateY(1px); }

    /* ---------- Contact actions ------------------------------------------- */
    .ov-contact { display:flex !important; align-items:center; gap:12px; padding:10px 12px; border:1px solid #e8eef7; border-radius:10px; background:#fbfdff; color:var(--lp-navy) !important; text-decoration:none !important; transition:background .18s ease, border-color .18s ease, color .18s ease; }
    .ov-contact + .ov-contact { margin-top:8px; }
    .ov-contact:hover { border-color:var(--lp-navy); background:var(--lp-navy); color:#fff !important; }
    .ov-contact-icon { display:flex; align-items:center; justify-content:center; flex:0 0 auto; width:32px; height:32px; border-radius:9px; background:#eef4ff; color:#1d4ed8; font-size:14px; transition:background .18s ease, color .18s ease; }
    .ov-contact:hover .ov-contact-icon { background:rgba(255,255,255,.14); color:#fff; }
    .ov-contact-copy { flex:1 1 auto; min-width:0; }
    .ov-contact-copy b { display:block; font-size:12.5px; font-weight:600; }
    .ov-contact-copy span { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11.5px; color:#8494a8; transition:color .18s ease; }
    .ov-contact:hover .ov-contact-copy span { color:rgba(255,255,255,.72); }
    .ov-contact-arrow { flex:0 0 auto; font-size:13px; color:#a4b2c6; transition:color .18s ease; }
    .ov-contact:hover .ov-contact-arrow { color:#fff; }
    .ov-hint { margin:14px 0 0; padding-top:13px; border-top:1px dashed #edf1f7; font-size:11.5px; line-height:1.6; color:#8494a8; }

    /* ---------- Organizer profile modal ----------------------------------- */
    /* A flat, editorial "dossier": a solid navy identity rail carrying the
       organizer's photo, name and at-a-glance facts, with the full record set
       as a typographic ledger beside it. No gradients and no font overrides —
       everything inherits the page's Poppins stack. Scoped with .ov-orgm so no
       other modal on the site is affected. */
    .ov-orgm .modal-dialog { max-width:min(780px,100% - 2rem); }
    .ov-orgm .modal-content { display:flex; flex-direction:row; max-height:86vh; overflow:hidden; border:0; border-radius:16px; background:#fff; box-shadow:0 28px 60px rgba(6,17,44,.42); }
    .ov-orgm-close { position:absolute; top:12px; right:12px; z-index:3; display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; padding:0; border:1px solid #e6ebf3; border-radius:4px; background:#fff; color:#5b6b80; font-size:13px; line-height:1; cursor:pointer; transition:background .15s ease, border-color .15s ease, color .15s ease; }
    .ov-orgm-close:hover { border-color:#d6dfec; background:#eef2f7; color:#0d1f45; }
    .ov-orgm-close:focus-visible { outline:2px solid #1d4ed8; outline-offset:2px; }

    /* Identity rail */
    .ov-orgm-rail { display:flex; flex-direction:column; flex:0 0 250px; padding:28px 24px 22px; background:#0d1f45; color:#fff; }
    .ov-orgm-rail-label { display:block; margin-bottom:20px; font-size:9.5px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:#8fa3c4; }
    .ov-orgm-photo { display:flex; align-items:center; justify-content:center; width:82px; height:82px; overflow:hidden; border-radius:4px; background:#1c3358; color:#fff; font-size:22px; font-weight:600; letter-spacing:.05em; }
    .ov-orgm-photo img { width:100%; height:100%; object-fit:cover; }
    .ov-orgm-name { margin:16px 0 5px; font-size:17px; font-weight:700; line-height:1.35; text-transform:capitalize; overflow-wrap:anywhere; }
    .ov-orgm-role { margin:0; font-size:11.5px; font-weight:500; color:#a9bbd6; }
    .ov-orgm-facts { display:flex; flex-direction:column; gap:14px; margin:22px 0 0; padding-top:18px; border-top:1px solid rgba(255,255,255,.14); }
    .ov-orgm-fact dt { margin:0 0 2px; font-size:9.5px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:#8fa3c4; }
    .ov-orgm-fact dd { margin:0; font-size:12.5px; font-weight:600; line-height:1.5; color:#fff; overflow-wrap:anywhere; }

    /* Rail actions */
    .ov-orgm-rail-actions { display:flex; flex-direction:column; gap:8px; margin-top:22px; }
    .ov-orgm-action { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:9px 14px; border:1px solid rgba(255,255,255,.26); border-radius:4px; background:transparent; color:#fff !important; font-size:12px; font-weight:600; text-decoration:none !important; cursor:pointer; transition:background .15s ease, border-color .15s ease, color .15s ease; }
    .ov-orgm-action:hover { border-color:rgba(255,255,255,.46); background:rgba(255,255,255,.1); color:#fff !important; }
    .ov-orgm-action.is-solid { border-color:#fff; background:#fff; color:#0d1f45 !important; }
    .ov-orgm-action.is-solid:hover { border-color:#e8eef8; background:#e8eef8; color:#0d1f45 !important; }
    .ov-orgm-action:focus-visible { outline:2px solid #9db8e6; outline-offset:2px; }
    .ov-orgm-rail-note { margin:auto 0 0; padding-top:22px; font-size:10px; line-height:1.6; color:#7e91b0; }

    /* Record ledger */
    .ov-orgm-record { flex:1 1 auto; min-width:0; padding:30px 30px 26px; overflow-y:auto; }
    .ov-orgm-block + .ov-orgm-block { margin-top:26px; }
    .ov-orgm-block-title { margin:0; padding-bottom:9px; border-bottom:1px solid #e6ebf3; font-size:10px; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:#7c8798; }
    .ov-orgm-rows { margin:0; }
    .ov-orgm-row { display:grid; grid-template-columns:128px minmax(0,1fr); gap:18px; padding:11px 0; border-bottom:1px solid #f1f4f9; }
    .ov-orgm-row:last-child { padding-bottom:0; border-bottom:0; }
    .ov-orgm-row dt { margin:0; font-size:12.5px; font-weight:400; line-height:1.6; color:#6b7789; }
    .ov-orgm-row dd { margin:0; font-size:13px; font-weight:600; line-height:1.6; color:#16233c; overflow-wrap:anywhere; }
    .ov-orgm-row dd.is-empty { color:#a3aec0; font-weight:500; }
    .ov-orgm-soft { color:#6b7789; font-weight:500; }
    .ov-orgm-copy { margin:14px 0 0; font-size:13px; font-weight:500; line-height:1.7; color:#16233c; overflow-wrap:anywhere; }
    .ov-orgm-link { color:#1d4ed8; text-decoration:none !important; border-bottom:1px solid rgba(29,78,216,.32); transition:color .15s ease, border-color .15s ease; }
    .ov-orgm-link:hover { color:#0d1f45; border-bottom-color:#0d1f45; }

    /* ---------- Responsive ------------------------------------------------ */
    @media (max-width:1199.98px) {
        .ov-metric { padding:18px 20px 16px; }
        .ov-snapshot-foot { padding:12px 20px; }
    }
    @media (max-width:991.98px) {
        .ov-grid { grid-template-columns:minmax(0,1fr); }
        .ov-rules { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:767.98px) {
        .ov-snapshot-grid { grid-template-columns:minmax(0,1fr); }
        .ov-metric + .ov-metric { border-left:0; border-top:1px solid #eef2f7; }
        .ov-metric + .ov-metric::before { display:none; }
        .ov-snapshot-foot { flex-direction:column; align-items:stretch; gap:9px; }
        .ov-orgm .modal-content { flex-direction:column; max-height:88vh; }
        .ov-orgm-close { top:10px; right:10px; width:30px; height:30px; }
        .ov-orgm-rail { flex:0 0 auto; padding:18px 16px 16px; }
        .ov-orgm-rail-label { margin-bottom:12px; }
        .ov-orgm-photo { width:56px; height:56px; font-size:17px; }
        .ov-orgm-name { margin-top:11px; font-size:15.5px; }
        .ov-orgm-facts { flex-direction:row; flex-wrap:wrap; gap:12px 24px; margin-top:14px; padding-top:13px; }
        .ov-orgm-rail-actions { flex-direction:row; flex-wrap:wrap; margin-top:14px; }
        .ov-orgm-action { flex:1 1 140px; }
        .ov-orgm-rail-note { margin:12px 0 0; padding-top:0; font-size:9.5px; }
        .ov-orgm-record { padding:20px 16px 18px; }
    }
    @media (max-width:575.98px) {
        .ov-rules { grid-template-columns:minmax(0,1fr); }
        .ov-news { gap:11px; }
        .ov-orgm-row { grid-template-columns:minmax(0,1fr); gap:3px; }
        .ov-orgm-row dt { font-size:9.5px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:#7c8798; }
    }

    /* ---------- Overview content refresh (below the shared header) -------- */
    .lp-page > .ov-grid { gap:22px; margin-top:26px; }
    .ov-stack { gap:20px; }
    .ov-stack > .lp-panel.ov-panel {
        position:relative;
        margin:0;
        padding:24px;
        overflow:hidden;
        border:1px solid #dfe7f1;
        border-radius:16px;
        background:#fff;
        box-shadow:0 10px 26px rgba(13,31,69,.07);
    }
    .ov-panel .lp-panel-head { margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid #e8eef6; }
    .ov-panel .lp-panel-title { gap:10px; color:#12284b; font-size:15px; font-weight:700; letter-spacing:-.01em; }
    .ov-panel .lp-panel-icon { width:38px; height:38px; border-radius:12px; background:#e9f1ff; color:#1e56a7; box-shadow:none; }
    .ov-panel .lp-panel-sub { margin-top:5px; color:#72829a; font-size:12px; line-height:1.55; }

    .ov-panel-requirements { background:linear-gradient(145deg,#f7faff,#edf4fc) !important; }
    .ov-panel-requirements .lp-panel-head { border-color:#dfe8f4; }
    .ov-panel-requirements .lp-panel-icon { background:#dcecff; color:#174f9c; }
    .ov-rules { gap:11px; }
    .ov-rule { position:relative; min-height:84px; justify-content:center; padding:14px 15px 14px 18px; border-color:#dce6f2; border-radius:12px; background:#fff; box-shadow:0 3px 8px rgba(16,43,82,.035); transition:transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
    .ov-rule::before { content:''; position:absolute; inset:12px auto 12px 0; width:3px; border-radius:0 3px 3px 0; background:#4181d4; }
    .ov-rule:hover { transform:translateY(-2px); border-color:#bfd2ec; box-shadow:0 8px 18px rgba(16,43,82,.08); }
    .ov-rule dt { color:#6f8098; }
    .ov-rule dt i { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:7px; background:#eaf2ff; color:#2362b5; font-size:11px; }
    .ov-rule dd { color:#142d52; font-size:14px; font-weight:500; }

    .ov-panel-announcements .lp-panel-head { margin:-24px -24px 20px; padding:20px 24px 16px; border-bottom-color:#dce7f5; background:linear-gradient(110deg,#eaf2ff,#f6f9ff); }
    .ov-panel-announcements .lp-panel-icon { background:#d9e8ff; color:#174f9c; }
    .ov-panel-announcements .ov-chip { padding:6px 11px; border:1px solid #d3e2f7; background:#fff; color:#225397; font-size:10px; }
    .ov-feed { gap:13px; }
    .ov-feed.has-items::before { left:17px; background:linear-gradient(180deg,#9ebce5,#dce7f6); }
    .ov-news-icon { border:2px solid #fff; background:#245ca8; color:#fff; box-shadow:0 0 0 1px #cdddf2,0 3px 9px rgba(17,58,112,.16); }
    .ov-news-card { padding:14px 16px; border-color:#e1e9f3; border-radius:12px; background:#f8fafd; }
    .ov-news-card:hover { border-color:#b9cee9; background:#fff; box-shadow:0 9px 22px rgba(13,43,83,.09); }
    .ov-news-title { color:#17345e; font-size:14px; }
    .ov-news-text { color:#52647c; }
    .ov-news-meta { color:#8291a7; }
    .ov-empty { padding:30px 20px; border-color:#d5e0ee; border-radius:13px; background:#f8fafd; }
    .ov-empty i { display:flex; align-items:center; justify-content:center; width:44px; height:44px; margin:0 0 7px; border-radius:14px; background:#e8f0fc; color:#3566a6; font-size:19px; }
    .ov-empty strong { color:#19365f; }
    .ov-empty span { color:#71829a; }

    .ov-org-panel { border-top:3px solid #3c72b7 !important; }
    .ov-docs-panel { border-top:3px solid #c35362 !important; }
    .ov-contact-panel { border-top:3px solid #16806c !important; }
    .ov-org-avatar { width:58px; height:58px; border:3px solid #e3edfb; border-radius:17px; background:linear-gradient(140deg,#164886,#2673b9); box-shadow:0 5px 13px rgba(20,63,117,.2); }
    .ov-org-name { color:#142e53; }
    .ov-org-role { color:#70819a; }
    .ov-org-btn { margin-top:18px; padding:10px 14px; border-color:#cbd9eb; border-radius:10px; background:#f1f6fd; color:#173d70; }
    .ov-org-btn:hover { border-color:#173d70; background:#173d70; color:#fff; }

    .ov-doc { border-color:#e0e8f2; border-radius:11px; background:#f8fafd; }
    .ov-doc:hover { border-color:#b9cde7; background:#fff; box-shadow:0 7px 16px rgba(17,48,90,.08); }
    .ov-doc-icon { width:36px; height:36px; border-radius:10px; background:#ffe9eb; color:#b8324c; }
    .ov-doc-name { color:#1c365d; }
    .ov-doc-arrow { color:#6680a2; }
    .ov-contact { border-color:#dde7f1; border-radius:11px; background:#f8fafd; }
    .ov-contact:hover { border-color:#174c74; background:#174c74; }
    .ov-contact-icon { width:36px; height:36px; border-radius:10px; background:#e4f3f1; color:#147765; }
    .ov-contact-copy b { color:#1b365d; }
    .ov-contact:hover .ov-contact-copy b { color:#fff; }
    .ov-contact-copy span { color:#71829a; }
    .ov-hint { color:#71829a; }

    @media (max-width:767.98px) {
        .lp-page > .ov-grid { gap:16px; margin-top:20px; }
        .ov-stack { gap:16px; }
        .ov-stack > .lp-panel.ov-panel { padding:19px; border-radius:14px; }
        .ov-panel-announcements .lp-panel-head { margin:-19px -19px 17px; padding:17px 19px 14px; }
        .ov-rule { min-height:78px; }
    }
    @media (prefers-reduced-motion:reduce) {
        .ov-rule, .ov-doc, .ov-contact, .ov-news-card { transition:none; }
    }

</style>

<?php
$fetch_team = "SELECT COUNT(*) as total_team 
           FROM tbl_team 
           WHERE league_id = '$id' AND LOWER(status) = 'approved'";

$team_res = mysqli_query($conn, $fetch_team);

$team_row = mysqli_fetch_assoc($team_res);

$total_team = $team_row['total_team'] ?? 0;

$max_team = $league_row['max_team'] ?? 0;

// Get eligibility requirements
$requirements = getEligibilityRequirements($conn, $id);
// Roster size may be a range or a fixed count (e.g. Pickleball: exactly 2).
$rosterBounds = normalizeRosterBounds($league_row['min_player'] ?? 0, $league_row['max_player'] ?? 0);

/* ---------------------------------------------------------------------------
   Overview presentation values.
   The hero banner, breadcrumb and tab strip are rendered by
   includes/league_header.php and stay untouched; everything below only feeds
   the redesigned Overview body.
   --------------------------------------------------------------------------- */
$ovDate = static function ($value) {
    $stamp = trim((string) $value) !== '' ? strtotime((string) $value) : false;
    return $stamp ? date('M d, Y', $stamp) : '—';
};

$approvedTeams  = (int) $total_team;
$maxTeamSlots   = max(0, (int) $max_team);
$openSlots      = max(0, $maxTeamSlots - $approvedTeams);
$fillPercentage = $maxTeamSlots > 0 ? max(0, min(100, (int) round($approvedTeams / $maxTeamSlots * 100))) : 0;

// Registration state drives the dot colour and the wording of the tile.
if ($registrationUpcoming) {
    $regState = 'upcoming';
    $regStateLabel = 'Opens soon';
    $regAccent = '#b45309';
} elseif ($regOpen) {
    $regState = 'open';
    $regStateLabel = 'Open';
    $regAccent = '#15803d';
} else {
    $regState = 'closed';
    $regStateLabel = 'Closed';
    $regAccent = '#64748b';
}

$daysLeft = ($regOpen && $regEndTs) ? max(0, (int) ceil(($regEndTs - $nowTs) / 86400)) : 0;
if ($slotsFull) {
    $regNote = 'All <b>' . $maxTeamSlots . '</b> team slots are taken.';
} elseif ($registrationUpcoming) {
    $regNote = 'Opens <b>' . htmlspecialchars($ovDate($league_row['regis_start'] ?? ''), ENT_QUOTES, 'UTF-8') . '</b> — applications are not accepted yet.';
} elseif ($regOpen) {
    $regNote = 'Closes <b>' . htmlspecialchars($ovDate($league_row['regis_end'] ?? ''), ENT_QUOTES, 'UTF-8') . '</b> · '
        . ($daysLeft > 0 ? $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left' : 'last day');
} else {
    $regNote = 'The registration window has closed for this league.';
}

// Roster tile: min === max means a fixed roster size.
$rosterValue = rosterSizeShortLabel($rosterBounds['min'], $rosterBounds['max']);
if ($rosterBounds['fixed']) {
    $rosterNote = 'Fixed roster — every team fields exactly <b>' . $rosterBounds['min'] . '</b>.';
} elseif ($rosterBounds['unlimited']) {
    $rosterNote = 'At least <b>' . $rosterBounds['min'] . '</b> players, with no upper limit.';
} else {
    $rosterNote = 'Between <b>' . $rosterBounds['min'] . '</b> and <b>' . $rosterBounds['max'] . '</b> players per team.';
}

// Entry-requirement values (getEligibilityRequirements() already ran above).
$reqMinAge    = (int) ($requirements['min_age'] ?? 0);
$reqMaxAge    = (int) ($requirements['max_age'] ?? 100);
$reqMinMale   = (int) ($requirements['min_male_players'] ?? 0);
$reqMinFemale = (int) ($requirements['min_female_players'] ?? 0);

if ($reqMinAge > 0 && $reqMaxAge < 100) {
    $ageLabel = $reqMinAge . '–' . $reqMaxAge . ' years';
} elseif ($reqMaxAge < 100) {
    $ageLabel = 'Up to ' . $reqMaxAge . ' years';
} elseif ($reqMinAge > 0) {
    $ageLabel = $reqMinAge . ' years and above';
} else {
    $ageLabel = 'Open to all ages';
}

$genderParts = [];
if ($reqMinMale > 0)   $genderParts[] = $reqMinMale . ' male';
if ($reqMinFemale > 0) $genderParts[] = $reqMinFemale . ' female';
$genderLabel = $genderParts ? 'Min ' . implode(' · ', $genderParts) : 'No gender minimum';

$divisionLabel = trim((string) ($league_row['division'] ?? '')) ?: '—';
$categoryLabel = trim((string) ($league_row['category'] ?? '')) ?: '—';
$entryFee      = trim((string) ($league_row['regis_fee'] ?? ''));
$showEntryFee  = $entryFee !== '' && !in_array($entryFee, ['0', '0.00'], true);

// Organizer identity, shared by the sidebar card and the profile modal.
$orgFullName   = trim(($organizer['fname'] ?? '') . ' ' . ($organizer['mname'] ?? '') . ' ' . ($organizer['lname'] ?? '') . ' ' . ($organizer['suffix'] ?? ''));
$orgInitials   = strtoupper(mb_substr(trim((string) ($organizer['fname'] ?? '')), 0, 1) . mb_substr(trim((string) ($organizer['lname'] ?? '')), 0, 1));
if ($orgInitials === '') $orgInitials = '?';

$orgAvatarRaw = trim((string) ($organizer['profile_image'] ?? ''));
$orgAvatarSrc = '';
if ($orgAvatarRaw !== '') {
    if (StorageService::isCloudinary($orgAvatarRaw)) {
        $orgAvatarSrc = $orgAvatarRaw;
    } else {
        // Uploaded avatars are stored as '../../uploads/...' by the admin pages.
        $orgAvatarSrc = ltrim(preg_replace('#^(\.\./)+#', '', str_replace('\\', '/', $orgAvatarRaw)), '/');
        if (!is_file(__DIR__ . '/' . $orgAvatarSrc)) $orgAvatarSrc = '';
    }
}

// Contact actions reuse the organizer record so the page never shows details
// that do not exist.
$orgEmail     = trim((string) ($organizer['email'] ?? ''));
$orgPhone     = trim((string) ($organizer['phone'] ?? ''));
$orgPhoneDial = preg_replace('/[^0-9+]/', '', $orgPhone);
?>

<div class="container-fluid lp-page">
    <p class="ov-eyebrow">League snapshot</p>

    <section class="ov-snapshot" aria-label="League snapshot">
        <div class="ov-snapshot-grid">
            <div class="ov-metric" style="--ov-accent:#1a3a7c;">
                <span class="ov-metric-label"><i class="bi bi-people-fill"></i>Teams registered</span>
                <span class="ov-metric-value"><?= $approvedTeams ?><small>/ <?= $maxTeamSlots ?></small></span>
                <span class="ov-metric-note">
                    <?php if ($openSlots > 0): ?>
                        <b><?= $openSlots ?></b> slot<?= $openSlots === 1 ? '' : 's' ?> still open for this league.
                    <?php elseif ($maxTeamSlots > 0): ?>
                        Every team slot has been filled.
                    <?php else: ?>
                        No team cap has been set for this league.
                    <?php endif; ?>
                </span>
            </div>

            <div class="ov-metric" style="--ov-accent:<?= htmlspecialchars($regAccent, ENT_QUOTES, 'UTF-8') ?>;">
                <span class="ov-metric-label"><i class="bi bi-hourglass-split"></i>Registration</span>
                <span class="ov-state is-<?= htmlspecialchars($regState, ENT_QUOTES, 'UTF-8') ?>"><span class="ov-dot"></span><?= htmlspecialchars($regStateLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="ov-metric-note"><?= $regNote ?></span>
            </div>

            <div class="ov-metric" style="--ov-accent:#0e7490;">
                <span class="ov-metric-label"><i class="bi bi-person-vcard"></i>Players per team</span>
                <span class="ov-metric-value"><?= htmlspecialchars($rosterValue, ENT_QUOTES, 'UTF-8') ?><small>players</small></span>
                <span class="ov-metric-note"><?= $rosterNote ?></span>
            </div>
        </div>

        <?php if ($maxTeamSlots > 0): ?>
        <div class="ov-snapshot-foot">
            <div class="ov-meter" role="img" aria-label="<?= $fillPercentage ?>% of team slots filled">
                <span style="width:<?= $fillPercentage ?>%;"></span>
            </div>
            <span class="ov-meter-caption"><b><?= $approvedTeams ?></b> of <b><?= $maxTeamSlots ?></b> team slots filled · <?= $fillPercentage ?>%</span>
        </div>
        <?php endif; ?>
    </section>

    <div class="ov-grid">
        <div class="ov-stack">
            <section class="lp-panel ov-panel">
                <div class="lp-panel-head">
                    <div class="ov-panel-heading">
                        <h2 class="lp-panel-title"><span class="lp-panel-icon"><i class="bi bi-info-lg"></i></span>About the league</h2>  
                    </div>
                </div>

                <?php $aboutText = trim((string) ($league_row['descrip'] ?? '')); ?>
                <?php if ($aboutText !== ''): ?>
                    <div class="ov-prose is-lead"><?= nl2br(htmlspecialchars($aboutText)) ?></div>
                <?php else: ?>
                    <p class="ov-prose is-muted">The organizer has not published a description for this league yet.</p>
                <?php endif; ?>

                <?php if (trim((string) ($league_row['add_info'] ?? '')) !== ''): ?>
                <div class="ov-subblock">
                    <h3 class="ov-subblock-title"><i class="bi bi-card-checklist"></i>Other information</h3>
                    <div class="ov-prose"><?= nl2br(htmlspecialchars((string) $league_row['add_info'])) ?></div>
                </div>
                <?php endif; ?>
            </section>

            <section class="lp-panel ov-panel ov-panel-requirements">
                <div class="lp-panel-head">
                    <div class="ov-panel-heading">
                        <h2 class="lp-panel-title"><span class="lp-panel-icon"><i class="bi bi-clipboard-check-fill"></i></span>Entry requirements</h2>
                        
                    </div>
                </div>
                <dl class="ov-rules">
                    <div class="ov-rule">
                        <dt><i class="bi bi-diagram-3"></i>Division</dt>
                        <dd><?= htmlspecialchars($divisionLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="ov-rule">
                        <dt><i class="bi bi-calendar-heart"></i>Age bracket</dt>
                        <dd><?= htmlspecialchars($ageLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="ov-rule">
                        <dt><i class="bi bi-people"></i>Team size</dt>
                        <dd><?= htmlspecialchars(rosterSizeLabel($rosterBounds['min'], $rosterBounds['max']), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="ov-rule">
                        <dt><i class="bi bi-gender-ambiguous"></i>Gender mix</dt>
                        <dd><?= htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="ov-rule">
                        <dt><i class="bi bi-tags"></i>Category</dt>
                        <dd><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <?php if ($showEntryFee): ?>
                    <div class="ov-rule">
                        <dt><i class="bi bi-cash-coin"></i>Registration fee</dt>
                        <dd><?= htmlspecialchars($entryFee, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="lp-panel ov-panel ov-panel-announcements">
                <div class="lp-panel-head">
                    <div class="ov-panel-heading">
                        <h2 class="lp-panel-title"><span class="lp-panel-icon"><i class="bi bi-megaphone-fill"></i></span>Announcements</h2>
                        
                    </div>
                    <span class="ov-chip"><i class="bi bi-broadcast"></i>Live feed</span>
                </div>
                <div id="announcementContainer" class="ov-feed" aria-live="polite">
                    <div class="ov-empty">
                        <i class="bi bi-hourglass-split"></i>
                        <strong>Loading announcements…</strong>
                        <span>Fetching the latest updates from the organizer.</span>
                    </div>
                </div>
            </section>
        </div>
        <aside class="ov-stack" aria-label="League details">
            <?php if (!empty($organizer)): ?>
            <section class="lp-panel ov-panel ov-org-panel">
                <div class="lp-panel-head">
                    <div class="ov-panel-heading">
                        <h2 class="lp-panel-title"><span class="lp-panel-icon"><i class="bi bi-person-badge-fill"></i></span>Organizer</h2>
                    </div>
                </div>
                <div class="ov-org">
                    <span class="ov-org-avatar">
                        <?php if ($orgAvatarSrc !== ''): ?>
                            <img src="<?= htmlspecialchars($orgAvatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($orgFullName, ENT_QUOTES, 'UTF-8') ?>">
                        <?php else: ?>
                            <?= htmlspecialchars($orgInitials, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>
                    <div>
                        <strong class="ov-org-name"><?= htmlspecialchars($orgFullName !== '' ? $orgFullName : 'League organizer', ENT_QUOTES, 'UTF-8') ?></strong>
                        <span class="ov-org-role">League organizer</span>
                    </div>
                </div>
                <button type="button" class="ov-org-btn" data-bs-toggle="modal" data-bs-target="#organizerProfileModal"
                        aria-haspopup="dialog" aria-controls="organizerProfileModal">
                    <span><i class="bi bi-person-lines-fill"></i>View full profile</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </section>
            <?php endif; ?>

            <?php $docCount = count($guidelineDocuments); ?>
            <section class="lp-panel ov-panel ov-docs-panel">
                <div class="lp-panel-head">
                    <div class="ov-panel-heading">
                        <h2 class="lp-panel-title"><span class="lp-panel-icon"><i class="bi bi-file-earmark-text-fill"></i></span>Documents</h2>
                        <p class="lp-panel-sub">Guidelines and tournament files.</p>
                    </div>
                    <?php if ($docCount > 0): ?>
                        <span class="ov-chip"><i class="bi bi-files"></i><?= $docCount ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($docCount > 0): ?>
                    <?php foreach ($guidelineDocuments as $guideline):
                        $docName    = (string) ($guideline['original_name'] ?? 'Document');
                        // Both local and Cloudinary uploads are streamed through
                        // the app so clicking a document downloads it (the HTML
                        // download attribute is ignored for cross-origin URLs,
                        // which used to open Cloudinary files in a new tab).
                        $docHref    = 'includes/downloadLeagueDocument.php?id=' . (int) ($guideline['id'] ?? 0);
                        $docExt     = strtolower(pathinfo($docName, PATHINFO_EXTENSION));
                        if ($docExt === 'pdf') {
                            $docIcon = 'bi-file-earmark-pdf-fill';
                        } elseif ($docExt === 'doc' || $docExt === 'docx') {
                            $docIcon = 'bi-file-earmark-word-fill';
                        } elseif ($docExt === 'xls' || $docExt === 'xlsx') {
                            $docIcon = 'bi-file-earmark-spreadsheet-fill';
                        } elseif (in_array($docExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                            $docIcon = 'bi-file-earmark-image-fill';
                        } else {
                            $docIcon = 'bi-file-earmark-text-fill';
                        }
                    ?>
                        <a class="ov-doc" href="<?= htmlspecialchars($docHref, ENT_QUOTES, 'UTF-8') ?>" download title="Download <?= htmlspecialchars($docName, ENT_QUOTES, 'UTF-8') ?>">
                            <span class="ov-doc-icon"><i class="bi <?= $docIcon ?>"></i></span>
                            <span class="ov-doc-name"><?= htmlspecialchars($docName, ENT_QUOTES, 'UTF-8') ?></span>
                            <i class="bi bi-download ov-doc-arrow"></i>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="ov-empty">
                        <i class="bi bi-folder2-open"></i>
                        <strong>No documents yet</strong>
                        <span>Guidelines will appear here once they are published.</span>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($orgEmail !== '' || $orgPhoneDial !== ''): ?>
            <section class="lp-panel ov-panel ov-contact-panel">
                <div class="lp-panel-head">
                    <div class="ov-panel-heading">
                        <h2 class="lp-panel-title"><span class="lp-panel-icon"><i class="bi bi-chat-dots-fill"></i></span>Inquiries</h2>
                        <p class="lp-panel-sub">Reach the organizer directly.</p>
                    </div>
                </div>
                <?php if ($orgEmail !== ''): ?>
                    <a class="ov-contact" href="mailto:<?= htmlspecialchars($orgEmail, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="ov-contact-icon"><i class="bi bi-envelope-fill"></i></span>
                        <span class="ov-contact-copy">
                            <b>Email the organizer</b>
                            <span><?= htmlspecialchars($orgEmail, ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <i class="bi bi-arrow-up-right ov-contact-arrow"></i>
                    </a>
                <?php endif; ?>
                <?php if ($orgPhoneDial !== ''): ?>
                    <a class="ov-contact" href="tel:<?= htmlspecialchars($orgPhoneDial, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="ov-contact-icon"><i class="bi bi-telephone-fill"></i></span>
                        <span class="ov-contact-copy">
                            <b>Call the organizer</b>
                            <span><?= htmlspecialchars($orgPhone, ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <i class="bi bi-arrow-up-right ov-contact-arrow"></i>
                    </a>
                <?php endif; ?>
                <p class="ov-hint">Questions about eligibility or requirements? The organizer usually replies during office hours.</p>
            </section>
            <?php endif; ?>
        </aside>
    </div>
</div>
<!-- Organizer Profile Modal -->
<?php if (!empty($organizer)): ?>
<?php
    // $orgFullName and $orgAvatarSrc were resolved with the overview values so
    // the sidebar card and this modal always show the same name and photo.
    $orgProfileImg  = $orgAvatarSrc !== '' ? $orgAvatarSrc : null;
    $orgDisplayName = $orgFullName !== '' ? $orgFullName : 'League organizer';

    $orgGenderLabel = '';
    if (trim((string) ($organizer['gender'] ?? '')) !== '') {
        $orgGenderLabel = ucfirst(strtolower(trim((string) $organizer['gender'])));
    }

    $orgDobTs    = !empty($organizer['date_of_birth']) ? strtotime((string) $organizer['date_of_birth']) : false;
    $orgDobLabel = $orgDobTs ? date('F j, Y', $orgDobTs) : '';
    $orgAgeLabel = '';
    if ($orgDobTs) {
        $orgAgeYears = (new DateTime(date('Y-m-d', $orgDobTs)))->diff(new DateTime('today'))->y;
        if ($orgAgeYears > 0) {
            $orgAgeLabel = $orgAgeYears . ' year' . ($orgAgeYears === 1 ? '' : 's') . ' old';
        }
    }

    // years_experience is numeric for current records, but legacy rows may hold
    // free text, so both shapes are formatted instead of cast blindly.
    $orgExperienceRaw   = trim((string) ($organizer['years_experience'] ?? ''));
    $orgExperienceLabel = '';
    if ($orgExperienceRaw !== '') {
        if (is_numeric($orgExperienceRaw)) {
            $orgExperienceYears = (float) $orgExperienceRaw;
            if ($orgExperienceYears <= 0) {
                $orgExperienceLabel = 'No prior experience';
            } else {
                $orgYearsText       = rtrim(rtrim(number_format($orgExperienceYears, 1, '.', ''), '0'), '.');
                $orgExperienceLabel = $orgYearsText . ' year' . ($orgExperienceYears == 1 ? '' : 's') . ' of experience';
            }
        } else {
            $orgExperienceLabel = $orgExperienceRaw;
        }
    }

    $orgCredentials = trim((string) ($organizer['credentials'] ?? ''));
    $orgAddress     = trim((string) ($organizer['address'] ?? ''));
    // Some legacy rows store "0" for an address that was never filled in.
    if ($orgAddress === '0') {
        $orgAddress = '';
    }
    $orgCreatedTs   = !empty($organizer['created']) ? strtotime((string) $organizer['created']) : false;
    $orgMemberSince = $orgCreatedTs ? date('F Y', $orgCreatedTs) : '';

    // The rail carries the at-a-glance facts; the record carries the details,
    // so nothing is printed twice (gender/date of birth stay in the record).
    $orgRailFacts = [];
    if ($orgExperienceLabel !== '') {
        $orgRailFacts[] = ['Experience', $orgExperienceLabel];
    }
    if ($orgMemberSince !== '') {
        $orgRailFacts[] = ['Member since', $orgMemberSince];
    }
?>
<div class="modal fade ov-orgm" id="organizerProfileModal" tabindex="-1" aria-labelledby="organizerProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="ov-orgm-close" data-bs-dismiss="modal" aria-label="Close organizer profile">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>

            <!-- Identity rail -->
            <aside class="ov-orgm-rail">
                <span class="ov-orgm-rail-label">Organizer profile</span>
                <div class="ov-orgm-photo" aria-hidden="true">
                    <?php if ($orgProfileImg): ?>
                        <img src="<?= htmlspecialchars($orgProfileImg, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars($orgInitials, ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
                <h4 class="ov-orgm-name" id="organizerProfileModalLabel"><?= htmlspecialchars($orgDisplayName, ENT_QUOTES, 'UTF-8') ?></h4>
                <p class="ov-orgm-role">League organizer</p>
                <?php if ($orgRailFacts): ?>
                    <dl class="ov-orgm-facts">
                        <?php foreach ($orgRailFacts as [$factLabel, $factValue]): ?>
                            <div class="ov-orgm-fact">
                                <dt><?= htmlspecialchars($factLabel, ENT_QUOTES, 'UTF-8') ?></dt>
                                <dd><?= htmlspecialchars($factValue, ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
                <div class="ov-orgm-rail-actions">
                    <?php if ($orgEmail !== ''): ?>
                        <a class="ov-orgm-action is-solid" href="mailto:<?= htmlspecialchars($orgEmail, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="bi bi-envelope" aria-hidden="true"></i>Email organizer
                        </a>
                    <?php endif; ?>
                    <?php if ($orgPhoneDial !== ''): ?>
                        <a class="ov-orgm-action" href="tel:<?= htmlspecialchars($orgPhoneDial, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="bi bi-telephone" aria-hidden="true"></i>Call organizer
                        </a>
                    <?php endif; ?>
                </div>
                <p class="ov-orgm-rail-note">Details are supplied by the organizer.</p>
            </aside>
            <!-- Record -->
            <div class="ov-orgm-record">
                <section class="ov-orgm-block" aria-labelledby="ovOrgmPersonalTitle">
                    <h5 class="ov-orgm-block-title" id="ovOrgmPersonalTitle">Personal details</h5>
                    <dl class="ov-orgm-rows">
                        <div class="ov-orgm-row">
                            <dt>Full name</dt>
                            <dd class="text-capitalize"><?= htmlspecialchars($orgDisplayName, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="ov-orgm-row">
                            <dt>Gender</dt>
                            <dd<?= $orgGenderLabel === '' ? ' class="is-empty"' : '' ?>><?= $orgGenderLabel !== '' ? htmlspecialchars($orgGenderLabel, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                        </div>
                        <div class="ov-orgm-row">
                            <dt>Date of birth</dt>
                            <?php if ($orgDobLabel !== ''): ?>
                                <dd>
                                    <?= htmlspecialchars($orgDobLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($orgAgeLabel !== ''): ?>
                                        <span class="ov-orgm-soft">· <?= htmlspecialchars($orgAgeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </dd>
                            <?php else: ?>
                                <dd class="is-empty">—</dd>
                            <?php endif; ?>
                        </div>
                        <div class="ov-orgm-row">
                            <dt>Address</dt>
                            <?php if ($orgAddress !== ''): ?>
                                <dd><?= nl2br(htmlspecialchars($orgAddress, ENT_QUOTES, 'UTF-8')) ?></dd>
                            <?php else: ?>
                                <dd class="is-empty">—</dd>
                            <?php endif; ?>
                        </div>
                    </dl>
                </section>

                <section class="ov-orgm-block" aria-labelledby="ovOrgmContactTitle">
                    <h5 class="ov-orgm-block-title" id="ovOrgmContactTitle">Contact information</h5>
                    <dl class="ov-orgm-rows">
                        <div class="ov-orgm-row">
                            <dt>Email address</dt>
                            <?php if ($orgEmail !== ''): ?>
                                <dd><a class="ov-orgm-link" href="mailto:<?= htmlspecialchars($orgEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($orgEmail, ENT_QUOTES, 'UTF-8') ?></a></dd>
                            <?php else: ?>
                                <dd class="is-empty">—</dd>
                            <?php endif; ?>
                        </div>
                        <div class="ov-orgm-row">
                            <dt>Phone number</dt>
                            <?php if ($orgPhone !== ''): ?>
                                <dd>
                                    <?php if ($orgPhoneDial !== ''): ?>
                                        <a class="ov-orgm-link" href="tel:<?= htmlspecialchars($orgPhoneDial, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($orgPhone, ENT_QUOTES, 'UTF-8') ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($orgPhone, ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </dd>
                            <?php else: ?>
                                <dd class="is-empty">—</dd>
                            <?php endif; ?>
                        </div>
                    </dl>
                </section>

                <?php if ($orgCredentials !== ''): ?>
                <section class="ov-orgm-block" aria-labelledby="ovOrgmCredentialsTitle">
                    <h5 class="ov-orgm-block-title" id="ovOrgmCredentialsTitle">Credentials</h5>
                    <p class="ov-orgm-copy"><?= nl2br(htmlspecialchars($orgCredentials, ENT_QUOTES, 'UTF-8')) ?></p>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    const leagueId = <?php echo $id; ?>;

    // The organizer sheet is a scrollable dialog: reset the record to the top
    // every time it opens so the identity rail is always the first thing seen.
    document.addEventListener('DOMContentLoaded', function() {
        const organizerModal = document.getElementById('organizerProfileModal');
        if (organizerModal) {
            organizerModal.addEventListener('shown.bs.modal', function() {
                const record = organizerModal.querySelector('.ov-orgm-record');
                if (record) {
                    record.scrollTop = 0;
                }
            });
        }
    });

    function escapeAnnouncementText(value) {
        const element = document.createElement('div');
        element.textContent = value ?? '';
        return element.innerHTML;
    }

    function loadPublicAnnouncements() {
        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('league_id', leagueId);

        fetch('./app/organizer/action/announcementAction.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            const container = document.getElementById('announcementContainer');
            
            if (res.status === 200 && res.announcements && res.announcements.length > 0) {
                let html = '';
                res.announcements.forEach(ann => {
                    const safeTitle = escapeAnnouncementText(ann.title);
                    const safeContent = escapeAnnouncementText(ann.content);
                    // MySQL DATETIMEs are not parsed consistently across browsers.
                    const created = new Date(String(ann.created || '').replace(' ', 'T'));
                    const date = isNaN(created.getTime()) ? '' : created.toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    const meta = date
                        ? `<span class="ov-news-meta"><i class="bi bi-clock"></i>${date}</span>`
                        : '';
                    html += `
                        <article class="ov-news">
                            <span class="ov-news-icon"><i class="bi bi-megaphone-fill"></i></span>
                            <div class="ov-news-card">
                                <h3 class="ov-news-title">${safeTitle}</h3>
                                <p class="ov-news-text">${safeContent}</p>
                                ${meta}
                            </div>
                        </article>
                    `;
                });
                container.classList.add('has-items');
                container.innerHTML = html;
            } else {
                container.classList.remove('has-items');
                container.innerHTML = `
                    <div class="ov-empty">
                        <i class="bi bi-megaphone"></i>
                        <strong>No announcements yet</strong>
                        
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading announcements:', error);
            const container = document.getElementById('announcementContainer');
            if (container) {
                container.classList.remove('has-items');
                container.innerHTML = `
                    <div class="ov-empty">
                        <i class="bi bi-exclamation-circle"></i>
                        <strong>Announcements unavailable</strong>
                        <span>Please refresh the page to try again.</span>
                    </div>
                `;
            }
        });
    }

    // Load announcements when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadPublicAnnouncements);
    } else {
        loadPublicAnnouncements();
    }
</script>
<?php include 'includes/footer.php'; ?>
