<?php
/**
 * Team registration page (representative) — page-specific styles.
 *
 * Builds on includes/application-page-styles.php (league-page / league-shell,
 * activity sections, flat tables). Only the pieces this page adds live here: the
 * page hero, status pills, correction banners, roster cells, document cards and
 * the resubmission modal with its progress panel.
 */
?>
<style>
    /* ---------- Layout ------------------------------------------------------ */
    .tr-team-view .league-shell { padding: 0; border: 0; background: transparent; box-shadow: none; }
    .tr-team-view .league-page { padding: 20px 24px 40px; }
    /* The shared shell stretches its activity sections to fill the viewport;
       this page stacks several of them, so they must stay content-sized. */
    .tr-team-view .league-shell > .activity-section { flex: 0 0 auto; }
    .tr-team-view .league-shell > .activity-section > .table-responsive { flex: 0 0 auto; }
    /* The shared .section-head is width:100% with 2px side margins, which pushes
       its right edge past the cards; align it with the panels instead. */
    .tr-team-view .section-head { width: auto; margin-left: 0; margin-right: 0; }

    .tv-crumbs { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; font-size: 12.5px; color: #75809a; }
    .tv-crumbs a { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 8px; border-radius: 8px; color: #09173c; font-weight: 600; text-decoration: none !important; transition: background .15s ease, color .15s ease; }
    .tv-crumbs a:hover { background: #e8edf7; color: #2f6fed; }
    .tv-crumbs .tv-crumb-sep { font-size: 12px; color: #94a3b8; }
    .tv-crumbs .tv-crumb-current { max-width: 52ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #09173c; font-weight: 600; }

    /* ---------- Status pills ------------------------------------------------ */
    .tv-status { display: inline-flex; align-items: center; gap: 7px; padding: 6px 13px; border-radius: 999px; font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
    .tv-status::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .tv-status.is-approved { background: #e7f8ef; color: #0f7a4e; }
    .tv-status.is-pending { background: #fff4e0; color: #a16207; }
    .tv-status.is-correction { background: #f1eafe; color: #6941c6; }
    .tv-status.is-rejected { background: #fde8ec; color: #b42335; }
    .tv-status.is-expired,
    .tv-status.is-cancelled,
    .tv-status.is-default { background: #eef1f6; color: #5b6577; }

    /* ---------- Hero --------------------------------------------------------- */
    .tv-hero {
        position: relative; isolation: isolate; overflow: hidden;
        display: grid; grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center; gap: 22px;
        padding: 24px 28px; margin-bottom: 20px;
        border-radius: 14px; color: #fff;
        background: radial-gradient(140% 190% at 100% 0%, #1d4390 0%, #0c2050 46%, #071129 100%);
        box-shadow: 0 22px 44px -30px rgba(7, 17, 41, .9);
    }
    .tv-hero::before {
        content: ""; position: absolute; z-index: -1; top: -170px; right: -110px;
        width: 420px; height: 420px; border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, .10); pointer-events: none;
    }
    .tv-hero-court { position: absolute; z-index: -1; top: -24px; bottom: -24px; left: 64%; width: 1px; background: rgba(255, 255, 255, .08); }
    .tv-hero-mark {
        display: flex; align-items: center; justify-content: center; flex: 0 0 auto;
        width: 78px; height: 78px; border-radius: 16px; overflow: hidden;
        background: rgba(255, 255, 255, .10); border: 1px solid rgba(255, 255, 255, .16);
        font-size: 1.7rem; color: #fff;
    }
    .tv-hero-mark img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .tv-hero-copy { min-width: 0; }
    .tv-hero-eyebrow {
        display: inline-flex; align-items: center; gap: 9px; margin-bottom: 8px;
        font-size: 9.5px; font-weight: 600; letter-spacing: .2em;
        text-transform: uppercase; color: rgba(255, 255, 255, .55);
    }
    .tv-hero-eyebrow::before { content: ""; width: 20px; height: 1px; background: rgba(255, 255, 255, .45); }
    .tv-hero-title {
        margin: 0 0 6px; color: #fff; font-size: clamp(1.5rem, 2.3vw, 2rem);
        font-weight: 600; line-height: 1.16; letter-spacing: -.015em; overflow-wrap: anywhere;
    }
    .tv-hero-sub { margin: 0; font-size: 13px; line-height: 1.55; color: rgba(255, 255, 255, .66); }
    .tv-hero-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 18px; margin-top: 10px; font-size: 11.5px; color: rgba(255, 255, 255, .72); }
    .tv-hero-meta span { display: inline-flex; align-items: center; gap: 6px; }
    .tv-hero-meta i { font-size: 12px; color: rgba(255, 255, 255, .45); }
    .tv-hero-meta .is-urgent,
    .tv-hero-meta .is-urgent i { color: #fecaca; }
    .tv-hero-aside { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
    .tv-hero-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
    .tv-hero-btn {
        display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px;
        border: 1px solid rgba(255, 255, 255, .28); border-radius: 8px;
        background: rgba(255, 255, 255, .08); color: #fff;
        font-size: 12px; font-weight: 600; line-height: 1.2; white-space: nowrap;
        text-decoration: none !important; cursor: pointer;
        transition: background .15s ease, border-color .15s ease, transform .15s ease;
    }
    .tv-hero-btn:hover { background: rgba(255, 255, 255, .16); border-color: rgba(255, 255, 255, .45); color: #fff; transform: translateY(-1px); }
    .tv-hero-btn.is-primary { background: #fff; border-color: #fff; color: #09173c; }
    .tv-hero-btn.is-primary:hover { background: #e9effa; border-color: #e9effa; color: #09173c; }
    .tv-hero-btn:focus-visible { outline: 2px solid #93b8ff; outline-offset: 3px; }

    /* ---------- Banners ----------------------------------------------------- */
    .tv-banner { display: flex; align-items: flex-start; gap: 14px; padding: 16px 18px; margin-bottom: 18px; border: 1px solid #dce4ef; border-radius: 6px; background: #fff; font-size: 12.5px; }
    .tv-banner-icon { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; flex: 0 0 38px; border-radius: 9px; background: #eef3fb; color: #245cbb; font-size: 1.05rem; }
    .tv-banner-body { min-width: 0; flex: 1 1 auto; }
    .tv-banner strong { display: block; font-size: 13px; font-weight: 700; color: #09173c; }
    .tv-banner p { margin: 3px 0 0; color: #5b6577; line-height: 1.55; }
    .tv-banner-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
    .tv-banner.is-warn { border-color: #f2dcae; background: #fffdf6; }
    .tv-banner.is-warn .tv-banner-icon { background: #fdf0d3; color: #b45309; }
    .tv-banner.is-info { border-color: #cfe0f2; background: #f7fbff; }
    .tv-banner.is-danger { border-color: #f3c9c9; background: #fffafa; }
    .tv-banner.is-danger .tv-banner-icon { background: #fde8ec; color: #b42335; }
    .tv-banner.is-muted { border-color: #e1e6ee; background: #f8fafc; }
    .tv-banner.is-muted .tv-banner-icon { background: #eef1f6; color: #5b6577; }

    /* ---------- Banner / inline actions ------------------------------------- */
    .tv-action { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border: 1px solid transparent; border-radius: 4px; font-size: 12px; font-weight: 600; line-height: 1.2; text-decoration: none !important; white-space: nowrap; cursor: pointer; transition: background .16s ease, border-color .16s ease, color .16s ease; }
    .tv-action-primary { background: #245cbb; border-color: #245cbb; color: #fff; }
    .tv-action-primary:hover { background: #173f87; border-color: #173f87; color: #fff; }
    .tv-action-ghost { background: #fff; border-color: #d6dfeb; color: #41506a; }
    .tv-action-ghost:hover { background: #edf2fc; border-color: #b9cef7; color: #245cbb; }
    .tv-action-danger { background: #fff; border-color: #f0c2c2; color: #b42335; }
    .tv-action-danger:hover { background: #b42335; border-color: #b42335; color: #fff; }

    .tv-deadline { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #fdf0d3; color: #92400e; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .tv-deadline.is-urgent { background: #fee2e2; color: #b42318; }
    .tv-remarks { margin-top: 10px; padding: 10px 12px; border-left: 3px solid #f0b429; border-radius: 4px; background: #fffbf0; color: #7c4a03; font-size: 12.5px; line-height: 1.55; }
    .tv-remarks strong { display: block; margin-bottom: 2px; color: #7c4a03; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; }

    /* ---------- Team facts -------------------------------------------------- */
    .tv-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px 22px; padding: 18px 22px 20px; }
    .tv-fact small { display: block; font-size: 10px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #8b95a8; }
    .tv-fact span { display: block; margin-top: 3px; font-size: 13px; font-weight: 500; color: #12233f; overflow-wrap: anywhere; }

    /* ---------- Roster cells ------------------------------------------------ */
    .tv-player { display: flex; align-items: center; gap: 10px; min-width: 180px; }
    .tv-avatar { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: 0 0 34px; border-radius: 50%; background: #eef3fb; color: #1a3a7c; font-size: 11.5px; font-weight: 700; letter-spacing: .04em; }
    .tv-player-name { font-weight: 600; color: #12233f; line-height: 1.3; text-transform: capitalize; }
    .tv-player-sub { font-size: 11px; color: #8b95a8; }
    .tv-cell-muted { color: #6b7688; font-size: 12.5px; white-space: nowrap; }
    .tv-cell-strong { color: #12233f; font-size: 12.5px; font-weight: 600; }
    .tv-role { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; background: #eef3fb; color: #1a3a7c; font-size: 10.5px; font-weight: 600; text-transform: capitalize; white-space: nowrap; }
    .tv-doc-link { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 600; color: #245cbb; text-decoration: none !important; white-space: nowrap; }
    .tv-doc-link:hover { color: #173f87; text-decoration: underline !important; }
    .tv-missing { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; color: #b42318; white-space: nowrap; }
    .tv-elig-notes { display: block; max-width: 32ch; margin-top: 4px; font-size: 11px; line-height: 1.45; color: #7b8798; }

    /* ---------- Team documents ---------------------------------------------- */
    .tv-doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; padding: 4px 22px 20px; }
    .tv-doc-card { display: flex; flex-direction: column; gap: 8px; padding: 13px 15px; border: 1px solid #e4e9f2; border-radius: 6px; background: #fff; }
    .tv-doc-card-head { display: flex; align-items: center; gap: 9px; }
    .tv-doc-card-icon { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; flex: 0 0 32px; border-radius: 8px; background: #eef3fb; color: #245cbb; font-size: .9rem; }
    .tv-doc-card-name { font-size: 12.5px; font-weight: 600; color: #09173c; line-height: 1.3; }
    .tv-doc-card-meta { font-size: 11px; color: #8b95a8; overflow-wrap: anywhere; }


    /* Between ~1000px and 1200px the status/actions cluster would squeeze the
       title block, so it drops onto its own row earlier than the shared shell. */
    @media (max-width: 1199.98px) {
        .tv-hero { grid-template-columns: auto minmax(0, 1fr); }
        .tv-hero-aside { grid-column: 1 / -1; flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: wrap; width: 100%; gap: 12px; }
        .tv-hero-actions { justify-content: flex-start; }
    }
    @media (max-width: 991.98px) {
        .tv-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575.98px) {
        .tr-team-view .league-page { padding: 14px 14px 32px; }
        .tv-facts { grid-template-columns: 1fr; }
        .tv-banner { flex-wrap: wrap; }
        .tv-doc-grid { padding: 4px 16px 16px; }
        .tv-hero { padding: 20px; gap: 14px; }
        .tv-hero-mark { width: 56px; height: 56px; border-radius: 12px; font-size: 1.25rem; }
        .tv-hero-title { font-size: 1.35rem; }
        .tv-hero-eyebrow { margin-bottom: 6px; }
        .tv-hero-aside { flex-direction: column; align-items: flex-start; }
        .tv-hero-actions { width: 100%; justify-content: flex-start; }
        .tv-hero-btn { flex: 0 1 auto; padding: 8px 11px; font-size: 11px; }
        .tv-hero-meta { font-size: 11px; gap: 4px 12px; }
        /* The banner and the KPI tile below both state the deadline. */
        .tv-hero-meta-deadline { display: none; }
        .tv-banner { padding: 14px; gap: 12px; font-size: 12px; }
        .tv-banner-icon { width: 34px; height: 34px; flex-basis: 34px; border-radius: 8px; font-size: .95rem; }
    }
</style>
