<style>
.settings-layout { display:grid; grid-template-columns:290px minmax(0,1fr); gap:22px; align-items:start; margin-bottom:28px; }
.settings-sidebar { display:grid; gap:18px; }
.settings-identity { padding:24px; border:1px solid #d6dfed; border-top:3px solid #245cbb; border-radius:4px; background:#edf2fc; }
.settings-avatar { width:48px; height:48px; display:grid; place-items:center; background:#dce7fc; color:#245cbb; border-radius:4px; font-size:23px; margin-bottom:18px; }
.settings-identity h3 { margin:7px 0 12px; color:#172742; font-size:20px; font-weight:600; overflow-wrap:anywhere; }
.settings-role { display:inline-flex; align-items:center; gap:6px; background:#dce7fc; color:#245cbb; padding:5px 8px; border-radius:3px; font-size:11px; }
.settings-guide { background:#fff; border:1px solid #e0e6ef; border-radius:4px; padding:22px; }
.settings-guide h3 { color:#172742; font-size:13px; font-weight:600; margin:0 0 8px; }
.settings-guide p { color:#75809a; font-size:12px; line-height:1.6; margin:0 0 14px; }
.settings-guide ul { padding-left:17px; color:#53617a; font-size:12px; line-height:1.7; margin-bottom:18px; }
.settings-guide li + li { margin-top:7px; }
.settings-tip { display:flex; align-items:flex-start; gap:9px; padding-top:16px; border-top:1px solid #e8edf4; color:#657b9e; font-size:11px; line-height:1.6; }
.settings-tip i { color:#245cbb; font-size:15px; }
.settings-panel { border:1px solid #dce4ef; border-radius:4px; background:#fff; overflow:hidden; }
.settings-panel-heading { display:flex; align-items:flex-start; gap:14px; padding:24px 28px; border-bottom:1px solid #e4e9f2; }
.settings-section-icon { display:grid; place-items:center; width:38px; height:38px; flex-shrink:0; border-radius:4px; background:#e4f2ec; color:#16735e; font-size:18px; }
.settings-panel-heading h3 { font-size:19px; color:#172742; font-weight:600; margin:5px 0 7px; }
.settings-panel-heading p { margin:0; color:#75809a; font-size:12px; line-height:1.6; }
.settings-password-form { padding:24px 28px 0; }
.admin-settings .form-label { font-size:12px; color:#53617a; margin-bottom:8px; }
.settings-required { color:#b0443d; }
.password-field { position:relative; }
.admin-settings .password-field .form-control { min-height:44px; padding:10px 48px 10px 12px; border:1px solid #d6dfeb; border-radius:4px; font-size:14px; }
.admin-settings .password-field .form-control:focus { border-color:#2f6fed; box-shadow:0 0 0 3px rgba(47,111,237,.1); }
.password-toggle { position:absolute; top:50%; right:6px; transform:translateY(-50%); display:grid; place-items:center; width:34px; height:34px; padding:0; border:0; border-radius:3px; background:transparent; color:#75809a; }
.password-toggle:hover { background:#edf2fc; color:#245cbb; }
.admin-settings .form-text { font-size:11px; color:#75809a; margin-top:8px; line-height:1.6; }
.settings-form-footer { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; background:#f8faff; border-top:1px solid #e4e9f2; margin:4px -28px 0; padding:20px 28px; }
.settings-form-footer > span { color:#75809a; font-size:11px; }
.admin-settings .settings-save { background:#245cbb; color:#fff; border:1px solid #245cbb; border-radius:4px; padding:10px 16px; font-size:12px; font-weight:600; }
.admin-settings .settings-save:hover { background:#173f87; border-color:#173f87; }
@media(min-width:1400px) { .settings-layout { grid-template-columns:320px minmax(0,1fr); } }
@media(max-width:991px) { .settings-layout { grid-template-columns:1fr; } .settings-sidebar { grid-template-columns:1fr 1fr; } }
@media(max-width:600px) {
.settings-sidebar { grid-template-columns:1fr; }
.settings-panel-heading { padding:20px; }
.settings-password-form { padding:20px 20px 0; }
.settings-form-footer { margin:4px -20px 0; padding:18px 20px; }
.admin-settings .settings-save { width:100%; }
}
.settings-guide .password-checklist { list-style:none; padding:0; }
.password-checklist li { display:flex; align-items:center; gap:9px; color:#75809a; }
.password-checklist li > i { font-size:14px; flex-shrink:0; }
.password-checklist li.is-met { color:#167345; }
.settings-guide #passwordProgress { padding:10px 12px; background:#f0f4fa; border-radius:3px; font-size:11px; color:#53617a; margin-bottom:16px; }
</style>
