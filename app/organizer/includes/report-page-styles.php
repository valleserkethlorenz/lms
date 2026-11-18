<style>
/* Competition records, using the organizer's flat season-desk language. */
.organizer-reports { --report-navy:#09173c; --report-line:#e4e9f2; --report-muted:#6f7a8d; }
.organizer-reports .report-filter-section,
.organizer-reports .report-results-section { background:#fff; border:1px solid var(--report-line); border-radius:4px; overflow:hidden; margin-bottom:24px; }
.organizer-reports .report-filter-header,
.organizer-reports .report-results-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; padding:22px 24px; border-bottom:1px solid var(--report-line); }
.organizer-reports .report-filter-body { padding:24px; }
.organizer-reports .report-filter-body label { color:#596780; font-size:11px; font-weight:600; margin-bottom:7px; }
.organizer-reports .report-filter-body .form-select,
.organizer-reports .report-filter-body .form-control { border:1px solid #d6deea; border-radius:3px; padding:11px 12px; font-size:12px; color:#34415a; background-color:#fff; }
.organizer-reports .report-filter-body .form-select { padding-right:32px; }
.organizer-reports .report-filter-body .form-select:focus,
.organizer-reports .report-filter-body .form-control:focus { border-color:#2f6fed; box-shadow:0 0 0 2px #e6efff; }
.organizer-reports .report-submit-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; border-top:1px solid #eef1f7; padding-top:20px; margin-top:24px; }
.organizer-reports .report-form-note { color:var(--report-muted); font-size:11px; }
.organizer-reports .report-generate-btn { display:inline-flex; align-items:center; gap:8px; background:var(--report-navy); color:#fff; border:1px solid var(--report-navy); border-radius:3px; padding:11px 20px; font-size:12px; font-weight:600; }
.organizer-reports .report-generate-btn:hover { background:#17356f; }
.organizer-reports .report-generate-btn:disabled { opacity:.6; cursor:wait; }
.organizer-reports .report-submit-actions { display:flex; align-items:center; flex-wrap:wrap; gap:10px; }
.organizer-reports .report-reset-btn { display:inline-flex; align-items:center; gap:7px; border:1px solid #d6deea; border-radius:3px; padding:10px 16px; background:#fff; color:#596780; font-size:12px; font-weight:600; }
.organizer-reports .report-reset-btn:hover { background:#f0f4fb; color:#34415a; }
.organizer-reports .report-notice { display:flex; align-items:flex-start; gap:9px; padding:12px 24px; border-bottom:1px solid var(--report-line); font-size:12px; line-height:1.6; }
.organizer-reports .report-notice[hidden] { display:none !important; }
.organizer-reports .report-notice-warning { background:#fff8e6; color:#8a5a00; }
.organizer-reports .report-notice-info { background:#eff6ff; color:#1e40af; }
.organizer-reports .report-results-section[aria-busy="true"] .report-table-wrapper { opacity:.45; transition:opacity .15s ease; }
.organizer-reports .report-results-actions { display:flex; flex-wrap:wrap; gap:8px; }
.organizer-reports .report-action-btn { display:inline-flex; align-items:center; gap:7px; border:1px solid #d6deea; border-radius:3px; padding:8px 12px; color:#34415a; background:#fff; font-size:11px; font-weight:600; cursor:pointer; }
.organizer-reports .report-action-btn:hover:not(:disabled) { background:#f0f4fb; }
.organizer-reports .report-action-btn:disabled { opacity:.4; cursor:not-allowed; }
.organizer-reports .report-action-btn-print { background:var(--report-navy); color:#fff; border-color:var(--report-navy); }
.organizer-reports .report-action-btn-print:hover:not(:disabled) { background:#17356f; }
.organizer-reports .report-meta { display:flex; flex-wrap:wrap; gap:12px 24px; padding:14px 24px; background:#f4f6fb; border-bottom:1px solid var(--report-line); color:#67748a; font-size:11px; line-height:1.7; }
.organizer-reports .report-meta-item { display:inline-flex; align-items:baseline; gap:7px; }
.organizer-reports .report-meta-item i { color:#8896ad; }
.organizer-reports .report-summary { padding:20px 24px; color:#596780; font-size:12px; line-height:1.8; }
.organizer-reports .report-table-wrapper { overflow:auto; max-height:72vh; }
.organizer-reports .report-table { width:100%; margin:0; border-collapse:collapse; font-variant-numeric:tabular-nums; }
.organizer-reports #reportTable thead th { position:sticky; top:0; z-index:2; background:#f4f6fb; color:#67748a; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; padding:14px 16px; border-block:1px solid var(--report-line); white-space:nowrap; }
.organizer-reports #reportTable tbody td { vertical-align:middle; padding:17px 16px; font-size:12px; color:#34415a; border-bottom:1px solid #eef1f7; }
.organizer-reports #reportTable tbody td.report-number { text-align:right; white-space:nowrap; }
.organizer-reports #reportTable .report-empty { padding:46px 24px; text-align:center; color:var(--report-muted); }
.organizer-reports #reportTable .report-empty i { display:block; font-size:28px; color:#b4bfd0; margin-bottom:10px; }
.organizer-reports #reportTable .report-empty span { font-size:13px; line-height:1.6; }
.organizer-reports #reportTable tbody tr:hover { background:#f8faff; }
.organizer-reports button:focus-visible { outline:2px solid #2f6fed; outline-offset:3px; }
@media(max-width:768px) {
    .organizer-reports .report-filter-header,
    .organizer-reports .report-results-header,
    .organizer-reports .report-filter-body,
    .organizer-reports .report-summary,
    .organizer-reports .report-meta,
    .organizer-reports .report-notice { padding:16px; }
    .organizer-reports .report-submit-actions { width:100%; }
    .organizer-reports .report-generate-btn,
    .organizer-reports .report-reset-btn { flex:1; width:100%; justify-content:center; }
    .organizer-reports .report-table-wrapper { max-height:none; }
}
@media(prefers-reduced-motion:reduce) { .organizer-reports .dash-animate { animation:none; } }
@media print {
    .organizer-reports .page-header,
    .organizer-reports .report-filter-section,
    .organizer-reports .report-results-actions,
    .organizer-reports .sidebar,
    .organizer-reports .navbar { display:none !important; }
    .organizer-reports .report-results-section { border:1px solid #ddd; }
}
</style>
