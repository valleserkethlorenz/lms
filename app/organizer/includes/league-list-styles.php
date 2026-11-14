<style>
/* League register: the dashboard's season-desk palette, in a flat layout. */
.organizer-leagues { --league-navy:#09173c; --league-line:#e4e9f2; --league-muted:#6f7a8d; }
.organizer-leagues .activity-section { border:1px solid var(--league-line); border-radius:4px; box-shadow:none; }
.organizer-leagues .activity-header { padding:22px 24px; border-bottom:1px solid var(--league-line); }
.organizer-leagues .activity-badge-count { background:#f0f3f8; color:#596780; border-radius:3px; font-size:10px; letter-spacing:0; }
.organizer-leagues .league-filter-bar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; padding:16px 24px; border-bottom:1px solid var(--league-line); }
.organizer-leagues .league-search-input { width:280px; max-width:100%; border:1px solid #d6deea; border-radius:3px; padding:10px 12px; background:#fff; font:inherit; font-size:12px; color:var(--league-navy); }
.organizer-leagues .league-status-filter { display:flex; gap:0; flex-wrap:wrap; }
.organizer-leagues .league-status-btn { background:transparent; border:0; border-bottom:2px solid transparent; border-radius:0; padding:10px 13px; color:#67748a; font-size:12px; font-weight:500; cursor:pointer; }
.organizer-leagues .league-status-btn:hover { color:var(--league-navy); background:#f4f6fb; }
.organizer-leagues .league-status-btn.active { border-bottom-color:#2f6fed; color:var(--league-navy); font-weight:600; }
.organizer-leagues .league-table-wrapper { overflow-x:auto; }
.organizer-leagues #organizeTable { width:100% !important; font-variant-numeric:tabular-nums; }
.organizer-leagues #organizeTable thead th { background:#f4f6fb; color:#67748a; font-size:10px; font-weight:600; letter-spacing:.07em; padding:14px 16px; border-bottom:1px solid var(--league-line); }
.organizer-leagues #organizeTable thead th i { display:none; }
.organizer-leagues #organizeTable tbody td { padding:19px 16px; font-size:12px; border-color:#eef1f7; color:#34415a; }
.organizer-leagues #organizeTable tbody td:first-child { color:#98a1b2; font-size:11px; }
.organizer-leagues #organizeTable tbody td:nth-child(2) { color:var(--league-navy); min-width:180px; font-weight:600 !important; }
/* Deferred setup: a league created without a structure keeps this chip until one is applied. */
.organizer-leagues .league-setup-chip { display:inline-flex; align-items:center; gap:4px; margin-left:8px; padding:3px 8px; border:1px solid #ecd399; border-radius:3px; background:#fff3d9; color:#89580b; font-size:10px; font-weight:600; letter-spacing:.02em; text-transform:none; text-decoration:none; vertical-align:middle; }
.organizer-leagues .league-setup-chip:hover { background:#ffe9bd; border-color:#dcb96a; color:#6f4607; text-decoration:none; }
.organizer-leagues .league-setup-chip i { font-size:11px; }
.organizer-leagues #organizeTable tbody tr:hover { background:#f8faff; }
.organizer-leagues .league-actions { display:inline-flex; align-items:center; gap:6px; }
.organizer-leagues .league-action-column { min-width:106px; text-align:center; }
.organizer-leagues .league-icon-btn { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; padding:0; border:1px solid #d6deea; border-radius:3px; background:#fff; color:#17356f; font-size:14px; text-decoration:none; }
.organizer-leagues .league-icon-btn:hover { background:#edf2fb; border-color:#8da6cf; }
.organizer-leagues .league-action-publish { color:#137a4e; border-color:#bcdccc; }
.organizer-leagues .league-action-archive { color:#b42318; border-color:#e5c2be; }
.organizer-leagues .badge { border-radius:3px; padding:5px 8px !important; font-size:10px; font-weight:500; }
.organizer-leagues .badge.text-bg-success { background:#e7f3ed !important; color:#137a4e !important; }
.organizer-leagues .badge.text-bg-secondary { background:#eef1f5 !important; color:#647086 !important; }
.organizer-leagues #organizeTable tbody td:nth-child(6) .badge { border:1px solid transparent; font-weight:600; }
.organizer-leagues #organizeTable tbody td:nth-child(6) .text-bg-success { background:#e4f4eb !important; color:#146c43 !important; border-color:#b8dfc9; }
.organizer-leagues #organizeTable tbody td:nth-child(6) .text-bg-primary { background:#e6efff !important; color:#2458ad !important; border-color:#bed1f4; }
.organizer-leagues #organizeTable tbody td:nth-child(6) .text-bg-warning { background:#fff3d9 !important; color:#89580b !important; border-color:#ecd399; }
.organizer-leagues #organizeTable tbody td:nth-child(6) .text-bg-secondary { background:#eee9f8 !important; color:#695095 !important; border-color:#d6c9eb; }
.organizer-leagues .dataTables_wrapper .dataTables_length,
.organizer-leagues .dataTables_wrapper .dataTables_filter,
.organizer-leagues .dataTables_wrapper .dataTables_info,
.organizer-leagues .dataTables_wrapper .dataTables_paginate { padding:14px 24px; font-size:11px; color:#67748a; }
.organizer-leagues .dataTables_wrapper select,
.organizer-leagues .dataTables_wrapper input { border:1px solid #d6deea; border-radius:3px; padding:5px 8px; }
.organizer-leagues .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius:3px !important; background:none; }
.organizer-leagues .dataTables_wrapper .dataTables_paginate .paginate_button.current,
.organizer-leagues .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background:var(--league-navy) !important; color:#fff !important; border:1px solid var(--league-navy) !important; }
.organizer-leagues .modal-content { border-radius:14px; overflow:hidden; }
.organizer-leagues .league-modal .modal-header { background:var(--league-navy); color:#fff; padding:20px 24px; border:0; }
.organizer-leagues .league-modal .modal-header .btn-close { filter:invert(1); }
.organizer-leagues .league-modal .modal-body { padding:24px; }
.organizer-leagues .league-modal label { font-size:11px; font-weight:600; color:#596780; }
.organizer-leagues .league-modal .form-control,
.organizer-leagues .league-modal .form-select { border:1px solid #d6deea; border-radius:3px; padding:10px 12px; font-size:13px; }
.organizer-leagues .league-modal .btn-create { background:var(--league-navy); border:0; color:#fff; border-radius:3px; padding:11px 24px; font-size:12px; font-weight:600; }
.organizer-leagues a:focus-visible,
.organizer-leagues button:focus-visible,
.organizer-leagues input:focus-visible { outline:2px solid #2f6fed; outline-offset:3px; }
@media(max-width:768px) {
    .organizer-leagues .activity-header,.organizer-leagues .league-filter-bar { padding:16px; }
    .organizer-leagues .league-search-input { width:100%; }
    .organizer-leagues .league-status-filter { width:100%; }
    .organizer-leagues .league-status-btn { flex:1; padding:10px; }
}
@media(max-width:767.98px) {
    .organizer-leagues .league-table-wrapper { overflow-x:hidden; }
    .organizer-leagues #organizeTable { table-layout:fixed; }
    .organizer-leagues #organizeTable thead th:nth-child(1),
    .organizer-leagues #organizeTable thead th:nth-child(3),
    .organizer-leagues #organizeTable thead th:nth-child(4),
    .organizer-leagues #organizeTable thead th:nth-child(5),
    .organizer-leagues #organizeTable thead th:nth-child(7),
    .organizer-leagues #organizeTable tbody td:nth-child(1),
    .organizer-leagues #organizeTable tbody td:nth-child(3),
    .organizer-leagues #organizeTable tbody td:nth-child(4),
    .organizer-leagues #organizeTable tbody td:nth-child(5),
    .organizer-leagues #organizeTable tbody td:nth-child(7) { display:none; }
    .organizer-leagues #organizeTable thead th:nth-child(2),
    .organizer-leagues #organizeTable tbody td:nth-child(2) { width:52%; min-width:0; }
    .organizer-leagues #organizeTable thead th:nth-child(6),
    .organizer-leagues #organizeTable tbody td:nth-child(6) { width:24%; }
    .organizer-leagues #organizeTable thead th:nth-child(8),
    .organizer-leagues #organizeTable tbody td:nth-child(8) { width:24%; }
    .organizer-leagues #organizeTable thead th,
    .organizer-leagues #organizeTable tbody td { padding:12px 8px; vertical-align:middle; }
    .organizer-leagues #organizeTable tbody td:nth-child(2) { overflow-wrap:anywhere; }
    .organizer-leagues #organizeTable tbody td:nth-child(6) .badge { white-space:normal; line-height:1.2; text-align:center; }
    .organizer-leagues .league-action-column { min-width:0; }
    .organizer-leagues .league-actions { gap:4px; flex-wrap:wrap; justify-content:center; }
    .organizer-leagues .league-icon-btn { width:30px; height:30px; }
    .organizer-leagues .league-setup-chip { margin:6px 0 0; }
}
@media(prefers-reduced-motion:reduce) { .organizer-leagues .dash-animate { animation:none; } }
</style>
