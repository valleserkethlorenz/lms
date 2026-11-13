<style>
@keyframes fadeInUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
@keyframes countUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
/* pulseGlow removed — neon glow eliminated in UI overhaul */
.dash-animate{animation:fadeInUp .55s ease-out both}
.dash-animate-d1{animation-delay:.05s}.dash-animate-d2{animation-delay:.10s}.dash-animate-d3{animation-delay:.15s}
.dash-animate-d4{animation-delay:.20s}.dash-animate-d5{animation-delay:.25s}.dash-animate-d6{animation-delay:.30s}
.dash-animate-d7{animation-delay:.35s}.dash-animate-d8{animation-delay:.40s}
.welcome-banner{background:linear-gradient(135deg,#09173c 0%,#1a3a7c 100%);border-radius:10px;padding:32px 36px;color:#fff;position:relative;overflow:hidden;box-shadow:0 6px 20px rgba(9,23,60,.16)}
.welcome-banner::before{content:none}
.welcome-banner::after{content:none}
.welcome-greeting{font-size:1.75rem;font-weight:600;letter-spacing:.3px;margin-bottom:4px}
.welcome-date{font-size:13px;color:rgba(255,255,255,.6);font-weight:400}
.welcome-avatar{width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:1.8rem;border:2px solid rgba(255,255,255,.2);flex-shrink:0}
.stat-card{border-radius:10px;border:none;box-shadow:0 4px 20px rgba(9,23,60,.08);overflow:hidden;transition:all .3s cubic-bezier(.4,0,.2,1);cursor:default;position:relative}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 8px 22px rgba(9,23,60,.14)}
.stat-card-link{text-decoration:none!important;color:inherit!important}
.stat-card .stat-gradient-bar{height:5px;transition:height .3s ease}
.stat-card:hover .stat-gradient-bar{height:7px}
.stat-card .stat-icon-wrap{width:52px;height:52px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;transition:all .3s ease}
.stat-card:hover .stat-icon-wrap{transform:scale(1.1) rotate(-3deg)}
.stat-card .stat-value{font-size:1.75rem;font-weight:700;line-height:1;color:#09173c;animation:countUp .5s ease-out .3s both}
.stat-card .stat-label{font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#6b7280;font-weight:500;margin-top:4px}
.stat-card .stat-arrow{position:absolute;top:16px;right:16px;opacity:0;transition:all .3s ease;color:#6b7280;font-size:14px}
.stat-card:hover .stat-arrow{opacity:1;transform:translateX(2px)}
.quick-action-card{border-radius:10px;border:none;box-shadow:0 4px 20px rgba(9,23,60,.08);overflow:hidden;transition:all .3s ease;background:#fff}
.quick-action-card:hover{box-shadow:0 8px 28px rgba(9,23,60,.14)}
.quick-action-btn{display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:8px;border:1.5px solid #e5e7eb;background:#fff;color:#09173c;text-decoration:none;transition:all .25s ease;width:100%}
.quick-action-btn:hover{border-color:#2563eb;background:linear-gradient(135deg,#f0f5ff,#e8edf7);color:#09173c;transform:translateX(4px);box-shadow:0 4px 14px rgba(37,99,235,.10)}
.quick-action-btn .qa-icon{width:44px;height:44px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;transition:transform .25s ease}
.quick-action-btn:hover .qa-icon{transform:scale(1.1)}
.quick-action-btn .qa-text{font-size:13.5px;font-weight:500;line-height:1.3}
.quick-action-btn .qa-sub{font-size:11px;color:#6b7280;font-weight:400;margin-top:1px}
.quick-action-btn .qa-arrow{margin-left:auto;opacity:0;transition:all .25s ease;color:#2563eb}
.quick-action-btn:hover .qa-arrow{opacity:1;transform:translateX(3px)}
.activity-section{border-radius:10px;border:none;box-shadow:0 4px 20px rgba(9,23,60,.08);overflow:hidden;background:#fff}
.activity-header{padding:20px 24px 12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.activity-badge-count{background:#e8edf7;color:#09173c;font-size:11px;font-weight:600;padding:3px 10px;border-radius:4px}
.activity-view-all{font-size:12.5px;font-weight:500;color:#fff;background:#09173c;border:none;border-radius:6px;padding:7px 18px;text-decoration:none;transition:all .2s ease;display:inline-flex;align-items:center;gap:6px}
.activity-view-all:hover{background:#1a3a7c;color:#fff;transform:translateY(-1px);box-shadow:0 4px 12px rgba(9,23,60,.18)}
.activity-table{font-size:13.5px;margin:0}
.activity-table thead th{background:#09173c;color:#fff;font-weight:500;font-size:12px;letter-spacing:.5px;text-transform:uppercase;padding:12px 16px;border:none;white-space:nowrap}
.activity-table tbody tr{transition:background .2s ease}
.activity-table tbody tr:hover{background:#f0f5ff}
.activity-table tbody td{padding:12px 16px;vertical-align:middle;border-color:#f0f0f0}
.role-badge{display:inline-block;padding:4px 12px;border-radius:4px;font-size:11px;font-weight:600;letter-spacing:.3px;text-transform:uppercase}
.role-badge-organizer{background:#dbeafe;color:#1e40af}
.role-badge-committee{background:#d1fae5;color:#065f46}
.role-badge-admin{background:#ede9fe;color:#5b21b6}
.role-badge-default{background:#f3f4f6;color:#374151}
.activity-empty{padding:48px 24px;text-align:center}
.activity-empty i{font-size:3rem;color:#d1d5db;margin-bottom:12px}
.activity-empty p{color:#9ca3af;font-size:14px;margin:0}
.section-label{font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#09173c;margin-bottom:12px;padding-left:2px}
.live-clock{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);border-radius:6px;padding:8px 16px;font-size:13px;color:rgba(255,255,255,.85);border:1px solid rgba(255,255,255,.12)}
.live-clock .clock-dot{width:8px;height:8px;border-radius:50%;background:#34d399}
</style>