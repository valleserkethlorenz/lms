<?php
/**
 * Correction resubmission page styles.
 *
 * The page reuses includes/registration-page-styles.php for the shell (reg-page
 * / reg-shell), the numbered sections, the roster table and the sticky action
 * bar. Only what this page adds lives here: the breadcrumb above the hero, the
 * "what can change" chips, locked vs. editable roster rows, organizer remark
 * notes, document slots and the upload progress panel.
 *
 * Usage: include 'includes/resubmit-page-styles.php';
 */
?>
<style>
    /* ---------- Breadcrumb --------------------------------------------------- */
    .reg-page .resubmit-crumbs { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; font-size: 12.5px; color: #75809a; }
    .reg-page .resubmit-crumbs a { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 8px; border-radius: 8px; color: #09173c; font-weight: 600; text-decoration: none !important; transition: background .15s ease, color .15s ease; }
    .reg-page .resubmit-crumbs a:hover { background: #e8edf7; color: #2f6fed; }
    .reg-page .resubmit-crumbs .resubmit-crumb-sep { font-size: 12px; color: #94a3b8; }
    .reg-page .resubmit-crumbs .resubmit-crumb-current { max-width: 52ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #09173c; font-weight: 600; }

    /* ---------- What can change --------------------------------------------- */
    .reg-page .resubmit-scope { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 10px; }
    .reg-page .resubmit-scope-item { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border: 1px solid #d5e3f2; border-radius: 999px; background: #fff; color: #35516e; font-size: .7rem; font-weight: 650; }
    .reg-page .resubmit-scope-item i { color: #2f6fed; }
    .reg-page .resubmit-scope-item.is-locked { border-color: #e2e8f0; background: #f6f8fb; color: #6b7688; }
    .reg-page .resubmit-scope-item.is-locked i { color: #94a3b8; }
    .reg-page .resubmit-remarks { margin-top: 12px; padding: 10px 12px; border-left: 3px solid #e4a11b; border-radius: 6px; background: #fff9e9; color: #7c4a03; font-size: .76rem; line-height: 1.55; }
    .reg-page .resubmit-remarks strong { display: block; margin-bottom: 2px; color: #7c4a03; font-size: .64rem; letter-spacing: .06em; text-transform: uppercase; }
    .reg-page .resubmit-remarks p { margin: 0; }

    /* ---------- Roster rows -------------------------------------------------- */
    .reg-page .roster-table tbody tr.player-edit-row.is-locked > td { background: #f8fafc; }
    .reg-page .roster-table tbody tr.player-edit-row.is-editable > td { background: #fffdf4; }
    .reg-page .player-edit-row .form-control[readonly],
    .reg-page .player-edit-row .form-control:disabled { background: #f1f4f8; border-color: #e2e8f0; color: #5b6577; }
    .reg-page .roster-table tbody tr.correction-note > td { padding: 0 0 10px; border: 0; background: transparent; }
    .reg-page .correction-note .alert { margin: 0; padding: 9px 12px; border: 1px solid #f0cf84; border-left: 4px solid #e4a11b; border-radius: 6px; background: #fff9e9; color: #664b0b; font-size: .74rem; line-height: 1.5; }
    .reg-page .roster-table tbody tr.correction-note + tr.player-edit-row > td { border-top: 0; }

    /* ---------- Document slots ---------------------------------------------- */
    .reg-page .doc-slots { display: flex; flex-direction: column; gap: 7px; min-width: 210px; }
    .reg-page .doc-slot-name { font-size: .64rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #53617a; }
    .reg-page .doc-slot-current { display: flex; align-items: center; gap: 5px; font-size: .7rem; color: #0f7a4e; overflow-wrap: anywhere; }
    .reg-page .doc-slot-missing { display: flex; align-items: center; gap: 5px; font-size: .7rem; font-weight: 700; color: #b42318; }
    .reg-page .locked-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; background: #eef2f7; color: #5b6577; font-size: .64rem; font-weight: 700; white-space: nowrap; }
    .reg-page .resubmit-remove { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; padding: 0; }

    /* ---------- Checklist + deadline ---------------------------------------- */
    .reg-page .resubmit-checklist { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
    .reg-page .resubmit-checklist li { display: flex; align-items: flex-start; gap: 9px; color: #40536b; font-size: .78rem; line-height: 1.55; }
    .reg-page .resubmit-checklist li > i { margin-top: 2px; flex: 0 0 auto; color: #0f7a4e; }
    .reg-page .resubmit-checklist li.is-caution > i { color: #b45309; }
    .reg-page .resubmit-deadline { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 999px; background: #fdf0d3; color: #92400e; font-size: .7rem; font-weight: 700; }
    .reg-page .resubmit-deadline.is-urgent { background: #fee2e2; color: #b42318; }
    .reg-page .resubmit-count { color: #68788d; font-size: .72rem; font-weight: 600; }

    /* ---------- Upload progress panel --------------------------------------- */
    .reg-page .resubmit-progress { display: none; margin-top: 18px; padding: 15px 18px; border: 1px solid #cfe0f2; border-radius: 8px; background: #f4f9ff; }
    .reg-page .resubmit-progress.is-active { display: block; }
    .reg-page .resubmit-progress-head { display: flex; align-items: center; gap: 9px; color: #123f70; font-size: .82rem; font-weight: 700; }
    .reg-page .resubmit-progress-head .spinner-border { width: 15px; height: 15px; border-width: 2px; }
    .reg-page .resubmit-progress-bar { position: relative; height: 6px; margin-top: 10px; border-radius: 999px; background: #dbe7f6; overflow: hidden; }
    .reg-page .resubmit-progress-bar span { position: absolute; top: 0; bottom: 0; width: 38%; border-radius: 999px; background: linear-gradient(90deg, #245cbb, #5b8def); animation: resubmitProgress 1.25s ease-in-out infinite; }
    .reg-page .resubmit-progress-note { margin: 9px 0 0; color: #5b6577; font-size: .72rem; line-height: 1.5; }
    @keyframes resubmitProgress { 0% { left: -40%; } 100% { left: 100%; } }
    @media (prefers-reduced-motion: reduce) { .reg-page .resubmit-progress-bar span { animation: none; left: 0; width: 100%; opacity: .5; } }
</style>
