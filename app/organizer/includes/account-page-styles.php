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
@media(prefers-reduced-motion:reduce) { .organizer-accounts .dash-animate { animation:none; } }
</style>
