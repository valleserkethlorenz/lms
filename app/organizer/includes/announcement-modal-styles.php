<?php
/**
 * Announcement composer + feed styling for the organizer's league overview.
 *
 * Scoped to #announcementModal so it layers on top of the shared
 * includes/league-view-styles.php modal treatment without touching the other
 * league modals.
 *
 * Usage: <?php include 'includes/announcement-modal-styles.php'; ?>
 */
?>
<style>
    /* ---------- Shell -------------------------------------------------------- */
    #announcementModal .modal-dialog { max-width:720px; }
    #announcementModal .modal-content { border-radius:16px; }
    #announcementModal .modal-header { padding:18px 22px; }
    .lp-am-head { display:flex; align-items:center; gap:12px; min-width:0; }
    .lp-am-head-icon { flex:0 0 auto; width:40px; height:40px; display:inline-flex; align-items:center; justify-content:center; border-radius:12px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.22); color:#fff; font-size:1.05rem; }
    #announcementModal .modal-title { font-size:1.02rem; font-weight:700; letter-spacing:.2px; }
    #announcementModal .modal-subtitle { max-width:46ch; font-size:11.5px; line-height:1.45; }
    #announcementModal .modal-body { padding:20px 22px 8px; }
    #announcementModal .modal-footer { padding:14px 22px; }

    /* ---------- Composer ----------------------------------------------------- */
    .lp-am-composer { margin-bottom:20px; padding:16px 18px 14px; border:1px solid #e4ebf6; border-radius:14px; background:#fff; box-shadow:0 1px 2px rgba(9,23,60,.04); transition:border-color .2s ease, box-shadow .2s ease; }
    .lp-am-composer:focus-within { border-color:#c7dcff; box-shadow:0 0 0 3px rgba(37,99,235,.08); }
    .lp-am-composer-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
    .lp-am-composer-title { display:inline-flex; align-items:center; gap:8px; font-size:12px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#334155; }
    .lp-am-composer-title i { color:#2563eb; font-size:13px; }
    .lp-am-hint { padding:3px 9px; border-radius:999px; background:#eef4ff; color:#3b5b8c; font-size:10.5px; font-weight:600; font-variant-numeric:tabular-nums; }
    .lp-am-hint.is-near-limit { background:#fef3c7; color:#92400e; }
    .lp-am-field { margin-bottom:12px; }
    .lp-am-field:last-of-type { margin-bottom:14px; }
    #announcementModal .lp-am-field .form-label { margin-bottom:6px; font-size:10.5px; font-weight:700; letter-spacing:.7px; text-transform:uppercase; color:#94a3b8; }
    #announcementModal .lp-am-field .form-control { padding:10px 13px; border-radius:10px; border:1.5px solid #e4ebf6; font-size:13px; line-height:1.5; }
    #announcementModal .lp-am-field textarea.form-control { min-height:104px; resize:vertical; }
    #announcementModal .lp-am-field .form-control::placeholder { color:#a6b2c4; }
    #announcementModal .lp-am-field .form-control.is-invalid { border-color:#f0a8ad; background:#fffafa; }
    #announcementModal .lp-am-field .form-control.is-invalid:focus { box-shadow:0 0 0 3px rgba(220,53,69,.1); }
    #announcementModal .lp-am-field .invalid-feedback { display:none; margin-top:5px; font-size:11.5px; }
    #announcementModal .lp-am-field .form-control.is-invalid ~ .invalid-feedback { display:block; }
    .lp-am-composer-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .lp-am-note { display:inline-flex; align-items:center; gap:6px; font-size:11px; line-height:1.4; color:#94a3b8; }
    .lp-am-note i { color:#2563eb; }
    .lp-am-submit { display:inline-flex; align-items:center; gap:8px; margin-left:auto; padding:9px 20px; border:none; border-radius:10px; background:linear-gradient(135deg,#09173c 0%,#2563eb 100%); color:#fff; font-size:12.5px; font-weight:600; letter-spacing:.2px; box-shadow:0 6px 16px rgba(37,99,235,.24); cursor:pointer; transition:transform .15s ease, box-shadow .15s ease, opacity .15s ease; }
    .lp-am-submit:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 9px 22px rgba(37,99,235,.3); }
    .lp-am-submit:focus-visible { outline:3px solid rgba(37,99,235,.32); outline-offset:2px; }
    .lp-am-submit:disabled { opacity:.6; cursor:progress; box-shadow:none; transform:none; }
    /* ---------- Feed --------------------------------------------------------- */
    .lp-am-list-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:0 0 10px; padding-top:16px; border-top:1px solid #eef2f7; }
    .lp-am-list-title { display:inline-flex; align-items:center; gap:7px; font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:#64748b; }
    .lp-am-list-title i { color:#94a3b8; font-size:12.5px; }
    .lp-am-count { padding:3px 10px; border-radius:999px; background:#eef2f7; color:#475569; font-size:10.5px; font-weight:700; letter-spacing:.3px; }
    .lp-am-list { display:flex; flex-direction:column; gap:10px; }
    .lp-am-item { display:flex; gap:12px; padding:13px 15px; border:1px solid #e8eef7; border-left:3px solid #2563eb; border-radius:12px; background:#fff; transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
    .lp-am-item:hover { border-color:#cddcf5; border-left-color:#1d4ed8; box-shadow:0 8px 20px rgba(9,23,60,.07); transform:translateY(-1px); }
    .lp-am-item-icon { flex:0 0 auto; width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; background:#eef4ff; color:#2563eb; font-size:.95rem; }
    .lp-am-item-body { flex:1 1 auto; min-width:0; }
    .lp-am-item-head { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .lp-am-item-title { margin:0; font-size:13.5px; font-weight:700; line-height:1.4; color:#0f172a; word-break:break-word; }
    .lp-am-item-text { margin:5px 0 9px; font-size:12.5px; line-height:1.6; color:#475569; word-break:break-word; }
    .lp-am-item-time { display:inline-flex; align-items:center; gap:6px; font-size:10.5px; font-weight:600; letter-spacing:.3px; color:#94a3b8; }
    .lp-am-item-time i { font-size:11px; }
    .lp-am-delete { flex:0 0 auto; width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; padding:0; border:1px solid transparent; border-radius:8px; background:transparent; color:#9aa7ba; font-size:12.5px; line-height:1; opacity:.65; cursor:pointer; transition:opacity .15s ease, background .15s ease, color .15s ease, border-color .15s ease; }
    .lp-am-item:hover .lp-am-delete, .lp-am-delete:focus-visible { opacity:1; }
    .lp-am-delete:hover { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
    .lp-am-delete:focus-visible { outline:2px solid rgba(220,53,69,.35); outline-offset:1px; }

    /* ---------- Empty state & loading skeleton ------------------------------- */
    .lp-am-empty { padding:34px 22px; border:1px dashed #d7e2f2; border-radius:14px; background:linear-gradient(180deg,#fbfdff 0%,#f5f9ff 100%); text-align:center; }
    .lp-am-empty-icon { display:inline-flex; align-items:center; justify-content:center; width:48px; height:48px; margin-bottom:10px; border-radius:50%; background:#eef4ff; color:#8fb4ef; font-size:1.3rem; }
    .lp-am-empty h6 { margin:0 0 4px; font-size:13.5px; font-weight:700; color:#1e293b; }
    .lp-am-empty p { margin:0; font-size:12px; line-height:1.5; color:#94a3b8; }
    .lp-am-skeleton { display:flex; flex-direction:column; gap:10px; }
    .lp-am-skeleton span { display:block; height:62px; border-radius:12px; background:linear-gradient(90deg,#eef2f8 25%,#f7fafd 50%,#eef2f8 75%); background-size:200% 100%; animation:lpAmShimmer 1.3s ease-in-out infinite; }
    @keyframes lpAmShimmer { 0% { background-position:200% 0; } 100% { background-position:-200% 0; } }

    /* ---------- Footer ------------------------------------------------------- */
    .lp-am-btn-ghost { padding:8px 20px; border:1.5px solid #e2e8f0; border-radius:9px; background:#fff; color:#334155; font-size:12.5px; font-weight:600; cursor:pointer; transition:border-color .15s ease, background .15s ease, color .15s ease; }
    .lp-am-btn-ghost:hover { border-color:#c7dcff; background:#f6f9ff; color:#1d4ed8; }

    /* ---------- Responsive --------------------------------------------------- */
    @media (max-width:575.98px) {
        #announcementModal .modal-body { padding:16px 16px 6px; }
        .lp-am-composer { padding:14px 15px 13px; }
        .lp-am-composer-foot { flex-direction:column; align-items:stretch; }
        .lp-am-note { order:2; }
        .lp-am-submit { order:1; width:100%; justify-content:center; margin-left:0; }
        .lp-am-item { padding:12px 13px; }
    }
    @media (prefers-reduced-motion:reduce) {
        .lp-am-item, .lp-am-submit, .lp-am-delete, .lp-am-btn-ghost, .lp-am-composer { transition:none; }
        .lp-am-item:hover, .lp-am-submit:hover:not(:disabled) { transform:none; }
        .lp-am-skeleton span { animation:none; }
    }
</style>
