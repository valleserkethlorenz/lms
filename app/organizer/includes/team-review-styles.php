<?php
/**
 * Team review — page-specific styles.
 *
 * Shared by the organizer's review queue (pending_teams.php) and the
 * per-team review screen (view_player.php) so both screens read as one flow.
 * Everything structural (hero banner, breadcrumb, pills, buttons, panels,
 * modals, row status chips) already lives in includes/league-view-styles.php
 * and includes/dashboard-styles.php; this file only adds the review-specific
 * pieces: the search toolbar, the review table, the player cells and the
 * document cards.
 *
 * Usage:  <?php include 'includes/team-review-styles.php'; ?>
 */
?>
<style>
    /* ---------- Review toolbar (search) ------------------------------------- */
    .tvr-toolbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; min-width:0; }
    .tvr-search { position:relative; min-width:0; }
    .tvr-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:13px; color:#9ca3af; pointer-events:none; }
    .tvr-search input { width:250px; max-width:100%; min-width:0; padding:8px 14px 8px 33px; border:1.5px solid var(--lp-border,#e5e7eb); border-radius:8px; background:#f9fafb; font-size:12.5px; transition:border-color .2s ease, box-shadow .2s ease, background .2s ease; }
    .tvr-search input:focus { outline:none; border-color:var(--lp-blue,#2563eb); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.1); }

    /* ---------- Review table ------------------------------------------------ */
    .tvr-table-wrap { overflow-x:auto; }
    .tvr-table { width:100%; margin:0; font-size:13.5px; }
    .tvr-table thead th { white-space:nowrap; }
    .tvr-table tbody td { padding:13px 16px; vertical-align:middle; }
    .tvr-index { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:9px; background:#f1f5f9; color:#475569; font-size:12px; font-weight:700; }
    .tvr-team-cell { display:flex; align-items:center; gap:12px; min-width:210px; }
    .tvr-team-avatar { width:42px; height:42px; flex:0 0 auto; border-radius:11px; border:1px solid #e8edf4; background:#eef2f7; object-fit:cover; }
    .tvr-team-name { font-weight:600; color:var(--lp-ink,#09173c); line-height:1.3; text-transform:capitalize; }
    .tvr-team-sub { font-size:11.5px; color:#9ca3af; }
    .tvr-roster { min-width:130px; }
    .tvr-roster-count { font-size:12.5px; font-weight:600; color:var(--lp-ink,#09173c); }
    .tvr-roster .lp-progress { height:5px; margin-top:7px; }
    .tvr-cell-strong { font-weight:500; color:#334155; text-transform:capitalize; white-space:nowrap; }
    .tvr-cell-muted { color:var(--lp-muted,#6b7280); font-size:12.5px; white-space:nowrap; }

    /* ---------- Row actions ------------------------------------------------- */
    /* All row actions live in one nowrap row that is pinned to the right edge of
       a content-sized column, so every row lines up exactly under the ACTION
       header instead of wrapping into a ragged second line. Same treatment as
       the action cells on League Management, Schedule and Standings. */
    .tvr-action-column { width:1%; white-space:nowrap; }
    .tvr-actions { display:inline-flex; align-items:center; justify-content:flex-end; gap:6px; flex-wrap:nowrap; white-space:nowrap; }
    .tvr-actions .league-icon-btn { width:34px; height:34px; flex:0 0 auto; border-radius:9px; font-size:.95rem; }
    .league-icon-btn.tvr-action-danger { color:#b42318; background:#fee2e2; border-color:#fecaca; }
    .league-icon-btn.tvr-action-danger:hover { color:#fff; background:#b42318; border-color:#b42318; }
    /* On narrower laptops the table scrolls sideways; pin the action column to
       the right edge so the decision buttons never scroll out of reach. The
       inset hairline doubles as the column divider on wide screens. */
    .tvr-table .tvr-action-column { position:sticky; right:0; z-index:2; background:#fff; box-shadow:inset 1px 0 0 #eef2f7; }
    .tvr-table thead .tvr-action-column { background:#09173c; box-shadow:inset 1px 0 0 rgba(255,255,255,.14); }
    .tvr-table tbody tr:hover .tvr-action-column { background:#f0f5ff; }

    /* ---------- Compact action pills ---------------------------------------- */
    .tvr-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:7px 13px; border:1px solid transparent; border-radius:8px; font-size:11.5px; font-weight:600; line-height:1.2; white-space:nowrap; text-decoration:none !important; cursor:pointer; transition:background .15s ease, border-color .15s ease, color .15s ease, transform .15s ease; }
    .tvr-btn:hover { transform:translateY(-1px); }
    .tvr-btn:focus-visible { outline:3px solid rgba(37,99,235,.28); outline-offset:2px; }
    .tvr-btn:disabled { opacity:.6; cursor:not-allowed; transform:none; }
    .tvr-btn-approve { background:#e8f6ee; border-color:#b9e3ca; color:#0f7b4f; }
    .tvr-btn-approve:hover { background:#15803d; border-color:#15803d; color:#fff; }
    .tvr-btn-return { background:#fef3c7; border-color:#f5d78b; color:#92400e; }
    .tvr-btn-return:hover { background:#b45309; border-color:#b45309; color:#fff; }
    .tvr-btn-reject { background:#fee2e2; border-color:#fbcaca; color:#b42318; }
    .tvr-btn-reject:hover { background:#b42318; border-color:#b42318; color:#fff; }
    .tvr-btn-neutral { background:#fff; border-color:var(--lp-border,#e5e7eb); color:var(--lp-navy,#09173c); }
    .tvr-btn-neutral:hover { background:var(--lp-soft,#f0f5ff); border-color:#c7d5ea; color:var(--lp-navy,#09173c); }
    .tvr-decision { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .tvr-decision form { margin:0; }

    /* ---------- Decision bar below the roster table -------------------------- */
    .tvr-actionbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:18px 0 20px; }
    .tvr-actionbar .tvr-decision { margin-left:auto; }
    .tvr-actionbar .tvr-state { display:inline-flex; align-items:center; gap:7px; margin-left:auto; padding:8px 16px; border:1px solid var(--lp-border,#e5e7eb); border-radius:8px; background:#f8fafc; color:#475569; font-size:12px; font-weight:600; }
    /* Explains why "Return for correction" is unavailable (nothing left to fix). */
    .tvr-hint { display:flex; align-items:flex-start; gap:8px; margin:-8px 0 18px; font-size:11.5px; line-height:1.5; color:#6b7280; }
    .tvr-hint i { margin-top:1px; color:#94a3b8; }
    @media (max-width:575px) {
        .tvr-actionbar .tvr-decision,
        .tvr-actionbar .tvr-state { margin-left:0; width:100%; }
        .tvr-decision .tvr-btn, .tvr-actionbar > .lp-link-btn { flex:1 1 100%; }
    }

    /* ---------- Team hero --------------------------------------------------- */
    .tvr-hero .lp-hero-inner { padding:28px 30px; }
    .tvr-hero-head { display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
    .tvr-hero-crest { width:86px; height:86px; flex:0 0 auto; border-radius:16px; border:2px solid rgba(255,255,255,.35); background:#fff; object-fit:cover; box-shadow:0 10px 24px rgba(3,10,26,.38); }
    .tvr-hero-copy { flex:1 1 320px; min-width:0; }
    .tvr-hero-copy .lp-hero-title { margin-bottom:14px; }
    .tvr-hero-copy .lp-hero-meta { gap:14px 30px; }
    .tvr-hero-copy .lp-meta-item span { max-width:26ch; overflow:hidden; text-overflow:ellipsis; }
    .tvr-pill-pending { background:#fef3c7; color:#92400e; }
    .tvr-pill-approved { background:#d1fae5; color:#065f46; }
    .tvr-pill-rejected { background:#fee2e2; color:#991b1b; }
    .tvr-pill-correction { background:#dbeafe; color:#1e40af; }

    /* ---------- Inline notices ---------------------------------------------- */
    .tvr-note { display:flex; align-items:flex-start; gap:12px; margin:0 0 20px; padding:14px 18px; border:1px solid #fde68a; border-radius:10px; background:#fffbeb; color:#92400e; font-size:12.5px; line-height:1.55; }
    .tvr-note i { margin-top:1px; font-size:16px; flex:0 0 auto; }
    .tvr-note strong { display:block; margin-bottom:3px; color:#7c2d12; font-weight:600; }
    .tvr-note p { margin:0; }
    .tvr-note ul { margin:6px 0 0; padding-left:18px; }
    .tvr-note-danger { border-color:#fecaca; background:#fef2f2; color:#991b1b; }
    .tvr-note-danger strong { color:#7f1d1d; }

    /* ---------- Player cells + chips ---------------------------------------- */
    .tvr-player-cell { display:flex; align-items:center; gap:11px; min-width:180px; }
    .tvr-player-avatar { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; flex:0 0 auto; border-radius:50%; background:#e8edf7; color:#1e3a68; font-size:12px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; }
    .tvr-player-name { font-weight:600; color:var(--lp-ink,#09173c); line-height:1.3; text-transform:capitalize; }
    .tvr-player-sub { font-size:11px; color:#9ca3af; }
    .tvr-chip { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:10.5px; font-weight:600; letter-spacing:.3px; white-space:nowrap; }
    .tvr-chip-neutral { background:#eef2f7; color:#475569; }

    /* ---------- Roster: documents + eligibility ----------------------------- */
    .tvr-steps { display:flex; align-items:center; flex-wrap:wrap; gap:6px 18px; padding:0 24px 16px; font-size:11.5px; color:#6b7280; }
    .tvr-step { display:inline-flex; align-items:center; gap:7px; }
    .tvr-step b { display:inline-flex; align-items:center; justify-content:center; width:19px; height:19px; border-radius:50%; background:#e8edf7; color:#1e3a68; font-size:10.5px; font-weight:700; }

    .tvr-docs-cell { min-width:300px; }
    .tvr-doc-item { padding:10px 12px; border:1px solid #eef2f7; border-radius:10px; background:#fff; }
    .tvr-doc-item + .tvr-doc-item { margin-top:8px; }
    .tvr-doc-item.is-missing { border-style:dashed; border-color:#fbd0d0; background:#fffafa; }
    .tvr-doc-item-head { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .tvr-doc-item-icon { font-size:13px; color:#94a3b8; }
    .tvr-doc-item-name { font-size:12px; font-weight:600; color:var(--lp-ink,#09173c); }
    .tvr-doc-item-head .lp-row-status { margin-left:auto; padding:3px 9px; font-size:10px; }
    .tvr-doc-item-foot { display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-top:7px; }
    .tvr-doc-item-link { display:inline-flex; align-items:center; gap:5px; font-size:11.5px; font-weight:600; color:#123f70; text-decoration:none !important; }
    .tvr-doc-item-link:hover { color:var(--lp-blue,#2563eb); text-decoration:underline !important; }
    .tvr-doc-quality { display:inline-flex; align-items:center; gap:5px; font-size:11px; color:#94a3b8; }
    .tvr-doc-quality.is-ok { color:#0f7b4f; }
    .tvr-doc-quality.is-warn { color:#b45309; font-weight:600; }
    .tvr-doc-item-remarks { margin:7px 0 0; font-size:11px; line-height:1.45; color:#6b7280; }

    .tvr-elig { display:flex; flex-direction:column; align-items:flex-start; gap:5px; }
    .tvr-elig .lp-row-status { white-space:nowrap; }
    .tvr-elig-remarks { font-size:11px; line-height:1.45; color:#6b7280; }

    /* The eligibility menu explains every outcome, so it can never be mistaken
       for the per-document marking buttons in the Documents column. */
    .tvr-decision-menu { min-width:250px; padding:6px; }
    .tvr-decision-menu .dropdown-header { padding:6px 10px 5px; font-size:9.5px; letter-spacing:.1em; text-transform:uppercase; color:#94a3b8; }
    .tvr-decision-menu .dropdown-item { padding:7px 10px; border-radius:7px; font-size:12px; font-weight:600; white-space:normal; }
    .tvr-decision-menu .dropdown-item small { display:block; margin-top:1px; font-size:10.5px; font-weight:400; color:#94a3b8; }
    .tvr-locked { display:inline-flex; align-items:center; gap:6px; font-size:11.5px; color:#94a3b8; }

    /* ---------- Document cards (team-level documents) ----------------------- */
    .tvr-roster-table .tvr-btn { padding:5px 10px; font-size:11px; }
    .tvr-roster-table .dropdown-menu { min-width:185px; }
    .tvr-doc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:14px; padding:4px 24px 24px; }
    .tvr-doc-card { display:flex; flex-direction:column; gap:10px; padding:16px 18px; border:1px solid #eef2f7; border-radius:12px; background:#fff; transition:border-color .2s ease, box-shadow .2s ease; }
    .tvr-doc-card:hover { border-color:#dbe4f0; box-shadow:0 8px 20px rgba(9,23,60,.07); }
    .tvr-doc-head { display:flex; align-items:center; gap:10px; }
    .tvr-doc-icon { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; flex:0 0 auto; border-radius:10px; background:#eef2fb; color:#1e3a68; font-size:1rem; }
    .tvr-doc-name { font-size:13px; font-weight:600; color:var(--lp-ink,#09173c); line-height:1.3; }
    .tvr-doc-meta { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
    .tvr-doc-actions { display:flex; flex-wrap:wrap; gap:6px; margin-top:auto; }
    .tvr-doc-remarks { font-size:11px; color:#6b7280; line-height:1.5; }

    /* ---------- Empty states ------------------------------------------------ */
    .tvr-empty { padding:52px 24px; text-align:center; }
    .tvr-empty i { display:block; margin-bottom:12px; font-size:2.6rem; color:#d1d5db; }
    .tvr-empty p { margin:0 auto; max-width:46ch; font-size:13px; color:#9ca3af; line-height:1.6; }

    @media (max-width:767px) {
        .tvr-hero-crest { width:64px; height:64px; border-radius:14px; }
        .tvr-hero-copy .lp-hero-meta { gap:12px 20px; }
        .tvr-doc-grid { padding:4px 16px 20px; }
        .tvr-search input { min-width:0; width:100%; }
    }
</style>
