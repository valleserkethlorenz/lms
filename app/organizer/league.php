<?php
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/leagueStatus.php';
require_once __DIR__ . '/../../config/auth_guard.php';
/** @var mysqli $conn */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$userId = currentUserId();
$full = currentUserName();
$userType = currentRole();
requireRole([1]);
requireActiveAccount($conn);

// League total count for table header badge
$total_leagues = 0;
$_cntStmt = $conn->prepare("SELECT COUNT(*) FROM tbl_league WHERE created_by=? AND (is_archived=0 OR is_archived IS NULL)");
if ($_cntStmt) { $_cntStmt->bind_param('i', $userId); $_cntStmt->execute(); $_cntStmt->bind_result($total_leagues); $_cntStmt->fetch(); $_cntStmt->close(); }

$loggin = true;
$time = strtotime("-10 year", time());
$date = date("Y-m-d", $time);

$today = date("M/d/Y");
include 'includes/header.php';
?>

    <body id="page-top" class="body organizer-leagues">
        <?php
        include 'includes/bar.php';

        // Flash message left behind when a page bounced the organizer back here
        // (for example after trying to open another organizer's league).
        $leagueFlash = $_SESSION['league_flash'] ?? null;
        unset($_SESSION['league_flash']);
        if ($leagueFlash): ?>
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="alert alert-<?= htmlspecialchars((string)($leagueFlash[0] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars((string)($leagueFlash[1] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php endif; ?>
        <div class="container-fluid" style="padding-top: 20px; min-height: 800px;">
            <div class="row p-3">
                <div class="card cards" style="background:transparent;box-shadow:none;border:none;">
                    <div class="card-body p-0">
                        <?php include 'includes/dashboard-styles.php'; ?>
                        <?php include 'includes/league-list-styles.php'; ?>
                        <!-- Page Banner -->
                        

                        <!-- League Table -->
                        <div class="activity-section dash-animate dash-animate-d5 mb-4">
                            <div class="activity-header">
                                <div><span class="section-eyebrow">Season overview</span><h2 class="section-title">League Management <span class="activity-badge-count"><?= (int) $total_leagues ?> total</span></h2></div>
                                <a href="create_league.php" class="activity-view-all"><i class="bi bi-plus-lg" aria-hidden="true"></i>Create League</a>
                            </div>
                            <div class="league-filter-bar">
                                <input type="text" class="league-search-input" id="leagueSearch" aria-label="Search leagues" placeholder="Find a league...">
                                <div class="league-status-filter">
                                    <button class="league-status-btn active" data-filter="all">All</button>
                                    <button class="league-status-btn" data-filter="ongoing">Ongoing</button>
                                    <button class="league-status-btn" data-filter="upcoming">Upcoming</button>
                                    <button class="league-status-btn" data-filter="draft">Draft</button>
                                </div>
                            </div>
                            <div class="league-table-wrapper">
                                <table id="organizeTable" class="table activity-table mb-0">
                                    <thead>
                                        <tr>
                                            <th><i class="bi bi-hash me-1"></i>#</th>
                                            <th><i class="bi bi-trophy me-1"></i>League</th>
                                            <th><i class="bi bi-dribbble me-1"></i>Sport</th>
                                            <th><i class="bi bi-people me-1"></i>Team limit</th>
                                            <th><i class="bi bi-calendar3 me-1"></i>Season dates</th>
                                            <th><i class="bi bi-activity me-1"></i>Status</th>
                                            <th><i class="bi bi-eye me-1"></i>Visibility</th>
                                            <th class="league-action-column" data-orderable="false"><i class="bi bi-gear me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $count = 0;
                                        $fetch_league = "SELECT l.id, l.league_name, l.sport, l.max_team, l.start_date, l.end_date, l.regis_start, l.regis_end, l.registration_window_open, l.publication_status, (SELECT COUNT(*) FROM tbl_league_stage s WHERE s.league_id = l.id) AS stage_count FROM tbl_league l WHERE l.created_by=? AND (l.is_archived=0 OR l.is_archived IS NULL) ORDER BY l.id DESC";
                                        $leagueStmt = $conn->prepare($fetch_league);
                                        $leagueStmt->bind_param('i', $userId);
                                        $leagueStmt->execute();
                                        $league_res = $leagueStmt->get_result();
                                        
                                        if ($league_res) {
                                            while ($league_row = mysqli_fetch_assoc($league_res)) {
                                                $start_date = date("F d, Y", strtotime($league_row['start_date']));
                                                $end_date = date("F d, Y", strtotime($league_row['end_date']));
                                                
                                                // Get real-time league status
                                                $leagueStatus = getLeagueStatus($league_row);
                                                $statusBadgeClass = getLeagueStatusBadgeClass($leagueStatus);
                                                $statusDisplay = getLeagueStatusDisplay($leagueStatus);
                                                
                                                $count++;
                                                
                                                // Sanitize output to prevent XSS
                                                $leagueId = intval($league_row['id']);
                                                $leagueName = htmlspecialchars($league_row['league_name'], ENT_QUOTES, 'UTF-8');
                                                $sport = htmlspecialchars($league_row['sport'], ENT_QUOTES, 'UTF-8');
                                                $maxTeam = intval($league_row['max_team']);
                                                $publicationStatus = $league_row['publication_status'] ?? 'Draft';
                                                $publicationBadge = $publicationStatus === 'Published' ? 'text-bg-success' : 'text-bg-secondary';
                                                $isCompleted = $publicationStatus === 'Published' && strtolower($leagueStatus) === 'completed';
                                                $needsSetup = !$isCompleted && (int)($league_row['stage_count'] ?? 0) === 0;
                                                $setupChip = $needsSetup
                                                    ? ' <a class="league-setup-chip" href="setup.php?id=' . $leagueId . '#step-format" title="No competition structure yet — choose one in Setup"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>Setup needed</a>'
                                                    : '';
                                                $primaryLabel = $publicationStatus === 'Draft' ? 'Edit Draft' : ($isCompleted ? 'View' : 'Manage');
                                                $primaryHref = $publicationStatus === 'Draft' ? 'edit_league.php?id=' . $leagueId : 'league_info.php?id=' . $leagueId;
                                                $primaryIcon = $publicationStatus === 'Draft' ? 'bi-pencil-square' : ($isCompleted ? 'bi-eye-fill' : 'bi-list-check');
                                                $safeDataName = htmlspecialchars($league_row['league_name'], ENT_QUOTES, 'UTF-8');
                                                $secondaryActions = '';
                                                if ($publicationStatus === 'Draft') {
                                                    $secondaryActions .= '<button type="button" class="league-icon-btn league-action-publish publish-league-btn" data-league-id="' . $leagueId . '" data-league-name="' . $safeDataName . '" title="Publish League" aria-label="Publish ' . $safeDataName . '"><i class="bi bi-send-fill" aria-hidden="true"></i></button>';
                                                }
                                                if ($isCompleted) {
                                                    $secondaryActions .= '<button type="button" class="league-icon-btn league-action-archive archive-league-btn" data-league-id="' . $leagueId . '" data-league-name="' . $safeDataName . '" title="Archive League" aria-label="Archive ' . $safeDataName . '"><i class="bi bi-archive-fill" aria-hidden="true"></i></button>';
                                                }

                                                echo '<tr>
                                                    <td>' . $count . '</td>
                                                    <td style="text-transform:capitalize;font-weight:500;">' . $leagueName . $setupChip . '</td>
                                                    <td style="text-transform:capitalize;">' . $sport . '</td>
                                                    <td class="text-center">' . $maxTeam . '</td>
                                                    <td style="font-size:12px;white-space:nowrap;color:#6b7280;">' . $start_date . ' – ' . $end_date . '</td>
                                                    <td><span class="badge ' . $statusBadgeClass . ' p-2">' . $statusDisplay . '</span></td>
                                                    <td><span class="badge ' . $publicationBadge . '">' . htmlspecialchars($publicationStatus) . '</span></td>
                                                    <td class="league-action-column"><div class="league-actions"><a class="league-icon-btn league-action-primary" href="' . htmlspecialchars($primaryHref, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($primaryLabel, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($primaryLabel . ' ' . $league_row['league_name'], ENT_QUOTES, 'UTF-8') . '"><i class="bi ' . $primaryIcon . '" aria-hidden="true"></i></a>' . $secondaryActions . '</div></td>
                                                </tr>';
                                            }
                                        }
                                        $leagueStmt->close();
                                        ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>


                    <?php include 'includes/footer.php'; ?>
                    <script>
                        function publishLeague(id, name) {
                            Swal.fire({title:'Publish League?',text:name + ' will become visible to Team Representatives and the public.',icon:'question',showCancelButton:true,confirmButtonColor:'#198754',confirmButtonText:'Publish'}).then(function(result) {
                                if (!result.isConfirmed) return;
                                $.post('action/leagueAction.php',{publishLeague:true,league_id:id,csrf_token:<?= json_encode(csrfToken()) ?>},function(response) {
                                    var res = typeof response === 'string' ? JSON.parse(response) : response;
                                    if (res.status === 201 || res.status === 200) Swal.fire('Published',res.msg,'success').then(()=>location.reload());
                                    else Swal.fire('Error',res.msg || 'Unable to publish league.','error');
                                }).fail(function(){ Swal.fire('Error','Server request failed.','error'); });
                            });
                        }
                        $(document).on('click','.publish-league-btn',function(){
                            publishLeague(Number(this.dataset.leagueId), this.dataset.leagueName || 'This league');
                        });
                        $(document).on('click','.archive-league-btn',function(){
                            const id=Number(this.dataset.leagueId), name=this.dataset.leagueName || 'This league';
                            Swal.fire({title:'Archive League?',text:name + ' will be hidden but can be restored later.',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc3545',confirmButtonText:'Archive'}).then(function(result){
                                if(!result.isConfirmed) return;
                                $.post('action/leagueAction.php',{archiveLeague:true,league_id:id,csrf_token:<?= json_encode(csrfToken()) ?>},function(response){
                                    const res=typeof response==='string'?JSON.parse(response):response;
                                    if(res.status===201) Swal.fire('Archived',res.msg,'success').then(()=>location.reload());
                                    else Swal.fire('Error',res.msg || 'Unable to archive league.','error');
                                }).fail(function(){Swal.fire('Error','Server request failed.','error');});
                            });
                        });
                    </script>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Search filter for league table
                        var searchInput = document.getElementById('leagueSearch');
                        if (searchInput) {
                            searchInput.addEventListener('input', function() {
                                var q = this.value.toLowerCase();
                                var table = document.getElementById('organizeTable');
                                if (!table) return;
                                table.querySelectorAll('tbody tr').forEach(function(row) {
                                    var text = row.textContent.toLowerCase();
                                    row.style.display = text.indexOf(q) !== -1 ? '' : 'none';
                                });
                            });
                        }

                        // Status filter buttons
                        document.querySelectorAll('.league-status-btn').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                document.querySelectorAll('.league-status-btn').forEach(function(b) { b.classList.remove('active'); });
                                this.classList.add('active');
                                var filter = this.getAttribute('data-filter');
                                var table = document.getElementById('organizeTable');
                                if (!table) return;
                                table.querySelectorAll('tbody tr').forEach(function(row) {
                                    if (filter === 'all') { row.style.display = ''; return; }
                                    var statusCell = row.querySelector('td:nth-child(6)');
                                    var visCell = row.querySelector('td:nth-child(7)');
                                    var statusText = statusCell ? statusCell.textContent.toLowerCase().trim() : '';
                                    var visText = visCell ? visCell.textContent.toLowerCase().trim() : '';
                                    var show = false;
                                    if (filter === 'ongoing' && statusText.indexOf('ongoing') !== -1) show = true;
                                    if (filter === 'upcoming' && statusText.indexOf('upcoming') !== -1) show = true;
                                    if (filter === 'draft' && visText.indexOf('draft') !== -1) show = true;
                                    row.style.display = show ? '' : 'none';
                                });
                            });
                        });
                    });
                    </script>

                    </div>
                </div>
            </div>
        </div>

    </body>

    </html>
