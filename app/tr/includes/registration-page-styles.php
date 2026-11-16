<?php
/**
 * Team registration page styles.
 *
 * Moved from components/modal/registeam.php (the registration form is a page
 * now). Every rule is scoped under `.reg-page` so the rest of the team
 * representative portal is untouched.
 *
 * Usage: include 'includes/registration-page-styles.php';
 */
?>
<style>
    .reg-page { --reg-navy:#123f6b;--reg-blue:#2f6fed;--reg-soft:#eef5fc;--reg-border:#d8e3ef;--reg-ink:#182d46;--reg-muted:#68788d;
        padding:20px 24px 45px;display:flex;flex-direction:column;min-height:calc(100vh - 70px); }
    .reg-page .reg-shell { padding:24px;border:1px solid #dce4ed;border-radius:4px;background:#fff;box-shadow:0 8px 24px rgba(16,24,40,.06);flex:1;display:flex;flex-direction:column;min-width:0; }
    .reg-page .reg-shell > .page-header { flex-shrink:0; }
    .reg-page .reg-body { padding:22px;border:1px solid var(--reg-border);border-radius:6px;background:#f4f7fb;margin-top:18px; }
    .reg-page .requirements-banner { display:flex;align-items:flex-start;gap:14px;margin-bottom:18px;padding:16px 18px;border:1px solid #cfe0f2;border-radius:8px;background:linear-gradient(105deg,#edf6ff,#fff);color:var(--reg-ink); }
    .reg-page .requirements-banner-icon { display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;width:38px;height:38px;border-radius:8px;background:#d9eaff;color:var(--reg-blue);font-size:1.05rem; }
    .reg-page .requirements-title { margin:0 0 5px;font-size:.88rem;font-weight:750; }
    .reg-page .requirements-copy { margin:0;color:var(--reg-muted);font-size:.78rem;line-height:1.55; }
    .reg-page .requirement-chip { display:inline-flex;align-items:center;margin:5px 5px 0 0;padding:5px 9px;border:1px solid #d5e3f2;border-radius:999px;background:#fff;color:#35516e;font-size:.7rem;font-weight:650; }
    .reg-page .registration-section { width:100%;padding:20px;border:1px solid var(--reg-border);border-radius:8px;background:#fff;box-shadow:0 7px 22px rgba(28,57,90,.06); }
    .reg-page .registration-section + .registration-section { margin-top:18px; }
    .reg-page .section-heading { display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:17px;padding-bottom:13px;border-bottom:1px solid #e7edf4; }
    .reg-page .section-heading-main { display:flex;align-items:center;gap:11px; }
    .reg-page .section-number { display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,var(--reg-navy),var(--reg-blue));color:#fff;font-size:.82rem;font-weight:800; }
    .reg-page .section-heading h6 { margin:0;color:var(--reg-ink);font-size:.95rem;font-weight:750; }
    .reg-page .section-heading p { margin:2px 0 0;color:var(--reg-muted);font-size:.72rem; }
    .reg-page .form-label { margin-bottom:6px;color:#344a63;font-size:.78rem;font-weight:700; }
    .reg-page .required-marker { margin-left:2px;color:#dc3545;font-weight:800; }
    .reg-page .form-control,.reg-page .form-select { min-height:42px;border-color:#cdd9e6;border-radius:6px;color:var(--reg-ink);font-size:.8rem; }
    .reg-page .form-control:focus,.reg-page .form-select:focus { border-color:#6e9eef;box-shadow:0 0 0 .18rem rgba(47,111,237,.12); }
    .reg-page .form-text { color:#7a899b;font-size:.68rem; }
    .reg-page .roster-table-wrap { width:100%;border:1px solid #d4e0ec;border-radius:8px;background:#fff; }
    .reg-page .roster-table { min-width:960px;margin-bottom:0;table-layout:auto; }
    .reg-page .roster-table thead th { position:sticky;top:0;z-index:2;padding:12px 10px;border-color:#dce6f0;background:#eaf2f9;color:#294660;white-space:nowrap;font-size:.72rem;text-transform:uppercase;letter-spacing:.35px; }
    .reg-page .roster-table td { padding:10px;border-color:#e4eaf1;vertical-align:top;background:#fff; }
    .reg-page .roster-table tbody tr:nth-child(even) td { background:#fbfdff; }
    .reg-page .roster-table input,.reg-page .roster-table select { min-width:105px;min-height:38px;font-size:.75rem; }
    .reg-page .roster-scroll-hint { display:none;color:#6c7d91;font-size:.72rem; }
    .reg-page #validationSummary { margin:0 0 14px !important;border:1px solid #f0cf84;border-left:4px solid #e4a11b;border-radius:6px;background:#fff9e9;color:#664b0b;font-size:.76rem; }
    .reg-page #addPlayerButton { border-color:#d2e1f0;background:#edf5ff;color:var(--reg-navy); }
    .reg-page #addPlayerButton:hover { background:#dcecff; }
    .reg-page #playerRowCount { padding:5px 9px;border-radius:999px;background:#eef2f7;color:#65758a !important;font-size:.68rem; }
    .reg-page .remove-player-button { display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;padding:0;border:1px solid #f1c7cb;border-radius:6px;background:#fff5f6;color:#c73543; }
    .reg-page .remove-player-button:hover { border-color:#dc3545;background:#dc3545;color:#fff; }
    .reg-page .file-selection-feedback { display:block;max-width:100%;margin-top:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.72rem; }
    .reg-page .file-selection-feedback.invalid { color:#dc3545;font-weight:700; }
    .reg-page .document-quality-failed { border-color:#dc3545 !important;background-color:#fff5f6 !important;box-shadow:0 0 0 .2rem rgba(220,53,69,.18) !important; }
    .reg-page .player-field-invalid { border-color:#dc3545 !important;background-color:#fff5f6 !important;box-shadow:0 0 0 .2rem rgba(220,53,69,.18) !important; }
    .reg-page .document-quality-block-failed { padding:8px;border:2px solid #dc3545;border-radius:6px;background:#fff5f6; }
    .reg-page .document-quality-message { display:block;margin-top:6px;color:#b42332;font-size:.7rem;font-weight:700;line-height:1.35; }

    /* Sticky action bar replaces the modal footer */
    .reg-page .reg-actionbar { position:sticky;bottom:0;z-index:6;display:flex;flex-wrap:wrap;gap:9px;align-items:center;margin-top:18px;padding:15px 22px;border:1px solid #dce5ef;border-radius:6px;background:#fff;box-shadow:0 -6px 18px rgba(31,55,82,.08); }
    .reg-page .reg-actionbar .btn { min-height:40px;padding:8px 16px;border-radius:6px;font-size:.78rem;font-weight:700; }
    .reg-page .reg-actionbar-meta { display:flex;flex-direction:column;gap:2px;margin-right:auto;font-size:.72rem;color:#68788d; }
    .reg-page #draftStatus { font-weight:600;color:#2f6fed; }
    .reg-page #draftStatus.is-empty { color:#98a4b4; }
    .reg-page #discardDraftBtn { padding:0;font-size:.72rem;vertical-align:baseline; }

    /* Draft restore banner + re-attach checklist */
    .reg-page #draftRestoreBanner { border:1px solid #b9d8f5;border-left:4px solid #2f6fed;border-radius:6px;background:#f2f8ff;color:#1d3c5c; }
    .reg-page #draftFileChecklistList { padding-left:18px; }
    .reg-page #draftFileChecklistList li { margin-top:2px; }
    .reg-page .draft-file-missing { border-color:#e4a11b !important;background-color:#fffaf0 !important; }
    .reg-page .draft-file-note { display:block;margin-top:5px;color:#8a6100;font-size:.7rem;font-weight:700;line-height:1.35; }
    .reg-page .draft-file-restored { color:#0f7a4e;font-weight:700; }

    /* "Registration unavailable" panel */
    .reg-page .reg-state { display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:44px 24px;border:1px solid var(--reg-border);border-radius:8px;background:#fff;text-align:center; }
    .reg-page .reg-state-icon { display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;border-radius:50%;background:#eef5fc;color:var(--reg-blue);font-size:1.5rem; }
    .reg-page .reg-state h2 { margin:0;font-size:1.05rem;font-weight:750;color:var(--reg-ink); }
    .reg-page .reg-state p { max-width:520px;margin:0;color:var(--reg-muted);font-size:.82rem;line-height:1.6; }
    .reg-page .reg-state-actions { display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:6px; }
    .reg-page .reg-state-actions .btn { border-radius:6px;font-size:.78rem;font-weight:700; }

    /* League hero — the same banner the public league pages render, so it sits
       outside the white .reg-shell card instead of inside it. The breadcrumb
       above it comes from includes/league-view-styles.php (the organizer-style
       trail the league pages share), so nothing needs overriding here. */
    .reg-page > .reg-hero { flex:0 0 auto; }
    .reg-page > .reg-hero .lp-crumbs { margin-bottom:12px; }
    .reg-page > .reg-hero .lp-hero { margin-bottom:18px; }

    @media (max-width: 991.98px) {
        .reg-page .roster-scroll-hint { display: block; }
        .reg-page .reg-actionbar { position: sticky; }
    }

    /* ---------- Phones: roster rows become labelled cards -------------------- */
    /* The roster is a 7-column table on desktop. On a phone the required
       document column sat ~900px to the right, so a representative could fill
       the visible fields, tap Submit, and be blocked by an off-screen required
       input with no message they could see. Each row is now a card whose every
       field is labelled, so nothing is hidden behind a horizontal swipe. */
    @media (max-width: 767.98px) {
        .reg-page .roster-scroll-hint { display: none; }
        .reg-page .roster-table-wrap { border: 0; background: transparent; overflow: visible; }
        .reg-page #playerTable { min-width: 0; display: block; }
        .reg-page #playerTable thead { display: none; }
        .reg-page #playerTable tbody { display: block; }
        .reg-page #playerTable tr {
            display: block; margin-bottom: 12px; padding: 12px 14px;
            border: 1px solid #d4e0ec; border-radius: 10px; background: #fff;
            box-shadow: 0 4px 14px rgba(28, 57, 90, .06);
        }
        .reg-page #playerTable td {
            display: grid; grid-template-columns: minmax(92px, 34%) minmax(0, 1fr);
            gap: 10px; align-items: center;
            padding: 6px 0; border: 0; background: transparent !important;
        }
        .reg-page #playerTable td::before {
            content: attr(data-label);
            color: #53617a; font-size: .68rem; font-weight: 700;
            letter-spacing: .04em; text-transform: uppercase; line-height: 1.35;
        }
        .reg-page #playerTable td[data-label=""]::before { content: none; }
        .reg-page #playerTable td[data-label=""] {
            display: flex; justify-content: flex-end; padding-top: 10px;
        }
        .reg-page #playerTable input,
        .reg-page #playerTable select { min-width: 0; width: 100%; }
    }

    @media (max-width: 575.98px) {
        .reg-page { padding:14px; }
        .reg-page .reg-shell { padding:14px; }
        .reg-page .reg-body { padding:14px; }
        .reg-page .registration-section { padding:15px; }
        .reg-page .requirements-banner { padding:14px; }
        .reg-page .section-heading { align-items:flex-start;flex-direction:column; }
        .reg-page .reg-actionbar {
            flex-direction:column;align-items:stretch;
            position:sticky;bottom:0;
            padding-bottom:calc(15px + env(safe-area-inset-bottom));
        }
        .reg-page .reg-actionbar-meta { margin-right:0; }

        /* iOS Safari zooms into any focused control smaller than 16px, which
           pushed the rest of the row — and every validation message — off the
           visible area. */
        .reg-page .form-control,
        .reg-page .form-select,
        .reg-page .roster-table input,
        .reg-page .roster-table select { font-size:16px; }
        .reg-page .form-control,
        .reg-page .form-select { min-height:46px; }
        .reg-page .roster-table input,
        .reg-page .roster-table select { min-height:44px; }
        .reg-page .remove-player-button { width:44px; height:44px; }
    }
</style>
