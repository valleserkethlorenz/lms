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
                                <span class="report-form-note">Choose a report type and narrow the scope as needed.</span>
                                <div class="report-submit-actions">
                                    <button type="button" class="report-reset-btn" id="resetReport"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                                    <button type="submit" class="report-generate-btn" id="generateReport"><i class="bi bi-file-earmark-bar-graph"></i> Generate Report</button>
                                </div>
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
                        <div class="report-notice" id="reportNotice" role="status" aria-live="polite" hidden></div>
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
    (() => {
        'use strict';
        const form = document.getElementById('reportForm');
        const reportLeague = document.getElementById('reportLeague');
        const reportType = document.getElementById('reportType');
        const multiLeagueFilters = [...document.querySelectorAll('.multi-league-filter')];
        const reportDateFilters = [...document.querySelectorAll('.report-date-filter')];
        const reportFromLabel = document.getElementById('reportFromLabel');
        const reportToLabel = document.getElementById('reportToLabel');
        const table = document.getElementById('reportTable');
        const titleEl = document.getElementById('reportTitle');
        const filtersEl = document.getElementById('reportFilters');
        const generatedEl = document.getElementById('reportGenerated');
        const summaryEl = document.getElementById('reportSummary');
        const noticeEl = document.getElementById('reportNotice');
        const generateBtn = document.getElementById('generateReport');
        const resetBtn = document.getElementById('resetReport');
        const actionButtons = ['exportCsv', 'exportExcel', 'exportPdf', 'printReport']
            .map(id => document.getElementById(id));
        const dateFilterLabels = {
            registrations: ['Submitted From', 'Submitted To'],
            approved_teams: ['Approved From', 'Approved To'],
            rosters: ['Added From', 'Added To'],
            schedules: ['Match Date From', 'Match Date To'],
            results: ['Played From', 'Played To'],
            summary: ['Start Date From', 'Start Date To']
        };
        let reportRows = [];
        let reportMeta = {title: 'League Report', filters: {}, generated_at: ''};
        let lastRequest = '';
        let stale = false;
        let generating = false;

        function escapeReport(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }
        function isNumericValue(value) {
            return typeof value === 'number'
                || (typeof value === 'string' && /^-?(?:0|[1-9]\d*)(?:\.\d+)?$/.test(value.trim()));
        }
        function setNotice(message, kind = 'info') {
            if (!message) {
                noticeEl.hidden = true;
                noticeEl.textContent = '';
                noticeEl.className = 'report-notice';
                return;
            }
            noticeEl.hidden = false;
            noticeEl.className = 'report-notice report-notice-' + kind;
            noticeEl.textContent = message;
        }
        function updateActionButtons() {
            const enabled = reportRows.length > 0 && !stale && !generating;
            actionButtons.forEach(button => {
                if (button) button.disabled = !enabled;
            });
        }
        function markStale() {
            if (!lastRequest || stale) return;
            stale = true;
            setNotice('Filters changed — generate the report again to refresh the table and enable exports.', 'warning');
            updateActionButtons();
        }
        function updateReportFilterVisibility() {
            const useSpecificLeague = reportLeague.value !== '';
            multiLeagueFilters.forEach(wrapper => {
                wrapper.classList.toggle('d-none', useSpecificLeague);
                const control = wrapper.querySelector('select,input');
                if (useSpecificLeague && control) control.value = '';
            });
        }
        function updateDateFilterVisibility() {
            const supportsDateFilter = !['standings', 'participation'].includes(reportType.value);
            const dateLabels = dateFilterLabels[reportType.value] || ['From', 'To'];
            if (reportFromLabel) reportFromLabel.textContent = dateLabels[0];
            if (reportToLabel) reportToLabel.textContent = dateLabels[1];
            reportDateFilters.forEach(wrapper => {
                wrapper.classList.toggle('d-none', !supportsDateFilter);
                if (!supportsDateFilter) {
                    const input = wrapper.querySelector('input');
                    if (input) input.value = '';
                }
            });
        }
        reportLeague.addEventListener('change', () => { updateReportFilterVisibility(); markStale(); });
        reportType.addEventListener('change', () => { updateDateFilterVisibility(); markStale(); });
        form.addEventListener('change', markStale);
        form.addEventListener('input', event => {
            if (event.target && event.target.type === 'date') markStale();
        });
        updateReportFilterVisibility();
        updateDateFilterVisibility();
        function buildParams() {
            const params = new URLSearchParams(new FormData(form));
            [...params.keys()].forEach(key => {
                if (params.get(key) === '') params.delete(key);
            });
            return params;
        }
        function parseJsonResponse(text) {
            try {
                return JSON.parse(text);
            } catch (error) {
                throw new Error('The server returned an unexpected response. Your session may have expired — refresh and sign in again.');
            }
        }
        function renderEmpty(message) {
            reportRows = [];
            table.innerHTML = '<tbody><tr><td class="report-empty" colspan="20">'
                + '<i class="bi bi-inbox"></i><span>' + escapeReport(message) + '</span></td></tr></tbody>';
        }
        function renderTable() {
            if (!reportRows.length) {
                renderEmpty('No matching records found for the selected filters.');
                return;
            }
            const headers = Object.keys(reportRows[0]);
            const numeric = headers.map(header => reportRows.slice(0, 50).every(row => isNumericValue(row[header])));
            const head = '<thead><tr>' + headers.map(header =>
                '<th scope="col">' + escapeReport(header.replaceAll('_', ' ')) + '</th>'
            ).join('') + '</tr></thead>';
            const body = reportRows.map(row => '<tr>' + headers.map((header, index) =>
                '<td' + (numeric[index] ? ' class="report-number"' : '') + '>' + escapeReport(row[header]) + '</td>'
            ).join('') + '</tr>').join('');
            table.innerHTML = '<caption class="visually-hidden">' + escapeReport(reportMeta.title || 'League Report')
                + '</caption>' + head + '<tbody>' + body + '</tbody>';
        }
        async function generateReport(event) {
            event.preventDefault();
            if (generating) return;

            const params = buildParams();
            const from = params.get('from_date') || '';
            const to = params.get('to_date') || '';
            if (from && to && from > to) {
                Swal.fire('Invalid dates', 'From date cannot be later than To date.', 'warning');
                return;
            }

            generating = true;
            stale = false;
            updateActionButtons();
            generateBtn.disabled = true;
            generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating';
            const resultsSection = document.querySelector('.report-results-section');
            if (resultsSection) resultsSection.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch('action/reportAction.php?' + params.toString(), {
                    headers: {'Accept': 'application/json'},
                    credentials: 'same-origin'
                });
                const data = parseJsonResponse(await response.text());
                if (!response.ok || data.status !== 200) {
                    throw new Error(data.msg || 'Unable to generate report.');
                }

                reportRows = Array.isArray(data.rows) ? data.rows : [];
                reportMeta = data;
                lastRequest = params.toString();
                stale = false;

                titleEl.textContent = data.title || 'League Report';
                filtersEl.textContent = Object.entries(data.filters || {})
                    .map(([key, value]) => key + ': ' + value).join(' • ') || 'No filters applied';
                generatedEl.textContent = 'Generated: ' + (data.generated_at || '');
                renderTable();
                summaryEl.textContent = reportRows.length
                    ? reportRows.length.toLocaleString() + ' record(s) generated.'
                    : 'No matching records found.';

                if (data.truncated) {
                    setNotice('Showing the first ' + reportRows.length.toLocaleString()
                        + ' rows. Use Excel, CSV or PDF to download the complete report.', 'warning');
                } else {
                    setNotice('');
                }
            } catch (error) {
                reportRows = [];
                lastRequest = '';
                stale = false;
                titleEl.textContent = 'Report output';
                filtersEl.textContent = 'No filters applied';
                generatedEl.textContent = 'Not yet generated';
                renderEmpty('The report could not be generated.');
                summaryEl.textContent = 'The report could not be generated.';
                setNotice('');
                Swal.fire('Report error', error.message || 'Unable to generate report.', 'error');
            } finally {
                generating = false;
                generateBtn.disabled = false;
                generateBtn.innerHTML = '<i class="bi bi-file-earmark-bar-graph"></i> Generate Report';
                if (resultsSection) resultsSection.removeAttribute('aria-busy');
                updateActionButtons();
            }
        }
        async function downloadReport(url, fallbackName) {
            const response = await fetch(url, {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin'
            });
            const contentType = response.headers.get('content-type') || '';
            if (!response.ok) {
                let message = 'Unable to download the report.';
                if (contentType.includes('application/json')) {
                    try {
                        const data = await response.json();
                        message = data.msg || message;
                    } catch (ignored) { /* keep the default message */ }
                }
                throw new Error(message);
            }
            if (contentType.includes('application/json')) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.msg || 'Unable to download the report.');
            }
            if (!/(spreadsheetml|application\/pdf|text\/csv|octet-stream)/i.test(contentType)) {
                throw new Error('The server returned an unexpected response. Please try again.');
            }

            const blob = await response.blob();
            const disposition = response.headers.get('content-disposition') || '';
            const match = /filename="?([^";]+)"?/i.exec(disposition);
            const filename = match ? match[1] : fallbackName;
            const anchor = document.createElement('a');
            const href = URL.createObjectURL(blob);
            anchor.href = href;
            anchor.download = filename;
            anchor.rel = 'noopener';
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            setTimeout(() => URL.revokeObjectURL(href), 1500);
        }
        async function handleDownload(url, fallbackName) {
            if (!lastRequest || stale || generating) return;
            generating = true;
            updateActionButtons();
            try {
                await downloadReport(url, fallbackName);
            } catch (error) {
                Swal.fire('Export error', error.message || 'Unable to download the report.', 'error');
            } finally {
                generating = false;
                updateActionButtons();
            }
        }
        form.addEventListener('submit', generateReport);
        document.getElementById('exportCsv').addEventListener('click', () => {
            if (lastRequest) handleDownload('action/exportCsv.php?' + lastRequest, 'league-report.csv');
        });
        document.getElementById('exportExcel').addEventListener('click', () => {
            if (lastRequest) handleDownload('action/exportExcel.php?' + lastRequest, 'league-report.xlsx');
        });
        document.getElementById('exportPdf').addEventListener('click', () => {
            if (lastRequest) handleDownload('action/exportPdf.php?' + lastRequest, 'league-report.pdf');
        });
        document.getElementById('printReport').addEventListener('click', () => {
            if (!lastRequest || stale || generating) return;
            const printWindow = window.open('printReport.php?' + lastRequest + '&auto=1', '_blank');
            if (printWindow) {
                printWindow.opener = null;
            } else {
                Swal.fire('Popup blocked', 'Allow popups for this site to open the printable report.', 'warning');
            }
        });
        resetBtn.addEventListener('click', () => {
            if (generating) return;
            form.reset();
            reportRows = [];
            reportMeta = {title: 'League Report', filters: {}, generated_at: ''};
            lastRequest = '';
            stale = false;
            titleEl.textContent = 'Report output';
            filtersEl.textContent = 'No filters applied';
            generatedEl.textContent = 'Not yet generated';
            summaryEl.textContent = 'Choose filters above and click "Generate Report".';
            setNotice('');
            renderEmpty('Choose filters above and click "Generate Report".');
            updateReportFilterVisibility();
            updateDateFilterVisibility();
            updateActionButtons();
        });
        renderEmpty('Choose filters above and click "Generate Report".');
        updateActionButtons();
    })();
    </script>

</body>
</html>
