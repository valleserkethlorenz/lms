<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';

requireRole([1]);
requireActiveAccount($conn);
$userId = currentUserId();
$full = currentUserName();
$userType = currentRole();
include 'includes/header.php';
?>

<body id="page-top" class="body organizer-reports">
    <?php include 'includes/bar.php'; ?>
    <?php include 'includes/dashboard-styles.php'; ?>

    <?php include 'includes/report-page-styles.php'; ?>

    <div class="container-fluid" style="padding-top:20px;min-height:800px;">
        <div class="row p-3">
            <div class="card cards" style="background:transparent;box-shadow:none;border:none;">
                <div class="card-body p-0">
                    

                    <!-- Filter Section -->
                    
                    <div class="report-filter-section dash-animate dash-animate-d2 mb-4">
                        <div class="report-filter-header">
                            <div><span class="section-eyebrow">01 / Configure</span><h2 class="section-title">Build your report</h2></div><div class="section-meta"><i class="bi bi-info-circle"></i> All filters are optional </div>
                        </div>
                        <div class="report-filter-body">
                            <form id="reportForm" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">League</label>
                                <select name="league_id" id="reportLeague" class="form-select">
                                    <option value="">All Leagues</option>
                                    <?php
                                    $leagueStmt = $conn->prepare("SELECT id,league_name FROM tbl_league WHERE created_by=? AND (is_archived=0 OR is_archived IS NULL) ORDER BY league_name");
                                    $leagueStmt->bind_param('i', $userId); $leagueStmt->execute();
                                    $res = $leagueStmt->get_result();
                                    while ($r = $res->fetch_assoc()) {
                                        echo '<option value="' . $r['id'] . '">' . htmlspecialchars($r['league_name']) . '</option>';
                                    }
                                    $leagueStmt->close();
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 multi-league-filter">
                                <label class="form-label">Sport</label>
                                <select name="sport" class="form-select">
                                    <option value="">All Sports</option>
                                    <?php $u=$conn->query("SELECT name FROM tbl_utility WHERE category='sport' AND is_active=1 ORDER BY name"); while($row=$u->fetch_assoc()): ?><option><?= htmlspecialchars($row['name']) ?></option><?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 multi-league-filter">
                                <label class="form-label">Division</label>
                                <select name="division" class="form-select">
                                    <option value="">All Divisions</option>
                                    <?php $u=$conn->query("SELECT name FROM tbl_utility WHERE category='division' AND is_active=1 ORDER BY name"); while($row=$u->fetch_assoc()): ?><option><?= htmlspecialchars($row['name']) ?></option><?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-4 multi-league-filter">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select"><option value="">All Categories</option><?php $u=$conn->query("SELECT name FROM tbl_utility WHERE category='category' AND is_active=1 ORDER BY name"); while($row=$u->fetch_assoc()): ?><option><?= htmlspecialchars($row['name']) ?></option><?php endwhile; ?></select>
                            </div>
                            <div class="col-md-4 multi-league-filter">
                                <label class="form-label">League Status</label>
                                <select name="league_status" class="form-select"><option value="">All Statuses</option><option>Draft</option><option>Upcoming</option><option>Open</option><option>Ongoing</option><option>Completed</option></select>
                            </div>

                            <div class="col-md-2 report-date-filter">
                                <label class="form-label" id="reportFromLabel">From</label>
                                <input type="date" name="from_date" class="form-control">
                            </div>
                            <div class="col-md-2 report-date-filter">
                                <label class="form-label" id="reportToLabel">To</label>
                                <input type="date" name="to_date" class="form-control">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Report Type</label>
                                <select name="report_type" id="reportType" class="form-select">
                                    <option value="registrations">Team Registrations</option>
                                    <option value="approved_teams">Approved Teams</option>
                                    <option value="rosters">Team Rosters</option>
                                    <option value="schedules">Published Match Schedules</option>
                                    <option value="results">Official Match Results</option>
                                    <option value="standings">League Standings</option>
                                    <option value="participation">Participation</option>
                                    <option value="summary">League Summary</option>
                                </select>
                            </div>

                            <div class="col-12 report-submit-row">
                                <span class="report-form-note">Choose a report type and narrow the scope as needed.</span><button type="submit" class="report-generate-btn" id="generateReport"><i class="bi bi-file-earmark-bar-graph"></i> Generate Report</button>
                            </div>
                        </form>
                        </div>
                    </div>

                    <!-- Results Section -->
                    
                    <div class="report-results-section dash-animate dash-animate-d4 mb-4">
                        <div class="report-results-header">
                            <div>
                                <span class="section-eyebrow">02 / Review &amp; export</span><h2 class="section-title"><span id="reportTitle">Report output</span></h2>
                            </div>
                            <div class="report-results-actions">
                                <button type="button" id="exportCsv" class="report-action-btn" disabled><i class="bi bi-filetype-csv"></i> CSV</button>
                                <button type="button" id="exportExcel" class="report-action-btn" disabled style="border-color:#1d6f42;color:#1d6f42;"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
                                <button type="button" id="exportPdf" class="report-action-btn" disabled style="border-color:#dc2626;color:#dc2626;"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                                <button type="button" id="printReport" class="report-action-btn report-action-btn-print" disabled><i class="bi bi-printer"></i> Print</button>
                            </div>
                        </div>
                        <div class="report-meta">
                            <span class="report-meta-item"><i class="bi bi-funnel"></i> <span id="reportFilters">No filters applied</span></span>
                            <span class="report-meta-item"><i class="bi bi-clock"></i> <span id="reportGenerated">Not yet generated</span></span>
                        </div>
                        <div class="report-summary" id="reportSummary" role="status" aria-live="polite">Choose filters above and click "Generate Report".</div>
                        <div class="report-table-wrapper">
                            <table class="report-table" id="reportTable"></table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>

    <style>
        #reportTable th { white-space:nowrap; background:#eef2f7; text-transform:capitalize; }
        #reportTable td { vertical-align:middle; }
        @media print {
            body * { visibility:hidden !important; }
            .report-results-section, .report-results-section * { visibility:visible !important; }
            .report-results-section { position:absolute;left:0;top:0;width:100%;margin:0 !important;color:#000; }
            #reportTable { font-size:10px; }
            #reportTable th { background:#eee !important;color:#000 !important; }
            .report-table-wrapper { overflow:visible !important; }
        }
    </style>

    <script>
        let reportRows = [];
        let reportMeta = {title:'League Report',filters:{},generated_at:''};
        const reportLeague=document.getElementById('reportLeague');
        const multiLeagueFilters=[...document.querySelectorAll('.multi-league-filter')];
        function updateReportFilterVisibility(){
            const useSpecificLeague=reportLeague.value!=='';
            multiLeagueFilters.forEach(wrapper=>{
                wrapper.classList.toggle('d-none',useSpecificLeague);
                const control=wrapper.querySelector('select,input');
                if(useSpecificLeague&&control) control.value='';
            });
        }
        reportLeague.addEventListener('change',updateReportFilterVisibility);
        updateReportFilterVisibility();
        const reportType=document.getElementById('reportType');
        const reportDateFilters=[...document.querySelectorAll('.report-date-filter')];const reportFromLabel=document.getElementById('reportFromLabel');const reportToLabel=document.getElementById('reportToLabel');const dateFilterLabels={registrations:['Submitted From','Submitted To'],approved_teams:['Approved From','Approved To'],rosters:['Added From','Added To'],schedules:['Match Date From','Match Date To'],results:['Played From','Played To'],summary:['Start Date From','Start Date To']};
        function updateDateFilterVisibility(){
            const supportsDateFilter=!['standings','participation'].includes(reportType.value);const dateLabels=dateFilterLabels[reportType.value]||['From','To'];if(reportFromLabel)reportFromLabel.textContent=dateLabels[0];if(reportToLabel)reportToLabel.textContent=dateLabels[1];
            reportDateFilters.forEach(wrapper=>{
                wrapper.classList.toggle('d-none',!supportsDateFilter);
                const input=wrapper.querySelector('input');
                if(!supportsDateFilter&&input) input.value='';
            });
        }
        reportType.addEventListener('change',updateDateFilterVisibility);
        updateDateFilterVisibility();
        document.getElementById('reportForm').addEventListener('submit', async function(e){
            e.preventDefault();
            const formData=new FormData(this), from=formData.get('from_date'), to=formData.get('to_date');
            if(from&&to&&from>to) return Swal.fire('Invalid dates','From date cannot be later than To date.','warning');
            const button=document.getElementById('generateReport');button.disabled=true;button.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Generating';
            try {
            const response = await fetch('action/reportAction.php?' + new URLSearchParams(formData),{headers:{'Accept':'application/json'}});
            const data = await response.json();
            if (!response.ok || data.status !== 200) throw new Error(data.msg || 'Unable to generate report.');
            reportRows = Array.isArray(data.rows)?data.rows:[]; reportMeta=data;
            document.getElementById('reportTitle').textContent=data.title||'League Report';
            document.getElementById('reportFilters').textContent=Object.entries(data.filters||{}).map(([key,value])=>`${key}: ${value}`).join(' • ');
            document.getElementById('reportGenerated').textContent=`Generated: ${data.generated_at||''}`;
            const table = document.getElementById('reportTable');
            if (!reportRows.length) {
                table.innerHTML=''; document.getElementById('reportSummary').textContent='No matching records found.';
            } else {
                const headers=Object.keys(reportRows[0]);
                table.innerHTML='<thead><tr>'+headers.map(h=>`<th>${h.replaceAll('_',' ')}</th>`).join('')+'</tr></thead><tbody>'+reportRows.map(r=>'<tr>'+headers.map(h=>`<td>${escapeReport(r[h])}</td>`).join('')+'</tr>').join('')+'</tbody>';
                document.getElementById('reportSummary').textContent=`${reportRows.length} record(s) generated.`;
            }
            document.getElementById('exportCsv').disabled=!reportRows.length;
            document.getElementById('exportExcel').disabled=!reportRows.length;
            document.getElementById('exportPdf').disabled=!reportRows.length;
            document.getElementById('printReport').disabled=!reportRows.length;
            } catch(error) { reportRows=[];document.getElementById('exportCsv').disabled=true;document.getElementById('exportExcel').disabled=true;document.getElementById('exportPdf').disabled=true;document.getElementById('printReport').disabled=true;Swal.fire('Report error',error.message,'error'); }
            finally { button.disabled=false;button.innerHTML='<i class="bi bi-file-earmark-bar-graph"></i> Generate Report'; }
        });
        function escapeReport(value){ const d=document.createElement('div'); d.textContent=value ?? ''; return d.innerHTML; }
        document.getElementById('exportCsv').addEventListener('click', function(){
            if(!reportRows.length) return; const headers=Object.keys(reportRows[0]);
            const quote=v=>'"'+String(v ?? '').replaceAll('"','""')+'"';
            const csv=[headers.map(h=>quote(h.replaceAll('_',' '))).join(','),...reportRows.map(r=>headers.map(h=>quote(r[h])).join(','))].join('\r\n');
            const a=document.createElement('a'); a.href=URL.createObjectURL(new Blob(['\ufeff'+csv],{type:'text/csv;charset=utf-8'})); a.download=(reportMeta.title||'league-report').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')+'.csv'; a.click(); setTimeout(()=>URL.revokeObjectURL(a.href),0);
        });
        document.getElementById('exportExcel').addEventListener('click', function(){
            const form=document.getElementById('reportForm');
            const params=new URLSearchParams(new FormData(form));
            window.location.href='action/exportExcel.php?'+params.toString();
        });
        document.getElementById('exportPdf').addEventListener('click', function(){
            const form=document.getElementById('reportForm');
            const params=new URLSearchParams(new FormData(form));
            window.location.href='action/exportPdf.php?'+params.toString();
        });
        document.getElementById('printReport').addEventListener('click', function(){const form=document.getElementById('reportForm');const params=new URLSearchParams(new FormData(form));params.set('auto','1');window.open('printReport.php?'+params.toString(),'_blank');});
    </script>

</body>
</html>
