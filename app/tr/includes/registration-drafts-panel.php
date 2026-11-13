<?php
/**
 * "Unfinished registrations" panel.
 *
 * Drafts live in the browser (localStorage + IndexedDB), so the server can
 * never know about them. This component renders an empty, hidden container and
 * a small script that fills it from the drafts found on this device.
 *
 * Expects $userId to be set (every TR page already defines it).
 *
 * Usage: include 'includes/registration-drafts-panel.php';
 */
$draftPanelUserId = (int) ($userId ?? 0);
if ($draftPanelUserId <= 0) {
    return;
}
?>
<style>
    .tr-drafts { margin:18px 0 22px;padding:18px 20px;border:1px solid #dbe3ee;border-left:4px solid #b26a12;border-radius:6px;background:#fff;box-shadow:0 6px 20px rgba(16,24,40,.06); }
    .tr-drafts[hidden] { display:none !important; }
    .tr-drafts-head { display:flex;align-items:center;gap:12px;margin-bottom:14px; }
    .tr-drafts-icon { display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;width:38px;height:38px;border-radius:8px;background:#fff4e2;color:#b26a12;font-size:1.05rem; }
    .tr-drafts-title { margin:0;font-size:.95rem;font-weight:700;color:#09173c;letter-spacing:.2px; }
    .tr-drafts-sub { margin:2px 0 0;font-size:.75rem;color:#6b7a90; }
    .tr-drafts-list { display:flex;flex-direction:column;gap:10px;margin:0;padding:0;list-style:none; }
    .tr-draft { display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 14px;border:1px solid #e6ecf4;border-radius:6px;background:#fafcff; }
    .tr-draft-copy { display:flex;flex-direction:column;gap:3px;min-width:0;flex:1 1 260px; }
    .tr-draft-name { display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.86rem;font-weight:700;color:#09173c;text-decoration:none; }
    .tr-draft-name:hover { color:#2f6fed;text-decoration:underline; }
    .tr-draft-meta { font-size:.72rem;color:#6b7a90; }
    .tr-draft-actions { display:inline-flex;align-items:center;gap:8px;margin-left:auto; }
    .tr-draft-resume { display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid #09173c;border-radius:4px;background:#09173c;color:#fff;font-size:.75rem;font-weight:600;text-decoration:none;transition:background .18s ease; }
    .tr-draft-resume:hover { background:#123f6b;color:#fff; }
    .tr-draft-discard { padding:7px 12px;border:1px solid #e3c9cb;border-radius:4px;background:#fff5f6;color:#b42332;font-size:.75rem;font-weight:600;transition:background .18s ease; }
    .tr-draft-discard:hover { background:#fde8ec; }
    @media (max-width:575.98px) {
        .tr-draft-actions { width:100%;margin-left:0;justify-content:flex-start; }
    }
</style>
<section id="trDraftPanel" class="tr-drafts" aria-label="Unfinished registrations" hidden>
    <div class="tr-drafts-head">
        <span class="tr-drafts-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
        <div>
            <h2 class="tr-drafts-title">Unfinished registrations</h2>
            <p class="tr-drafts-sub">Saved on this device. Resume a team you started earlier.</p>
        </div>
    </div>
    <ul class="tr-drafts-list" id="trDraftList"></ul>
</section>
<script>
window.LMS_REGISTRATION_DRAFTS = {
    userId: <?= $draftPanelUserId ?>,
    resumeUrl: 'register_team.php',
    panelId: 'trDraftPanel',
    listId: 'trDraftList'
};
</script>
<script src="js/registration-drafts.js"></script>
