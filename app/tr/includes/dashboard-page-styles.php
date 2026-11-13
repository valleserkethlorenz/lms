<?php // Dashboard-only presentation; shared representative pages keep their existing styles. ?>
<style>
.tr-dashboard{--tr-ink:#09173c;--tr-line:#e1e6ee;--tr-muted:#647084;color:var(--tr-ink);font-variant-numeric:tabular-nums;max-width:1600px;margin:0 auto;min-width:0}
.tr-dashboard a{text-decoration:none}
.tr-dashboard a:focus-visible,.tr-dashboard button:focus-visible{outline:2px solid #2f6fed;outline-offset:4px}
.tr-overview{display:flex;align-items:center;justify-content:space-between;gap:28px;padding:30px 32px;background:#09173c;color:#fff;border-radius:4px;border-left:4px solid #547dc0;margin-bottom:26px}
.tr-overview-copy{min-width:0}
.tr-eyebrow,.tr-panel-eyebrow{display:block;font-size:10px;font-weight:600;letter-spacing:.14em;text-transform:uppercase}
.tr-eyebrow{color:#b2c2de;margin-bottom:12px}
.tr-overview h1{font-size:clamp(1.45rem,2.3vw,2rem);font-weight:600;letter-spacing:-.035em;line-height:1.25;margin:0 0 10px;color:#fff;overflow-wrap:anywhere}
.tr-overview p{font-size:12.5px;line-height:1.7;color:#c2cce0;margin:0 0 18px}
.tr-browse{display:inline-flex;align-items:center;gap:20px;background:#fff;color:#09173c;padding:9px 13px;border:1px solid #fff;border-radius:3px;font-size:12px;font-weight:500}
.tr-browse:hover{background:#e6edf8;color:#09173c}
.tr-overview-date{display:flex;flex-direction:column;gap:8px;border-left:1px solid #354568;padding:10px 0 10px 28px;flex-shrink:0}
.tr-date-label{color:#c2cce0;font-size:11px}
.tr-time{font-size:24px;font-weight:500;letter-spacing:.025em;color:#fff}
.tr-date-caption{font-size:10px;color:#a8b8d4}
.tr-section-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
.tr-section-heading h2{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.12em;margin:0}
.tr-section-heading>span{font-size:11px;color:var(--tr-muted)}
.tr-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border:1px solid var(--tr-line);border-radius:4px;background:#fff;margin-bottom:26px;overflow:hidden}
.tr-metric{position:relative;padding:21px 24px;color:var(--tr-ink);min-width:0;border-top:2px solid var(--metric-accent,#09173c)}
.tr-metric+.tr-metric{border-left:1px solid var(--tr-line)}
a.tr-metric:hover{background:#f5f7fb;color:var(--tr-ink)}
.tr-metric-label{display:flex;align-items:center;gap:9px;font-size:10px;letter-spacing:.1em;text-transform:uppercase;font-weight:600;color:#536079}
.tr-metric-label i{color:var(--metric-accent,#09173c);font-size:14px}
.tr-metric strong{display:block;font-size:34px;line-height:1.2;font-weight:600!important;letter-spacing:-.04em;margin:12px 0 6px;color:var(--tr-ink)}
.tr-metric-note{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:11px;color:var(--tr-muted)}
.tr-metric-pending{--metric-accent:#a56a14}.tr-metric-approved{--metric-accent:#18794e}.tr-metric-rejected{--metric-accent:#b42335}
.tr-dashboard .calendar-section,.tr-dashboard .activity-section{border:1px solid var(--tr-line);border-radius:4px;box-shadow:none;margin-bottom:24px!important}
.tr-dashboard .calendar-header,.tr-dashboard .activity-header{padding:20px 24px 18px;border-bottom:1px solid var(--tr-line);align-items:center;gap:20px}
.tr-panel-eyebrow{color:var(--tr-muted);font-size:9px;margin-bottom:6px}
.tr-dashboard .calendar-title,.tr-dashboard .activity-title{font-size:17px;letter-spacing:-.02em;margin:0;font-weight:600}
.tr-panel-description{margin:6px 0 0;font-size:11px;line-height:1.6;color:var(--tr-muted)}
.tr-dashboard .calendar-legend{max-width:470px;gap:8px 14px;font-size:10px;color:#536079}
.tr-dashboard .calendar-dot{width:7px;height:7px;border-radius:1px}
.tr-dashboard .activity-view-all{background:#fff;color:#09173c;border:1px solid #ccd4e1;border-radius:3px;padding:7px 12px;font-size:11px}
.tr-dashboard .activity-view-all:hover{background:#09173c;color:#fff;transform:none;box-shadow:none}
.tr-dashboard .activity-table{font-size:12px}
.tr-dashboard .activity-table thead th{background:#f4f6fa;color:#59657a;font-size:9px;letter-spacing:.1em;font-weight:600;padding:12px 18px;border-bottom:1px solid var(--tr-line)}
.tr-dashboard .activity-table tbody td{padding:15px 18px;border-color:#edf0f5}
.tr-dashboard .activity-table tbody tr:hover{background:#f8fafd}
.tr-dashboard .status-badge{border-radius:3px;font-size:9px;letter-spacing:.04em;padding:5px 8px}
.tr-dashboard .activity-empty{padding:42px 20px}
.tr-dashboard .activity-empty i{display:block;font-size:26px;color:#8795ac;margin-bottom:10px}
.tr-dashboard .activity-empty p{font-size:12px;line-height:1.7;color:var(--tr-muted);max-width:48ch;margin:auto}
.tr-dashboard .dash-animate{animation:none}
.tr-dashboard #representativeCalendar{min-height:0;--fc-border-color:#e4e9f0;--fc-today-bg-color:#f1f5fc;--fc-neutral-bg-color:#f7f9fc;font-size:12px}
.tr-dashboard #representativeCalendar a{color:#41506a}
.tr-dashboard #representativeCalendar .fc-toolbar-title{font-size:17px;font-weight:500}
.tr-dashboard #representativeCalendar .fc-button{border-radius:3px;box-shadow:none;font-size:11px;padding:7px 11px;text-transform:none}
.tr-dashboard #representativeCalendar .fc-button-primary{background:#fff;color:#41506a;border-color:#cfd7e3}
.tr-dashboard #representativeCalendar .fc-button-primary:not(:disabled):hover,.tr-dashboard #representativeCalendar .fc-button-active{background:#09173c;color:#fff;border-color:#09173c}
.tr-dashboard #representativeCalendar .fc-col-header-cell-cushion{font-size:10px;font-weight:500;padding:10px 4px}
.tr-dashboard #representativeCalendar .fc-event{border-radius:2px;font-size:10px}
@media(max-width:991.98px){.tr-overview{padding:24px}.tr-overview-date{padding-left:20px}.tr-metric{padding:18px}.tr-dashboard .calendar-header{align-items:flex-start;flex-direction:column}.tr-dashboard .calendar-legend{max-width:none}}
@media(max-width:575.98px){.tr-overview{flex-direction:column;align-items:stretch;padding:22px 18px;gap:20px}.tr-overview-date{border-left:0;border-top:1px solid #354568;padding:16px 0 0}.tr-time{font-size:21px}.tr-date-caption{display:none}.tr-section-heading{align-items:flex-start;flex-direction:column;gap:5px}.tr-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.tr-metric{padding:16px 14px}.tr-metric:nth-child(3){border-left:0}.tr-metric:nth-child(n+3){border-top:1px solid var(--tr-line)}.tr-metric-label{font-size:9px;letter-spacing:.05em}.tr-metric strong{font-size:29px}.tr-metric-note{font-size:10px}.tr-dashboard .calendar-header,.tr-dashboard .activity-header{padding:18px 16px}.tr-dashboard .activity-table{min-width:650px}.tr-dashboard #representativeCalendar .fc-toolbar{gap:12px}.tr-dashboard #representativeCalendar .fc-toolbar-chunk{max-width:100%}}
@media(prefers-reduced-motion:reduce){.tr-dashboard *{animation:none!important;transition:none!important}}
</style>
