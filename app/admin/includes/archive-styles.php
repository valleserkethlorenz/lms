<style>
/* Archive-specific details; shared directory layout comes from users-styles.php. */
.admin-archive .users-clear { background:#e8f0ff; border:1px solid #b9cef7; color:#245cbb; border-radius:4px; padding:9px 12px; font-weight:600; white-space:nowrap; cursor:pointer; }
.admin-archive .users-clear:hover { background:#245cbb; border-color:#245cbb; color:#fff; }
.admin-archive #sportFilter { width:auto; min-width:170px; }
.admin-archive .archive-panel { background:#fff; border:1px solid #e4e9f2; border-radius:4px; overflow:hidden; }
.archive-panel-heading { display:flex; justify-content:space-between; align-items:center; gap:14px; padding:20px 22px; border-bottom:1px solid #e4e9f2; }
.archive-panel-heading > span { color:#09173c; font-size:14px; font-weight:600; }
.archive-panel-heading small { color:#8993a5; font-size:11px; }
.admin-archive .users-table { margin-bottom:0; }
.admin-archive .users-table td:nth-child(1) { color:#98a1b2; font-size:11px; }
.admin-archive .users-table td:nth-child(2) { min-width:190px; color:#172742; font-weight:600; }
.admin-archive .users-table td:nth-child(4) { min-width:145px; }
.admin-archive .users-table td:nth-child(n+6):nth-child(-n+8) { white-space:nowrap; font-size:12px; color:#75809a; }
.archive-status { display:inline-flex; align-items:center; gap:6px; padding:5px 8px; border-radius:3px; font-size:11px; white-space:nowrap; background:#f0f2f6; color:#6f7a8d; }
.archive-status::before { content:""; width:5px; height:5px; border-radius:50%; background:currentColor; }
.archive-status.is-success { background:#edf7f1; color:#137a4e; }
.archive-status.is-primary { background:#edf2fc; color:#2a5db0; }
.archive-status.is-warning { background:#fcf5e7; color:#946012; }
.archive-export { display:inline-flex; align-items:center; padding:6px 9px; border:1px solid #e0e6ef; border-radius:3px; color:#53617a; font-size:12px; text-decoration:none !important; white-space:nowrap; }
.archive-export:hover { background:#eef3fc; border-color:#17356f; color:#17356f; }
.archive-export.is-csv { background:#edf7f1; border-color:#bcdcc9; color:#176b43; }
.archive-export.is-csv:hover { background:#176b43; border-color:#176b43; color:#fff; }
.archive-export.is-pdf { background:#fcf0ef; border-color:#ecc7c3; color:#aa3e35; }
.archive-export.is-pdf:hover { background:#aa3e35; border-color:#aa3e35; color:#fff; }
.archive-export:focus-visible { outline:2px solid #2f6fed; outline-offset:3px; }
.archive-empty { text-align:center; padding:52px 22px; background:#fff; border:1px solid #e4e9f2; border-radius:4px; color:#8993a5; }
.archive-empty h4 { font-size:16px; color:#172742; margin:0 0 8px; }
.archive-empty p { font-size:13px; margin:0; }
@media(max-width:600px) {
.admin-archive #sportFilter { width:100%; }
.admin-archive .search-wrap { flex-basis:100%; }
.archive-panel-heading { align-items:flex-start; flex-direction:column; gap:5px; padding:16px; }
}
</style>
