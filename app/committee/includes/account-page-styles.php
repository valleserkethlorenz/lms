<style>
.committee-account-page { font-variant-numeric:tabular-nums; }
.account-page { padding:20px 24px 40px; }
.account-shell { background:transparent; }
.account-eyebrow { display:block; font-size:9px; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:#75809a; }
.committee-account-page .section-eyebrow { display:block; font-size:9px; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:#75809a; }
.committee-account-page .section-title { margin:4px 0 0; font-size:16px; font-weight:600; color:#172742; line-height:1.3; }
.account-layout { display:grid; grid-template-columns:280px minmax(0,1fr); align-items:start; gap:22px; }
.account-sidebar { display:grid; gap:18px; }
.account-identity { padding:24px; background:#edf2fc; border:1px solid #d6dfed; border-top:3px solid #245cbb; border-radius:4px; }
.account-avatar { width:50px; height:50px; display:grid; place-items:center; border-radius:4px; background:#dce7fc; color:#245cbb; font-size:24px; margin-bottom:18px; }
.account-identity h3 { margin:8px 0 12px; font-size:20px; font-weight:600; color:#172742; overflow-wrap:anywhere; line-height:1.4; }
.account-role { display:inline-block; padding:5px 8px; border-radius:3px; font-size:11px; color:#245cbb; background:#dce7fc; }
.account-identity p { font-size:12px; color:#657b9e; overflow-wrap:anywhere; margin:14px 0 0; }
.account-help { padding:22px; background:#fff; border:1px solid #e0e6ef; border-radius:4px; }
.account-help h3 { color:#172742; font-size:13px; font-weight:600; line-height:1.6; margin:0 0 10px; }
.account-help h3 i { color:#245cbb; margin-right:5px; }
.account-help p { color:#75809a; font-size:12px; line-height:1.8; margin:0 0 16px; }
.account-help > div { display:flex; gap:9px; border-top:1px solid #e8edf4; padding-top:16px; font-size:11px; line-height:1.7; color:#657b9e; }
.account-help > div i { color:#16735e; font-size:16px; }
#committeeAccountForm { min-width:0; }
.committee-account-page .form-section { border:1px solid #dce4ef; border-radius:4px; background:#fff; margin-bottom:18px; overflow:hidden; box-shadow:none; }
.committee-account-page .form-section-header { display:flex; align-items:center; gap:12px; padding:20px 24px; border-bottom:1px solid #e4e9f2; }
.committee-account-page .form-section-icon { display:grid; place-items:center; flex-shrink:0; width:38px; height:38px; border-radius:4px; font-size:18px; }
.committee-account-page .form-section-icon.profile { background:#e9f0fc; color:#245cbb; }
.committee-account-page .form-section-icon.password { background:#e4f2ec; color:#16735e; }
.committee-account-page .form-section-sub { color:#75809a; font-size:11px; margin-top:3px; line-height:1.6; }
.committee-account-page .form-section-body { padding:24px; }
.acct-form-label { display:block; font-size:11px; font-weight:500; color:#53617a; margin-bottom:7px; }
.acct-form-label sup { position:static; margin-left:4px; font-size:11px; }
.acct-form-control { width:100%; min-height:43px; border:1px solid #d6dfeb; border-radius:4px; background:#fff; padding:10px 12px; font-size:13px; }
.acct-form-control:focus { outline:none; border-color:#2f6fed; box-shadow:0 0 0 3px rgba(47,111,237,.1); }
.acct-form-control::placeholder { color:#98a1b2; }
.acct-input-group { position:relative; }
.acct-input-icon { position:absolute; top:50%; left:12px; transform:translateY(-50%); color:#8993a5; font-size:14px; pointer-events:none; }
.acct-input-group .acct-form-control { padding-left:36px; }
.account-save-bar { display:flex; justify-content:space-between; align-items:center; gap:16px; padding:18px 22px; background:#fff; border:1px solid #dce4ef; border-radius:4px; }
.account-save-bar > span { color:#75809a; font-size:11px; }
.acct-save-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:1px solid #245cbb; background:#245cbb; color:#fff; border-radius:4px; padding:10px 18px; font-size:12px; font-weight:600; }
.acct-save-btn:hover { background:#173f87; border-color:#173f87; }
.committee-account-page button:focus-visible { outline:2px solid #2f6fed; outline-offset:3px; }
@media(max-width:991px) { .account-layout { grid-template-columns:1fr; } .account-sidebar { grid-template-columns:1fr 1fr; } }
@media(max-width:600px) {
.account-page { padding:14px; }
.account-sidebar { grid-template-columns:1fr; }
.committee-account-page .form-section-header,
.committee-account-page .form-section-body { padding:18px; }
.account-save-bar { padding:16px; flex-wrap:wrap; }
.acct-save-btn { width:100%; }
}
</style>
