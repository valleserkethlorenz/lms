<style>
  @keyframes fadeInUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
  .league-page{padding:20px 24px 45px;display:flex;flex-direction:column;min-height:calc(100vh - 70px)}
  .league-shell{padding:24px;border:1px solid #dce4ed;border-radius:4px;background:#fff;box-shadow:0 8px 24px rgba(16,24,40,.06);flex:1;display:flex;flex-direction:column}
  .league-shell > .page-header{flex-shrink:0}
  .league-shell > .activity-section{flex:1;display:flex;flex-direction:column}
  .league-shell > .activity-section > .table-responsive{flex:1}
  .dash-animate{animation:fadeInUp .55s ease-out both}
  .dash-animate-d1{animation-delay:.05s}.dash-animate-d2{animation-delay:.10s}
  .activity-section{border-radius:4px;border:none;box-shadow:0 4px 20px rgba(9,23,60,.08);overflow:hidden;background:#fff}
  .activity-header{padding:18px 22px 14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border-bottom:1px solid #eef1f7}
  .activity-title i{color:#2f6fed}
  .activity-badge-count{background:#e8edf7;color:#09173c;font-size:11px;font-weight:600;padding:3px 10px;border-radius:4px;white-space:nowrap}
  .activity-table{font-size:13.5px;margin:0}
  .activity-table thead th{background:#09173c;color:#fff;font-weight:500;font-size:11.5px;letter-spacing:.5px;text-transform:uppercase;padding:12px 16px;border:none;white-space:nowrap}
  .activity-table tbody tr{transition:background .2s ease}
  .activity-table tbody tr:hover{background:#f6f9fd}
  .activity-table tbody td{padding:14px 16px;vertical-align:middle;border-color:#eef1f7}
  .app-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:14px 16px;margin-bottom:16px;border:1px solid #e4e9f2;border-radius:4px;background:#fff;box-shadow:0 2px 10px rgba(16,24,40,.04)}
  .app-search{position:relative;flex:1 1 250px;max-width:330px;min-width:200px}
  .app-search > i{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:.85rem;color:#8b95a8;pointer-events:none}
  .app-search .form-control{height:40px;padding:0 38px 0 34px;border:1.5px solid #dde5ef;border-radius:4px;background:#f8fafc;font-size:.84rem;color:#1e293b!important;box-shadow:none}
  .app-search .form-control:focus{border-color:#1a3a7c;background:#fff;box-shadow:0 0 0 3px rgba(26,58,124,.1)}
  .app-search-clear{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:24px;height:24px;border:0;border-radius:50%;background:#e8edf6;color:#41506a;display:inline-flex;align-items:center;justify-content:center;font-size:.68rem;transition:background .15s ease,color .15s ease}
  .app-search-clear:hover{background:#09173c;color:#fff}
  .app-tabs{display:flex;flex-wrap:wrap;gap:6px}
  /* ── Table cells ─────────────────────────────────────────────────────── */
  .app-index{color:#9aa5b5;font-weight:600;font-size:.78rem}
  .app-league{display:flex;align-items:center;gap:11px;min-width:0}
  .app-league-icon{width:34px;height:34px;flex:0 0 34px;border-radius:4px;background:#eef3fb;color:#1a3a7c;display:inline-flex;align-items:center;justify-content:center;font-size:.95rem}
  .app-league-copy{display:flex;flex-direction:column;min-width:0}
  .app-league-name{display:block;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.86rem;font-weight:600;color:#09173c;line-height:1.3}
  .app-league-meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:3px;font-size:.7rem;color:#7b8798}
  .app-sep{width:3px;height:3px;border-radius:50%;background:#cbd3e0}
  .app-team{display:block;font-size:.86rem;font-weight:600;color:#152847;line-height:1.35;overflow-wrap:anywhere}
  .app-team-sub{display:block;margin-top:2px;font-size:.7rem;color:#8b95a8}
  .app-status{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:3px;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;white-space:nowrap}
  .app-status::before{content:"";width:5px;height:5px;border-radius:50%;background:currentColor}
  .app-status.is-approved{background:#e7f8ef;color:#0f7a4e}
  .app-status.is-pending{background:#fff4e0;color:#a16207}
  .app-status.is-rejected{background:#fde8ec;color:#b42335}
  .app-status.is-correction{background:#f1eafe;color:#6941c6}
  .app-status.is-cancelled,.app-status.is-expired,.app-status.is-default{background:#eef1f6;color:#5b6577}
  .app-actions{display:inline-flex;align-items:center;justify-content:center;gap:6px}
  .action-btn{display:inline-flex;align-items:center;gap:5px;background:#09173c;color:#fff;font-size:12px;font-weight:500;border-radius:4px;padding:5px 14px;border:1px solid #09173c;transition:background .2s ease,color .2s ease,box-shadow .2s ease,transform .2s ease;text-decoration:none;white-space:nowrap}
  .action-btn:hover{background:#1a3a7c;border-color:#1a3a7c;color:#fff;transform:translateY(-1px);box-shadow:0 4px 12px rgba(9,23,60,.18)}
  .action-btn.is-ghost{background:#fff;border-color:#dbe3ee;color:#41506a;padding:5px 10px}
  .action-btn.is-ghost:hover{background:#09173c;border-color:#09173c;color:#fff;box-shadow:none}
  .activity-table tbody tr.is-hidden{display:none!important}
  .app-empty{display:flex;flex-direction:column;align-items:center;gap:8px;padding:52px 24px;text-align:center}
  .app-empty-icon{display:inline-flex;align-items:center;justify-content:center;width:62px;height:62px;margin-bottom:2px;border-radius:50%;background:#eef3fb;color:#1a3a7c;font-size:1.45rem}
  .app-empty h5{font-size:1rem;font-weight:700;color:#152847;margin:0}
  .app-empty p{max-width:44ch;margin:0;font-size:.84rem;line-height:1.6;color:#7b8798}
  .app-empty-btn{display:inline-flex;align-items:center;gap:7px;margin-top:12px;padding:9px 16px;border:0;border-radius:4px;background:#245cbb;color:#fff!important;font-size:.8rem;font-weight:600;transition:transform .2s ease,box-shadow .2s ease}
  .app-empty-btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(9,23,60,.22)}
  .app-empty-sm{padding:40px 24px}
  .app-empty-sm .app-empty-icon{width:52px;height:52px;font-size:1.2rem;background:#f1f4fa;color:#63708a}

  /* ── Small screens: one card per application ─────────────────────────── */
  @media(max-width:767.98px){
    .league-page{padding:14px}
    .league-shell{padding:16px}
    .app-toolbar{padding:12px}
    .app-search{max-width:none;flex:1 1 100%}
    .app-tabs{width:100%}
    .activity-header{padding:16px}
    .activity-table thead{display:none}
    .activity-table tbody tr.app-row{display:block;margin:0 0 12px;border:1px solid #e7ecf3;border-radius:4px;background:#fff;box-shadow:0 2px 8px rgba(16,24,40,.04);overflow:hidden}
    .activity-table tbody tr.app-row:hover{background:#fff}
    .activity-table tbody tr.app-row td{display:block;width:100%;padding:10px 14px;border:0;text-align:left!important}
    .activity-table tbody tr.app-row td::before{display:block;content:attr(data-label);margin-bottom:4px;font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#96a1b2}
    .activity-table tbody tr.app-row td[data-label="#"]{display:none}
    .activity-table tbody tr.app-row td.app-cell-actions{padding-top:0}
    .activity-table tbody tr.app-row td.app-cell-actions::before{display:none}
    .app-cell-actions .app-actions{justify-content:flex-start}
    .app-league-name{max-width:100%}
    .app-empty-cell{display:block!important;padding:0!important}
    .app-empty-cell::before{display:none!important}
  }
  @media(prefers-reduced-motion:reduce){
    .dash-animate{animation:none!important}
    .action-btn,.app-tab,.app-empty-btn{transition:none!important}
    .action-btn:hover,.app-empty-btn:hover{transform:none}
  }

  .app-tab{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border:1px solid #e0e7f0;border-radius:3px;background:#fff;color:#4b5b70;font-size:.76rem;font-weight:600;transition:background .16s ease,border-color .16s ease,color .16s ease,box-shadow .16s ease}
  .app-tab:hover{border-color:#c3d2e6;background:#f6f9fd;color:#09173c}
  .app-tab.is-active{background:#09173c;border-color:#09173c;color:#fff;box-shadow:0 6px 14px -8px rgba(9,23,60,.85)}
  .app-tab:focus-visible{outline:2px solid #2f6fed;outline-offset:2px}
  .app-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:18px;padding:0 6px;border-radius:3px;background:#eef3fb;color:#41506a;font-size:.66rem;font-weight:700}
  .app-tab.is-active .app-tab-count{background:rgba(255,255,255,.18);color:#fff}
  .app-header-meta{display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap}
  .app-result-count{font-size:11px;font-weight:600;letter-spacing:.04em;color:#8b95a8;white-space:nowrap}
</style><style>
/* Flat application register, consistent with admin and organizer pages. */
.tr-applications { font-variant-numeric:tabular-nums; }
.tr-applications .league-shell { padding:0; border:0; background:transparent; box-shadow:none; }
.tr-applications .app-toolbar { box-shadow:none; padding:16px; gap:16px; }
.tr-applications .app-search .form-control { background:#fff; font-size:12px; border-width:1px; }
.tr-applications .app-search-clear { border-radius:3px; }
.tr-applications .app-search-clear[hidden] { display:none; }
.tr-applications .app-tab { border-radius:3px; font-size:11px; padding:8px 10px; }
.tr-applications .app-tab.is-active { background:#245cbb; border-color:#245cbb; box-shadow:none; }
.tr-applications .activity-section { border:1px solid #dce4ef; box-shadow:none; border-radius:4px; }
.tr-applications .activity-header { padding:20px 22px; }
.tr-applications .activity-table thead th { background:#eaf0f7; color:#53617a; font-size:10px; font-weight:600; letter-spacing:.09em; padding:13px 18px; }
.tr-applications .activity-table tbody td { padding:18px; }
.tr-applications .app-league-icon { border-radius:4px; background:#e4edff; color:#245cbb; }
.tr-applications .app-status { border-radius:3px; font-weight:600; font-size:10px; letter-spacing:0; padding:6px 8px; text-transform:none; }
.tr-applications .action-btn { background:#e8f0ff; border-color:#b9cef7; color:#245cbb; border-radius:3px; font-size:11px; padding:7px 11px; }
.tr-applications .action-btn:hover { background:#245cbb; border-color:#245cbb; color:#fff; box-shadow:none; transform:none; }
.tr-applications .action-btn.is-ghost { background:#fff; color:#53617a; border-color:#d6dfeb; }
.tr-applications .action-btn.is-ghost:hover { background:#edf2fc; color:#245cbb; border-color:#b9cef7; }
.tr-applications .app-empty-btn { background:#245cbb; border-radius:4px; text-decoration:none; box-shadow:none; }
.tr-applications .app-empty-btn:hover { background:#173f87; transform:none; box-shadow:none; }
.tr-applications .app-empty-icon { border-radius:4px; }
.tr-applications .dash-animate { animation:none; }
.tr-applications a:focus-visible,.tr-applications button:focus-visible,.tr-applications input:focus-visible { outline:2px solid #2f6fed; outline-offset:3px; }
@media(max-width:991px) {
.tr-applications .app-search { flex-basis:100%; max-width:none; }
}
@media(max-width:767px) {
.tr-applications .activity-section { background:transparent; }
.tr-applications .activity-header { background:#fff; padding:16px; }
.tr-applications .activity-table tbody tr.app-row { border-radius:4px; box-shadow:none; margin:12px; }
.tr-applications .activity-table tbody tr.app-row td { padding:10px 14px; }
.tr-applications .app-tabs { gap:6px; }
}
</style>
