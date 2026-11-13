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
.status-badge{display:inline-block;padding:4px 12px;border-radius:4px;font-size:11px;font-weight:600;letter-spacing:.3px;text-transform:uppercase}
.status-badge-approved{background:#e8f7ee;color:#18794e}
.status-badge-pending{background:#fff4d6;color:#8a5a00}
.status-badge-rejected{background:#fde8ec;color:#b42335}
.status-badge-cancelled{background:#eef1f4;color:#4b5563}
.status-badge-correction{background:#f1eafe;color:#6941c6}
.status-badge-expired{background:#f3f4f6;color:#374151}
.status-badge-default{background:#eaf2fb;color:#245b91}
.activity-empty{padding:48px 24px;text-align:center}
.activity-empty i{font-size:3rem;color:#d1d5db;margin-bottom:12px}
.activity-empty p{color:#9ca3af;font-size:14px;margin:0}
.section-label{font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#09173c;margin-bottom:12px;padding-left:2px}
.live-clock{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);border-radius:6px;padding:8px 16px;font-size:13px;color:rgba(255,255,255,.85);border:1px solid rgba(255,255,255,.12)}
.live-clock .clock-dot{width:8px;height:8px;border-radius:50%;background:#34d399}
.calendar-section{border-radius:10px;border:none;box-shadow:0 4px 20px rgba(9,23,60,.08);overflow:hidden;background:#fff}
.calendar-section .calendar-header{padding:20px 24px 12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.calendar-section .calendar-title{font-size:15px;font-weight:600;color:#09173c;display:flex;align-items:center;gap:8px}
.calendar-legend{display:flex;flex-wrap:wrap;gap:10px 18px;color:#52606d;font-size:.82rem}
.calendar-legend span{display:inline-flex;align-items:center;gap:6px}
.calendar-dot{width:10px;height:10px;border-radius:50%;display:inline-block}
#representativeCalendar{min-height:620px}
#representativeCalendar .fc-toolbar-title{color:#09173c;font-size:1.35rem;font-weight:700}
#representativeCalendar .fc-button-primary{background:#09173c;border-color:#09173c}
#representativeCalendar .fc-event{cursor:pointer;border:0;padding:2px 4px}
#representativeCalendar .fc-list-event.timeline-milestone .fc-list-event-time{visibility:hidden}
@media(max-width:767.98px){
    #representativeCalendar{min-height:520px}
    #representativeCalendar .fc-toolbar{align-items:flex-start;gap:10px}
    #representativeCalendar .fc-toolbar.fc-header-toolbar{flex-direction:column}
}
</style>