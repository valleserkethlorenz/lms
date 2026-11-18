<style>
/* Profile and sign-in panels follow the organizer's flat competition desk. */
.organizer-accounts { --account-navy:#09173c; --account-line:#e4e9f2; }
.organizer-accounts .settings-card { height:100%; background:#fff; border:1px solid var(--account-line); border-radius:4px; overflow:hidden; }
.organizer-accounts .settings-card-header { display:flex; align-items:center; gap:14px; padding:22px 24px; border-bottom:1px solid var(--account-line); }
.organizer-accounts .settings-card-body { padding:24px; }
.organizer-accounts .settings-field-label { display:block; font-size:11px; font-weight:600; color:#596780; margin-bottom:7px; }
.organizer-accounts .settings-field-input { width:100%; background:#fff; border:1px solid #d6deea; border-radius:3px; padding:11px 12px; font:inherit; font-size:12px; color:#34415a; }
.organizer-accounts .settings-field-input:focus { outline:none; border-color:#2f6fed; box-shadow:0 0 0 2px #e6efff; }
.organizer-accounts .settings-card-body form { display:flex; flex-direction:column; }
.organizer-accounts .settings-save-btn,
.organizer-accounts .settings-pwd-btn { align-self:flex-start; display:inline-flex; align-items:center; justify-content:center; gap:8px; border:1px solid var(--account-navy); border-radius:3px; padding:11px 20px; margin-top:8px; background:var(--account-navy); color:#fff; font-size:12px; font-weight:600; cursor:pointer; }
.organizer-accounts .settings-save-btn:hover { background:#17356f; }
.organizer-accounts .settings-photo-field { display:flex; align-items:center; gap:16px; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--account-line); }
.organizer-accounts .settings-photo-preview { width:72px; height:72px; flex-shrink:0; display:grid; place-items:center; overflow:hidden; border:1px solid #d6deea; border-radius:4px; background:#edf2fc; color:#245cbb; font-size:28px; }
.organizer-accounts .settings-photo-preview img { width:100%; height:100%; object-fit:cover; display:block; }
.organizer-accounts .settings-photo-preview.has-image { background:#fff; }
.organizer-accounts .settings-photo-copy { min-width:0; }
.organizer-accounts .settings-photo-help { margin:2px 0 9px; font-size:11px; color:#75809a; line-height:1.6; overflow-wrap:anywhere; }
.organizer-accounts .settings-photo-help.is-warning { color:#a12c25; font-weight:600; }
.organizer-accounts .settings-photo-actions { display:flex; flex-wrap:wrap; gap:8px; }
.organizer-accounts .settings-photo-btn,
.organizer-accounts .settings-photo-remove { display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border-radius:3px; font-size:11px; font-weight:600; cursor:pointer; }
.organizer-accounts .settings-photo-btn { border:1px solid #245cbb; background:#245cbb; color:#fff; }
.organizer-accounts .settings-photo-btn:hover { background:#173f87; border-color:#173f87; }
.organizer-accounts .settings-photo-remove { border:1px solid #e1c4c1; background:#fff; color:#a12c25; }
.organizer-accounts .settings-photo-remove:hover { background:#fdebea; }
.organizer-accounts .settings-pwd-btn { color:#17356f; border-color:#b8c6dc; background:#f4f6fb; }
.organizer-accounts .settings-pwd-btn:hover { background:#e6efff; border-color:#8da6cf; }
.organizer-accounts .settings-save-btn:disabled,
.organizer-accounts .settings-pwd-btn:disabled { opacity:.6; cursor:wait; }
.organizer-accounts button:focus-visible { outline:2px solid #2f6fed; outline-offset:3px; }
.organizer-accounts .settings-alert { display:flex; align-items:center; gap:12px; border:1px solid; border-radius:3px; padding:14px 20px; margin-bottom:20px; font-size:12px; line-height:1.8; }
.organizer-accounts .settings-alert-success { background:#e4f4eb; color:#146c43; border-color:#b8dfc9; }
.organizer-accounts .settings-alert-danger { background:#fdebea; color:#a12c25; border-color:#edc1bd; }
@media(max-width:768px) {
    .organizer-accounts .settings-card-header,
    .organizer-accounts .settings-card-body { padding:20px 16px; }
    .organizer-accounts .settings-save-btn,
    .organizer-accounts .settings-pwd-btn { width:100%; }
}
@media(max-width:600px) {
    .organizer-accounts .settings-photo-field { flex-direction:column; align-items:flex-start; }
}
@media(prefers-reduced-motion:reduce) { .organizer-accounts .dash-animate { animation:none; } }
</style>
