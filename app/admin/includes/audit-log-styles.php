<style>
.al-filter-bar { background:#fff; border:1px solid #e4e9f2; border-radius:4px; padding:18px; margin-bottom:18px; }
.al-filter-bar .form-control,.al-filter-bar .form-select { border:1px solid #d6dfeb; border-radius:4px; height:40px; font-size:12px; }
.al-filter-bar .input-group-text { background:#f0f4fa; border:1px solid #d6dfeb; color:#75809a; border-radius:4px 0 0 4px; }
.al-filter-bar .input-group .form-control { border-radius:0 4px 4px 0; }
.al-reset-btn,.al-export-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; border:1px solid #b9cef7; border-radius:4px; padding:9px 13px; height:40px; font-size:12px; font-weight:600; background:#e8f0ff; color:#245cbb; white-space:nowrap; cursor:pointer; }
.al-reset-btn:hover { background:#245cbb; border-color:#245cbb; color:#fff; }
.al-export-btn { background:#e5f5eb; color:#167345; border-color:#b1d9c1; }
.al-export-btn:hover { background:#167345; border-color:#167345; color:#fff; }
.al-results-info { color:#75809a; font-size:11px; padding-top:6px; }
.al-results-info strong { color:#33415a; }
.al-table-wrap { border:1px solid #dce4ef; border-radius:4px; overflow:hidden; background:#fff; }
.audit-table-heading { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:18px 22px; border-bottom:1px solid #e4e9f2; }
.audit-table-heading span { color:#172742; font-size:14px; font-weight:600; }
.audit-table-heading small { color:#75809a; font-size:11px; }
.al-table { margin:0; font-size:13px; min-width:780px; }
.al-table thead th { background:#eaf0f7; color:#53617a; padding:13px 18px; font-size:10px; font-weight:600; letter-spacing:.09em; text-transform:uppercase; border:0; white-space:nowrap; }
.al-table tbody td { padding:15px 18px; vertical-align:middle; border-color:#eef1f7; }
.al-table tbody tr:hover { background:#f8faff; }
.al-table tbody tr:last-child td { border-bottom:0; }
.al-user-avatar { display:grid; place-items:center; width:35px; height:35px; flex-shrink:0; border-radius:4px; background:#e8edf7; color:#17356f; font-size:11px; font-weight:600; }
.al-user-name { font-size:13px; color:#172742; font-weight:500; overflow-wrap:anywhere; }
.al-role-badge,.al-action-badge { display:inline-flex; align-items:center; gap:6px; border-radius:3px; padding:5px 8px; font-size:10px; font-weight:600; white-space:nowrap; }
.al-role-organizer { background:#e4edff; color:#245cbb; }
.al-role-committee { background:#e0f3ed; color:#176b50; }
.al-role-admin { background:#efe7fa; color:#7040a2; }
.al-role-default { background:#f0f3f8; color:#53617a; }
.al-date { color:#33415a; font-size:12px; white-space:nowrap; }
.al-time { display:flex; align-items:center; gap:5px; color:#8993a5; font-size:11px; margin-top:4px; }
.al-row-num { color:#98a1b2; font-size:11px; }
.al-empty { padding:52px 24px; text-align:center; }
.al-empty-icon { color:#98a1b2; font-size:30px; margin-bottom:14px; }
.al-empty-title { color:#172742; font-size:16px; margin-bottom:7px; }
.al-empty-sub { color:#75809a; font-size:13px; }
.al-no-results { text-align:center; padding:40px 20px !important; color:#75809a; font-size:13px; }
.al-no-results i { display:block; font-size:24px; margin-bottom:10px; color:#98a1b2; }
.al-pagination { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; background:#fff; border:1px solid #e4e9f2; border-radius:4px; padding:16px 20px; margin-top:16px; }
.al-pagination-info { color:#75809a; font-size:11px; }
.al-pagination-nav { display:flex; gap:5px; align-items:center; flex-wrap:wrap; }
.al-pagination-nav a,.al-pagination-nav span { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-width:33px; height:34px; padding:0 9px; border:1px solid #dce4ef; border-radius:3px; font-size:12px; color:#53617a; text-decoration:none; }
.al-pagination-nav a:hover { background:#edf2fc; border-color:#b9cef7; color:#245cbb; }
.al-pagination-nav .al-page-active { background:#245cbb; color:#fff; border-color:#245cbb; }
.al-pagination-nav .al-page-disabled { color:#aab3c2; background:#f8f9fc; }
.al-pagination-nav .al-page-ellipsis { border:0; }
@media(max-width:600px) {
.al-filter-bar { padding:14px; }
.al-filter-bar .row > .col-auto { flex:1 1 100%; min-width:0 !important; }
.al-reset-btn,.al-export-btn { flex:1; }
.audit-table-heading { flex-direction:column; align-items:flex-start; padding:16px; }
.al-pagination { padding:14px; }
}
</style>
