<style>
/* Account directory: dashboard palette, flat surfaces, compact geometry. */
.admin-users { color:#0d1526; font-variant-numeric:tabular-nums; }
.admin-users .users-shell { background:transparent; border:0; box-shadow:none; }
.admin-users .users-shell > .card-body { padding:4px 8px 24px; }
.users-filter-bar,.filter-group { display:flex; align-items:center; flex-wrap:wrap; gap:12px; }
.users-filter-bar { justify-content:space-between; margin-bottom:20px; padding:16px; background:#fff; border:1px solid #e4e9f2; border-radius:4px; }
.admin-users .users-create-account-btn { background:#09173c; border-color:#09173c; color:#fff; }
.admin-users .users-create-account-btn:hover { background:#17356f; border-color:#17356f; color:#fff; }
.admin-users .users-filter-bar .form-select,.admin-users .users-filter-bar .form-control { border:1px solid #dce2ec; border-radius:4px; font-size:12px; min-height:39px; background-color:#fff; }
.search-wrap { position:relative; }
.search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#8993a5; pointer-events:none; }
.admin-users .search-wrap input { padding-left:36px; width:290px; }
.users-clear { border:0; background:transparent; color:#53617a; font-size:12px; padding:8px 4px; }
.admin-users .users-clear-accounts { background:#e8f0ff; border:1px solid #b9cef7; color:#245cbb; border-radius:4px; padding:9px 12px; font-weight:600; white-space:nowrap; cursor:pointer; }
.admin-users .users-clear-accounts:hover { background:#245cbb; border-color:#245cbb; color:#fff; }
.admin-users button:focus-visible,.admin-users input:focus-visible,.admin-users select:focus-visible { outline:2px solid #2f6fed; outline-offset:3px; }
.admin-users .role-group-card { border:1px solid #e4e9f2; border-radius:4px; overflow:hidden; box-shadow:none; margin-bottom:20px; }
.role-table-label { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:19px 22px; background:#fff; border-bottom:1px solid #e4e9f2; border-left:3px solid #17356f; }
.role-table-label h5 { color:#09173c; font-size:14px; font-weight:600; margin:0; }
.role-table-label h5 i { color:#75809a; }
.role-table-label .badge { background:#f0f3f8; color:#64708a; border-radius:3px; font-size:11px; font-weight:500; padding:6px 9px; white-space:nowrap; }
.users-table { font-size:13px; margin:0; }
.users-table thead th { background:#f8f9fc; color:#75809a; font-size:10px; font-weight:600; letter-spacing:.09em; text-transform:uppercase; padding:12px 18px; border-top:0; border-bottom:1px solid #e4e9f2; white-space:nowrap; }
.users-table tbody td { padding:16px 18px; vertical-align:middle; border-color:#eef1f7; color:#53617a; }
.users-table tbody tr:hover { background:#f8faff; }
.user-name-cell { display:flex; align-items:center; gap:11px; min-width:175px; }
.user-avatar-sm { width:36px; height:36px; border-radius:4px; overflow:hidden; flex-shrink:0; background:#eef2f8; display:flex; align-items:center; justify-content:center; }
.user-avatar-sm img { width:100%; height:100%; object-fit:cover; }
.avatar-initials { font-size:12px; font-weight:600; color:#17356f; }
.user-name-text { font-size:13px; font-weight:600; color:#172742; }
.user-email-sub { font-size:11px; color:#8993a5; margin-top:3px; }
.status-badge { display:inline-flex; align-items:center; gap:6px; font-size:11px; padding:5px 8px; border-radius:3px; }
.status-badge.active { background:#edf7f1; color:#137a4e; }
.status-badge.inactive { background:#f0f2f6; color:#6f7a8d; }
.status-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.action-btn-group { display:inline-flex; gap:6px; }
.action-btn { width:31px; height:31px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #e0e6ef; border-radius:3px; background:#fff; color:#53617a; padding:0; font-size:13px; cursor:pointer; }
.action-btn:hover { border-color:#17356f; background:#eef3fc; color:#17356f; }
.action-btn.view-btn { background:#e8f0ff; border-color:#b9cef7; color:#245cbb; }
.action-btn.view-btn:hover { background:#245cbb; border-color:#245cbb; color:#fff; }
.action-btn.edit-btn { background:#fff2d6; border-color:#edcf94; color:#925b0a; }
.action-btn.edit-btn:hover { background:#925b0a; border-color:#925b0a; color:#fff; }
.action-btn.toggle-off { background:#fce9e7; border-color:#edbbb6; color:#ad3f35; }
.action-btn.toggle-off:hover { background:#ad3f35; border-color:#ad3f35; color:#fff; }
.action-btn.toggle-on { background:#e5f5eb; border-color:#b1d9c1; color:#167345; }
.action-btn.toggle-on:hover { background:#167345; border-color:#167345; color:#fff; }
.date-cell,.phone-cell { white-space:nowrap; font-size:12px; }
.users-empty-state { padding:32px !important; text-align:center; color:#8993a5; }
.empty-icon { font-size:24px; margin-bottom:8px; }
.empty-text { font-size:12px; }
.users-no-results { background:#fff; border:1px dashed #cbd3df; padding:30px; text-align:center; color:#6f7a8d; font-size:13px; margin-bottom:20px; }
.admin-users .modal-content { border:0 !important; border-radius:14px !important; }
.admin-users .modal-header { background:#09173c !important; border-radius:14px 14px 0 0 !important; }
.admin-users .modal-body .form-control,.admin-users .modal-body .form-select,.admin-users .modal .btn { border-radius:10px !important; font-size:13px; }
.admin-users .modal-body p { overflow-wrap:anywhere; }
.admin-users .modal-body [style*="border-top:4px"] { border:1px solid #e4e9f2 !important; border-radius:4px !important; box-shadow:none !important; }
@media(max-width:991px) { .filter-group { flex:1; } .search-wrap { flex:1 1 240px; } .admin-users .search-wrap input { width:100%; } }
@media(max-width:600px) {
.admin-users .users-shell > .card-body { padding:0 0 20px; }
.users-filter-bar .filter-group { width:100%; }
.admin-users .users-filter-bar .form-select { flex:1; min-width:0 !important; }
.role-table-label { padding:16px; }
.role-table-label h5 { font-size:12px; }
}
.admin-accounts .role-group-card { --role-accent:#245cbb; --role-tint:#edf3fd; --role-badge:#dce8fc; border-color:#d6dfed; }
.admin-accounts .role-group-card[data-role="4"] { --role-accent:#16735e; --role-tint:#edf7f3; --role-badge:#d6eee4; }
.admin-accounts .role-group-card[data-role="2"] { --role-accent:#7040a2; --role-tint:#f4eef9; --role-badge:#e9ddf5; }
.admin-accounts .role-table-label { background:var(--role-tint); border-left-color:var(--role-accent); }
.admin-accounts .role-table-label h5,.admin-accounts .role-table-label h5 i { color:var(--role-accent); }
.admin-accounts .role-table-label .badge { background:var(--role-badge); color:var(--role-accent); }
.admin-accounts .user-avatar-sm { background:var(--role-badge); }
.admin-accounts .avatar-initials { color:var(--role-accent); }
.admin-accounts .users-table thead th { background:#eaf0f7; color:#53617a; border-bottom-color:#d6dfed; }
/* A shared column grid keeps the three independent role tables aligned. */
.admin-accounts .users-table { table-layout:fixed; width:100%; min-width:1080px; }
.admin-accounts .account-col-index { width:5%; }
.admin-accounts .account-col-user { width:23%; }
.admin-accounts .account-col-email { width:24%; }
.admin-accounts .account-col-phone { width:13%; }
.admin-accounts .account-col-status { width:11%; }
.admin-accounts .account-col-created { width:12%; }
.admin-accounts .account-col-actions { width:12%; }
.admin-accounts .user-name-cell { min-width:0; }
.admin-accounts .user-name-cell > div:last-child { min-width:0; }
.admin-accounts .user-name-text,.admin-accounts .user-email-sub,
.admin-accounts .users-table tbody td:nth-child(3) { overflow-wrap:anywhere; }
.admin-accounts .users-table th:last-child,.admin-accounts .users-table td:last-child { text-align:center; }
/* Profile detail dialog: split record sheet, flat surfaces only. */
@media(min-width:768px) { #viewProfileModal .modal-dialog { max-width:920px; } }
.profile-dialog { --profile-accent:#245cbb; --profile-tint:#e9f0fc; }
.profile-dialog[data-role="4"] { --profile-accent:#16735e; --profile-tint:#e4f2ec; }
.profile-dialog[data-role="2"] { --profile-accent:#7040a2; --profile-tint:#efe6f8; }
#viewProfileModal .modal-header { padding:16px 24px; border-bottom:2px solid var(--profile-accent); }
#viewProfileModal .modal-title { color:#fff; font-size:17px; font-weight:600; letter-spacing:-.01em; margin:0; }
#viewProfileModal .profile-eyebrow { display:block; margin-bottom:4px; color:#94a7c9; font-size:9px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; }
#viewProfileModal .modal-body { padding:0; background:#fff; }
.profile-sheet { display:grid; grid-template-columns:260px minmax(0,1fr); min-height:100%; }
.profile-side { padding:28px 24px; background:var(--profile-tint); border-right:1px solid #dce3ed; border-top:4px solid var(--profile-accent); }
.profile-side-label { display:block; color:var(--profile-accent); font-size:9px; font-weight:600; letter-spacing:.14em; text-transform:uppercase; margin-bottom:22px; }
.profile-avatar { display:grid; place-items:center; width:88px; height:88px; border-radius:4px; overflow:hidden; background:#fff; border:1px solid #dbe3ee; color:var(--profile-accent); }
.profile-avatar img { width:100%; height:100%; object-fit:cover; }
.profile-avatar > span { font-size:28px; font-weight:600; letter-spacing:.04em; }
.profile-name { margin:16px 0 12px; color:#15243d; font-size:19px; font-weight:600; line-height:1.35; letter-spacing:-.015em; overflow-wrap:anywhere; }
.profile-tags { display:flex; flex-wrap:wrap; gap:6px; }
.profile-tag { display:inline-flex; align-items:center; gap:5px; padding:4px 7px; border-radius:3px; font-size:9px; font-weight:600; letter-spacing:.09em; text-transform:uppercase; }
.profile-tag-role { background:var(--profile-accent); color:#fff; }
.profile-tag-status { background:#eef1f6; color:#6d7889; }
.profile-tag-status.is-active { background:#e9f5ee; color:#137a4e; }
.profile-tag-status.is-inactive { background:#f0f2f6; color:#6f7a8d; }
.profile-tag-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.profile-facts { display:grid; gap:13px; margin:22px 0 0; padding:16px 0 0; border-top:1px solid #e0e6f0; }
.profile-facts dt { margin-bottom:3px; color:#77839a; font-size:9px; font-weight:600; letter-spacing:.11em; text-transform:uppercase; }
.profile-facts dd { margin:0; color:#33415a; font-size:12px; line-height:1.5; overflow-wrap:anywhere; }
.profile-main { padding:24px; background:#f7f9fc; }
.profile-group { background:#fff; border:1px solid #dfe6f0; border-radius:4px; overflow:hidden; }
.profile-group + .profile-group { margin-top:16px; }
.profile-group-title { display:flex; align-items:center; gap:10px; margin:0; padding:14px 18px; color:#22324a; font-size:12px; font-weight:600; border-bottom:1px solid #e8edf4; }
.profile-group-title i { display:grid; place-items:center; width:28px; height:28px; border-radius:3px; background:var(--profile-tint); color:var(--profile-accent); font-size:14px; }
.profile-rows { margin:0; padding:4px 18px 14px; }
.profile-row { display:grid; grid-template-columns:132px minmax(0,1fr); gap:12px; padding:11px 0; border-bottom:1px solid #eef1f7; }
.profile-row:last-child { padding-bottom:0; border-bottom:0; }
.profile-row dt { color:#6d7889; font-size:12px; }
.profile-row dd { margin:0; color:#22324a; font-size:13px; line-height:1.6; white-space:pre-wrap; overflow-wrap:anywhere; }
.profile-empty { color:#98a3b3; }
.profile-link { color:#245cbb; text-decoration:none; border-bottom:1px solid rgba(36,92,187,.28); }
.profile-link:hover { color:#17356f; border-bottom-color:#17356f; }
#viewProfileModal .modal-footer { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; border-top:1px solid #e4e9f2; padding:13px 24px; background:#fff; }
#viewProfileModal .profile-footer-note { color:#7b8698; font-size:11px; }
#viewProfileModal .profile-close { background:#245cbb; border:1px solid #245cbb; color:#fff; padding:9px 20px; font-size:12px; font-weight:600; }
#viewProfileModal .profile-close:hover { background:#09173c; border-color:#09173c; color:#fff; }
@media(max-width:767px) {
.profile-sheet { grid-template-columns:minmax(0,1fr); }
.profile-side { padding:22px 20px; border-right:0; border-bottom:1px solid #e4e9f2; }
.profile-facts { grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
.profile-main { padding:20px; }
.profile-row { grid-template-columns:112px minmax(0,1fr); gap:10px; }
#viewProfileModal .modal-header,#viewProfileModal .modal-footer { padding:14px 20px; }
}
@media(max-width:575px) {
.profile-avatar { width:62px; height:62px; }
.profile-avatar > span { font-size:20px; }
.profile-name { font-size:18px; margin-top:14px; }
.profile-row { grid-template-columns:minmax(0,1fr); gap:2px; }
.profile-row dt { color:#77839a; font-size:9px; font-weight:600; letter-spacing:.11em; text-transform:uppercase; }
}
/* Edit account: matching record panels with a persistent action footer. */
@media(min-width:992px) { #editModal .modal-dialog { max-width:920px; } }
#editModal .modal-header { padding:20px 24px; border-bottom:2px solid #2f6fed; }
#editModal .modal-title { color:#fff; font-size:18px; font-weight:600; margin:4px 0; }
.edit-eyebrow { display:block; color:#a9b9d6; font-size:9px; font-weight:600; letter-spacing:.14em; text-transform:uppercase; }
#editModal .modal-header small { color:#bcc8de; font-size:12px; }
#editModal form { display:flex; flex-direction:column; min-height:0; overflow:hidden; }
#editModal .modal-body { padding:24px; background:#f4f6fa; overflow-y:auto; }
.edit-profile-banner { display:flex; align-items:center; gap:18px; background:#eaf0fb; border:1px solid #d5e0f2; border-left:3px solid #245cbb; border-radius:4px; padding:18px; }
#editImgPreview { width:76px; height:76px; flex-shrink:0; border-radius:4px; overflow:hidden; display:grid; place-items:center; background:#fff; border:1px solid #cbd8ed; color:#245cbb; font-size:30px; }
#editImgPreview img { width:100%; height:100%; object-fit:cover; }
.edit-profile-copy { min-width:0; }
#editModal .edit-profile-copy .edit-eyebrow { color:#657b9e; }
#editAccountName { margin:5px 0 10px; color:#172742; font-size:18px; font-weight:600; overflow-wrap:anywhere; }
.edit-photo-controls { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.edit-photo-button { background:#fff; border:1px solid #b9cef7; color:#245cbb; border-radius:3px; padding:5px 9px; font-size:11px; margin:0; cursor:pointer; }
.edit-photo-controls > span { font-size:10px; color:#657b9e; }
#editProfileImg { display:none; max-width:100%; font-size:11px; margin-top:10px; color:#53617a; }
.edit-required-note { margin:14px 0; font-size:11px; color:#75809a; }
.edit-required-note span { color:#b0443d; }
.edit-form-section { background:#fff; border:1px solid #dfe6f0; border-radius:4px; padding:18px; margin-bottom:16px; }
.edit-form-section:last-child { margin-bottom:0; }
.edit-section-heading { display:flex; align-items:center; gap:10px; padding-bottom:14px; margin-bottom:16px; border-bottom:1px solid #e8edf4; }
.edit-section-icon { width:32px; height:32px; flex-shrink:0; display:grid; place-items:center; border-radius:3px; background:#e9f0fc; color:#245cbb; }
.edit-form-section:nth-of-type(3) .edit-section-icon { background:#e4f2ec; color:#16735e; }
.edit-form-section:nth-of-type(4) .edit-section-icon { background:#efe6f8; color:#7040a2; }
.edit-section-heading h6 { margin:0 0 3px; color:#22324a; font-size:13px; font-weight:600; }
.edit-section-heading small { color:#75809a; font-size:11px; }
#editModal .form-label { color:#53617a; font-size:11px; margin-bottom:6px; }
#editModal .form-control,#editModal .form-select { min-height:40px; border-color:#d6dfeb; padding:8px 11px; }
#editModal .input-group-text { background:#f0f4fa; border-color:#d6dfeb; color:#657b9e; border-radius:4px 0 0 4px; }
#editModal textarea { resize:vertical; }
#editModal .modal-footer { flex-shrink:0; background:#fff; border-top:1px solid #dfe6f0; padding:16px 24px; gap:8px; }
#editModal .modal-footer .btn { padding:9px 16px; font-size:12px; font-weight:600; }
#editModal .modal-footer .btn-light { background:#fff; border:1px solid #ccd6e4; color:#53617a; }
#editModal .edit-save { background:#245cbb; border:1px solid #245cbb; color:#fff; }
#editModal .edit-save:hover { background:#173f87; border-color:#173f87; }
@media(max-width:575px) {
#editModal .modal-header,#editModal .modal-body,#editModal .modal-footer { padding:16px; }
.edit-profile-banner { padding:14px; gap:12px; align-items:flex-start; }
#editImgPreview { width:58px; height:58px; }
#editAccountName { font-size:16px; }
.edit-form-section { padding:14px; }
}
</style>
