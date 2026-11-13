<style>
.util-tabs{display:flex;gap:6px;flex-wrap:wrap;padding:0;list-style:none}
.util-tab{padding:8px 18px;border-radius:4px 8px 0 0;font-size:13px;font-weight:500;cursor:pointer;border:1px solid #dee2e6;border-bottom:none;background:#f8f9fa;color:#6b7280;transition:all .2s;display:inline-flex;align-items:center;gap:6px}
.util-tab:hover{background:#e9ecef;color:#09173c}.util-tab.active{background:#fff;color:#09173c;font-weight:600;box-shadow:0 -2px 8px rgba(9,23,60,.06)}
.util-table-wrap{background:#fff;border:1px solid #dee2e6;border-radius:0 10px 10px 10px;padding:20px;box-shadow:0 2px 8px rgba(9,23,60,.06)}
.util-table thead th{background:#09173c;color:#fff;font-weight:500;font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:12px 14px;border:none}
.util-table thead th:first-child{border-radius:4px 0 0 0}.util-table thead th:last-child{border-radius:0 8px 0 0}
.util-table tbody td{padding:12px 14px;vertical-align:middle;font-size:14px}.util-table tbody tr:hover{background:#f0f4ff}
.cat-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:4px;font-size:12px;font-weight:500;white-space:nowrap}
.status-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:4px;font-size:12px;font-weight:500}
.status-badge.active{background:#d1fae5;color:#065f46}.status-badge.inactive{background:#f3f4f6;color:#6b7280}
.status-badge .status-dot{width:7px;height:7px;border-radius:50%;display:inline-block}
.status-badge.active .status-dot{background:#10b981}.status-badge.inactive .status-dot{background:#9ca3af}
.util-action-btn{width:34px;height:34px;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;border:1px solid transparent;font-size:15px;transition:all .2s;cursor:pointer;background:transparent}
.util-action-btn.edit{color:#d97706;border-color:#fde68a}.util-action-btn.edit:hover{background:#fef3c7;color:#b45309}
.util-action-btn.toggle-on{color:#10b981;border-color:#a7f3d0}.util-action-btn.toggle-on:hover{background:#d1fae5;color:#065f46}
.util-action-btn.toggle-off{color:#ef4444;border-color:#fca5a5}.util-action-btn.toggle-off:hover{background:#fee2e2;color:#b91c1c}
.info-chip{display:inline-flex;align-items:center;gap:3px;background:#f0f4ff;padding:2px 10px;border-radius:4px;font-size:11px;color:#09173c;margin-right:4px}
.util-empty-state{text-align:center;padding:48px 20px;color:#9ca3af}.util-empty-state i{font-size:3rem;margin-bottom:12px;display:block}
.util-modal .modal-content{border-radius:14px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15)}
.util-modal .modal-header{background:#09173c;color:#fff;border-radius:14px 14px 0 0;padding:20px 24px;border:none}
.util-modal .modal-header .modal-title{font-weight:500;font-size:16px;letter-spacing:.5px}.util-modal .modal-header .btn-close{filter:invert(1)}
.util-modal .modal-body{padding:24px}.util-modal .modal-footer{padding:16px 24px;border-top:1px solid #f0f0f0}
.util-form-section{background:#fff;border-radius:10px;padding:20px;margin-bottom:16px;border-top:4px solid #09173c;box-shadow:0 2px 8px rgba(9,23,60,.06)}
.util-form-section-header{display:flex;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid #e3e6f0}
.util-form-section-icon{width:40px;height:40px;border-radius:4px;display:flex;align-items:center;justify-content:center;margin-right:12px;font-size:18px;color:#fff}
.util-form-section-title{font-size:13px;font-weight:500;color:#09173c;margin:0}.util-form-section-sub{font-size:11px;color:#6c757d}
.util-modal .form-label{font-size:12px;color:#495057;font-weight:500}.util-modal .form-control,.util-modal .form-select{border-radius:4px;font-size:14px;color:#212529}
.util-modal .form-control:focus,.util-modal .form-select:focus{border-color:#09173c;box-shadow:0 0 0 3px rgba(9,23,60,.12);color:#212529}
.util-modal .form-control::placeholder{color:#adb5bd}
.util-search-wrap{position:relative}.util-search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:14px}
.util-search-wrap input{padding-left:36px;border-radius:4px;border:1px solid #dee2e6;font-size:14px;height:38px;width:260px;max-width:100%}
.util-search-wrap input:focus{border-color:#09173c;box-shadow:0 0 0 3px rgba(9,23,60,.1);outline:none}
</style><style>
.admin-utility .util-tabs { gap:0; margin:0; background:#fff; border:1px solid #e4e9f2; border-bottom:0; border-radius:4px 4px 0 0; padding:0 16px; }
.admin-utility .utility-add-record-btn { background:#09173c; border-color:#09173c; color:#fff; }
.admin-utility .utility-add-record-btn:hover { background:#17356f; border-color:#17356f; color:#fff; }
.admin-utility .util-tab { border:0; border-bottom:2px solid transparent; border-radius:0; background:transparent; font-family:inherit; font-size:12px; padding:17px 14px; color:#75809a; box-shadow:none; gap:8px; }
.admin-utility .util-tab:hover { color:#17356f; background:#f8faff; }
.admin-utility .util-tab.active { color:#17356f; background:transparent; border-bottom-color:#2f6fed; box-shadow:none; }
.admin-utility .util-table-wrap { padding:0; border:1px solid #e4e9f2; border-radius:0 0 4px 4px; box-shadow:none; overflow:hidden; }
.utility-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:18px 22px; border-bottom:1px solid #eef1f7; }
.admin-utility .util-search-wrap input { width:320px; height:39px; border-radius:4px; border-color:#dce2ec; font-size:12px; }
.admin-utility .util-table thead th { background:#f8f9fc; color:#75809a; border-radius:0; border-bottom:1px solid #e4e9f2; padding:12px 18px; font-size:10px; font-weight:600; letter-spacing:.09em; }
.admin-utility .util-table tbody td { padding:16px 18px; font-size:13px; border-color:#eef1f7; }
.admin-utility .util-table tbody tr:hover { background:#f8faff; }
.admin-utility .cat-badge { border-radius:3px; font-size:11px; padding:5px 8px; }
.admin-utility .info-chip { background:#edf2fc; color:#365991; border-radius:3px; padding:4px 8px; white-space:nowrap; }
.admin-utility .util-action-btn { width:31px; height:31px; border-radius:3px; border:1px solid #e0e6ef; font-size:13px; color:#53617a; }
.admin-utility .util-action-btn:hover { border-color:#17356f; color:#17356f; background:#eef3fc; }
.admin-utility .util-action-btn.edit { background:#fff2d6; border-color:#edcf94; color:#925b0a; }
.admin-utility .util-action-btn.edit:hover { background:#925b0a; border-color:#925b0a; color:#fff; }
.admin-utility .util-action-btn.toggle-on { background:#e5f5eb; border-color:#b1d9c1; color:#167345; }
.admin-utility .util-action-btn.toggle-on:hover { background:#167345; border-color:#167345; color:#fff; }
.admin-utility .util-action-btn.toggle-off { background:#fce9e7; border-color:#edbbb6; color:#ad3f35; }
.admin-utility .util-action-btn.toggle-off:hover { background:#ad3f35; border-color:#ad3f35; color:#fff; }
.admin-utility tr[data-category="sport"] .cat-badge { background:#e8edf7; color:#17356f; }
.admin-utility tr[data-category="division"] .cat-badge { background:#e4edff; color:#245cbb; }
.admin-utility tr[data-category="venue"] .cat-badge { background:#e0f3ed; color:#176b50; }
.admin-utility tr[data-category="document_type"] .cat-badge { background:#e0f2fa; color:#176585; }
.admin-utility tr[data-category="category"] .cat-badge { background:#efe7fa; color:#7040a2; }
.admin-utility .status-badge.active { background:#dff3e7; color:#167345; }
.admin-utility .status-badge.inactive { background:#f3eae9; color:#87534d; }
.admin-utility .status-badge .status-dot { background:currentColor; }
.admin-utility .util-empty-state { padding:40px 20px; }
.admin-utility .util-empty-state i { font-size:26px; color:#aab5c6; }
.admin-utility .util-modal .modal-content { box-shadow:0 16px 48px rgba(9,23,60,.15); }
.admin-utility .util-modal .modal-header { padding:22px 24px; }
.admin-utility .util-modal .modal-title { font-size:16px; letter-spacing:0; }
.admin-utility .util-modal .modal-body { background:#f8f9fc; padding:22px; }
.admin-utility .util-form-section { border:1px solid #e4e9f2; border-radius:10px; box-shadow:none; padding:18px; }
.admin-utility .util-form-section:last-child { margin-bottom:0; }
.admin-utility .util-form-section-header { border-bottom:1px solid #eef1f7; padding-bottom:12px; }
.admin-utility .util-form-section-icon { width:32px; height:32px; border-radius:10px; background:#edf1f8 !important; color:#17356f; font-size:15px; }
.admin-utility .util-form-section-title { font-weight:600; }
.admin-utility .util-modal .modal-footer { border-top:1px solid #e4e9f2; gap:10px; }
.admin-utility .util-modal .btn-secondary { background:#fff; border-color:#dce2ec; color:#53617a; }
.admin-utility .util-modal .btn-danger { background:#fff; border-color:#ecd1d1; color:#b64d4d; }
/* SweetAlert2 dialogs are themed by assets/css/lms-modal.css (shared). */
.admin-utility #utilNoResults { margin:20px; }
@media(max-width:600px) {
.admin-utility .util-tabs { padding:4px 8px 0; }
.admin-utility .util-tab { padding:12px 9px; font-size:11px; }
.utility-toolbar { padding:16px; }
.admin-utility .util-search-wrap,.admin-utility .util-search-wrap input { width:100%; }
.admin-utility .util-modal .modal-body { padding:16px; }
.admin-utility .util-modal .modal-footer { padding:16px; }
.admin-utility .util-modal .modal-footer > div { display:flex; gap:8px; }
}
</style>
