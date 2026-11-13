<?php
require_once __DIR__ . '/../../config/dbconnect.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../config/teamRegistrationStatus.php';
requireRole([2]);
requireActiveAccount($conn);
$userId = currentUserId();
$full = currentUserName();
$userType = currentRole();

require_once __DIR__ . '/../../config/leagueStatus.php';
require_once __DIR__ . '/../../includes/summaryStrip.php';

$loggin = true;
    $time = strtotime("-10 year", time());
    $date = date("Y-m-d", $time);

    $today = date("M/d/Y");
    include 'includes/header.php';
?>
<?php include 'includes/application-page-styles.php'; ?>
<?php include_once __DIR__ . '/../../includes/summary-strip-styles.php'; ?>

    <body id="page-top" class="body tr-applications">
        <?php include 'includes/bar.php'; ?>
        <div class="league-page">
            <div class="league-shell">
                <?php
                // ── Applications submitted by this representative ─────────────────
                $appStatusKey = static function ($status): string {
                    $key = strtolower(trim((string) $status));
                    if ($key === 'correction requested' || $key === 'needs correction' || $key === 'correction') return 'correction';
                    if ($key === 'canceled') return 'cancelled';
                    return in_array($key, ['pending','approved','rejected','cancelled','expired'], true) ? $key : 'default';
                };
                $appRows = [];
                $appCounts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'correction' => 0, 'rejected' => 0, 'cancelled' => 0, 'expired' => 0];
                $appStmt = $conn->prepare(
                    "SELECT t.id AS team_id, t.team_name, t.status, t.created,
                            l.id AS league_id, l.league_name, l.sport, l.division, l.category
                       FROM tbl_team t
                       INNER JOIN tbl_league l ON l.id = t.league_id
                      WHERE t.user_id = ?
                      ORDER BY t.id DESC"
                );
                if ($appStmt) {
                    $appStmt->bind_param('i', $userId);
                    $appStmt->execute();
                    $appRes = $appStmt->get_result();
                    while ($appRow = $appRes->fetch_assoc()) {
                        $appRow['status_key'] = $appStatusKey($appRow['status'] ?? '');
                        $appRows[] = $appRow;
                        $appCounts['all']++;
                        if (isset($appCounts[$appRow['status_key']])) $appCounts[$appRow['status_key']]++;
                    }
                    $appStmt->close();
                }
                $totalRegs = count($appRows);

                // Status tabs: "All" is always shown, the rest only when in use.
                $appTabs = [
                    ['key' => 'all',        'label' => 'All'],
                    ['key' => 'pending',    'label' => 'Pending'],
                    ['key' => 'approved',   'label' => 'Approved'],
                    ['key' => 'correction', 'label' => 'Needs correction'],
                    ['key' => 'rejected',   'label' => 'Rejected'],
                    ['key' => 'cancelled',  'label' => 'Cancelled'],
                    ['key' => 'expired',    'label' => 'Expired'],
                ];
                $appTabs = array_values(array_filter($appTabs, static fn($tab) => $tab['key'] === 'all' || ($appCounts[$tab['key']] ?? 0) > 0));
                ?>



                <?php include 'includes/registration-drafts-panel.php'; ?>

                <?php renderSummaryStrip([
                    ['label' => 'Applications',     'value' => $totalRegs,               'note' => 'All submitted teams'],
                    ['label' => 'Pending review',   'value' => $appCounts['pending'],    'note' => 'Awaiting a decision'],
                    ['label' => 'Approved',         'value' => $appCounts['approved'],   'note' => 'Accepted registrations'],
                    ['label' => 'Needs correction', 'value' => $appCounts['correction'], 'note' => 'Updates requested'],
                ], 'Application totals'); ?>
                <div class="section-head">
                    <div><span class="section-eyebrow">Registration history</span><h2 class="section-title">Your applications</h2></div>
                    
                </div>
                <?php if ($totalRegs > 0): ?>
                <div class="app-toolbar dash-animate dash-animate-d1">
                    <div class="app-search">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" id="appSearch" class="form-control" placeholder="Search team, league or sport…" aria-label="Search applications" autocomplete="off">
                        <button type="button" class="app-search-clear" id="appSearchClear" aria-label="Clear search" hidden><i class="bi bi-x-lg"></i></button>
                    </div>
                   
                </div>
                <?php endif; ?>

                <div class="activity-section dash-animate dash-animate-d2">
                    <div class="activity-header">
                        <div class="activity-title"><i class="bi bi-list-ul"></i> My team registrations</div>
                        <div class="app-header-meta">
                            <span class="app-result-count" id="appResultCount" aria-live="polite"><?= $totalRegs > 0 ? 'Showing ' . $totalRegs . ' of ' . $totalRegs : '' ?></span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table activity-table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:56px">#</th>
                                    <th>League</th>
                                    <th>Team</th>
                                    <th class="text-center" style="width:150px">Status</th>
                                    <th class="text-center" style="width:150px">Action</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php if ($appRows): $rowIndex = 0; foreach ($appRows as $row):
                                    $rowIndex++;
                                    $statusKey = $row['status_key'];
                                    $rawStatus = trim((string) ($row['status'] ?? ''));
                                    $statusLabel = $rawStatus !== '' ? ucwords($rawStatus) : 'Pending';
                                    $submittedTs = strtotime((string) ($row['created'] ?? ''));
                                    $searchBlob = strtolower(implode(' ', array_map('strval', [
                                        $row['league_name'] ?? '', $row['team_name'] ?? '',
                                        $row['sport'] ?? '', $row['division'] ?? '', $row['category'] ?? '',
                                    ])));
                                ?>
                                <tr class="app-row" data-status="<?= htmlspecialchars($statusKey) ?>" data-search="<?= htmlspecialchars($searchBlob) ?>">
                                    <td class="text-center app-index" data-label="#"><?= $rowIndex ?></td>
                                    <td data-label="League">
                                        <div class="app-league">
                                            <span class="app-league-icon" aria-hidden="true"><i class="bi bi-trophy"></i></span>
                                            <span class="app-league-copy">
                                                <span class="app-league-name" title="<?= htmlspecialchars((string) $row['league_name']) ?>"><?= htmlspecialchars((string) $row['league_name']) ?></span>
                                                <span class="app-league-meta">
                                                    <?php if (trim((string) ($row['sport'] ?? '')) !== ''): ?><span><?= htmlspecialchars((string) $row['sport']) ?></span><?php endif; ?>
                                                    <?php if (trim((string) ($row['division'] ?? '')) !== ''): ?><span class="app-sep" aria-hidden="true"></span><span><?= htmlspecialchars((string) $row['division']) ?></span><?php endif; ?>
                                                    <?php if (trim((string) ($row['category'] ?? '')) !== ''): ?><span class="app-sep" aria-hidden="true"></span><span><?= htmlspecialchars((string) $row['category']) ?></span><?php endif; ?>
                                                </span>
                                            </span>
                                        </div>
                                    </td>
                                    <td data-label="Team">
                                        <span class="app-team"><?= htmlspecialchars((string) $row['team_name']) ?></span>
                                        <?php if ($submittedTs): ?><span class="app-team-sub">Submitted <?= htmlspecialchars(date('M j, Y', $submittedTs)) ?></span><?php endif; ?>
                                    </td>
                                    <td class="text-center" data-label="Status"><span class="app-status is-<?= htmlspecialchars($statusKey) ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                                    <td class="text-center app-cell-actions" data-label="Action">
                                        <div class="app-actions">
                                            <a href="view_team.php?id=<?= (int) $row['team_id'] ?>" class="action-btn"><i class="bi bi-eye"></i> View</a>
                                            
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr class="app-empty-row">
                                    <td colspan="5" class="app-empty-cell">
                                        <div class="app-empty">
                                            <span class="app-empty-icon"><i class="bi bi-clipboard2-x"></i></span>
                                            <h5>No applications yet</h5>
                                            <p>Browse the published leagues and submit your first team — its status will show up here.</p>
                                            <a href="browse_leagues.php" class="app-empty-btn"><i class="bi bi-plus-lg"></i>Browse leagues</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr class="app-noresults-row is-hidden" id="appNoResults">
                                    <td colspan="5" class="app-empty-cell">
                                        <div class="app-empty app-empty-sm">
                                            <span class="app-empty-icon"><i class="bi bi-funnel"></i></span>
                                            <h5>No applications match these filters</h5>
                                            <p>Try another keyword or switch back to the “All” tab.</p>
                                            <button type="button" class="app-empty-btn" id="appResetFilters"><i class="bi bi-arrow-counterclockwise"></i>Clear filters</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'includes/footer.php'; ?>
        <script>
            // Client-side search + status tabs for the applications table.
            (function () {
                var rows = Array.prototype.slice.call(document.querySelectorAll('.app-row'));
                var tabs = Array.prototype.slice.call(document.querySelectorAll('.app-tab'));
                var search = document.getElementById('appSearch');
                var clearBtn = document.getElementById('appSearchClear');
                var countEl = document.getElementById('appResultCount');
                var noResults = document.getElementById('appNoResults');
                if (!rows.length) return;

                var activeStatus = 'all';

                function setActiveTab(key) {
                    activeStatus = key;
                    tabs.forEach(function (tab) {
                        var isActive = tab.getAttribute('data-status-filter') === key;
                        tab.classList.toggle('is-active', isActive);
                        tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    });
                }

                function applyFilters() {
                    var term = (search ? search.value : '').trim().toLowerCase();
                    var visible = 0;
                    rows.forEach(function (row) {
                        var matchesStatus = activeStatus === 'all' || row.getAttribute('data-status') === activeStatus;
                        var matchesText = term === '' || (row.getAttribute('data-search') || '').indexOf(term) !== -1;
                        var show = matchesStatus && matchesText;
                        row.classList.toggle('is-hidden', !show);
                        if (show) visible++;
                    });
                    if (countEl) countEl.textContent = 'Showing ' + visible + ' of ' + rows.length;
                    if (noResults) noResults.classList.toggle('is-hidden', visible !== 0);
                    if (clearBtn) clearBtn.hidden = term === '';
                }

                tabs.forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        setActiveTab(tab.getAttribute('data-status-filter') || 'all');
                        applyFilters();
                    });
                });

                if (search) search.addEventListener('input', applyFilters);
                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        search.value = '';
                        search.focus();
                        applyFilters();
                    });
                }
                var resetBtn = document.getElementById('appResetFilters');
                if (resetBtn) {
                    resetBtn.addEventListener('click', function () {
                        if (search) search.value = '';
                        setActiveTab('all');
                        applyFilters();
                    });
                }

                applyFilters();
            })();
        </script>
        <script>
            $(document).on('submit', '#regisLeague', function(e) {
                e.preventDefault();
                var form = this;
                Swal.fire({
                    title: 'Create league?',
                    text: 'Are you sure you want to create this league?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Create',
                    cancelButtonText: 'Cancel'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        var formData = new FormData(form);
                        formData.append("regisLeague", true);
                        $.ajax({
                            url: "action/leagueAction.php",
                            type: "post",
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(data) {
                                var res;
                                try {
                                    res = jQuery.parseJSON(data);
                                } catch (e) {
                                    Swal.fire({icon:'error', title:'Server error', text: data});
                                    return;
                                }

                                if (res.status == 201) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'SUCCESS',
                                        text: res.msg,
                                        timer: 2000
                                    }).then(function() {
                                        location.reload();
                                    });
                                } else {
                                    var icon = (res.status >= 500) ? 'error' : 'warning';
                                    Swal.fire({icon: icon, title: res.msg || 'Error', timer: 8000});
                                }
                            }
                        });
                    }
                });
            });
        </script>
    </body>
    </html>

