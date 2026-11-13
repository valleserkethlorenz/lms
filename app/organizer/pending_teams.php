<?php
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../config/leagueStatus.php';
require_once __DIR__ . '/../../includes/registrationGate.php';
require_once __DIR__ . '/../../includes/storageService.php';
require_once __DIR__ . '/../../includes/rosterSize.php';
require_once __DIR__ . '/../../includes/summaryStrip.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$userId = currentUserId();
$full = currentUserName();
$userType = currentRole();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
requireRole([1]);
requireActiveAccount($conn);
if (!organizerOwnsLeague($conn, $id, (int)$userId)) {
    $_SESSION['league_flash'] = ['danger', 'That league belongs to another organizer.'];
    header('Location: league.php'); exit;
}

$leagueStmt = $conn->prepare("SELECT * FROM tbl_league WHERE id=? AND created_by=? LIMIT 1");
$leagueStmt->bind_param('ii', $id, $userId); $leagueStmt->execute();
$league_row = $leagueStmt->get_result()->fetch_assoc(); $leagueStmt->close();

/* ------------------------------------------------------------------
   Review queue analytics — the hero, the summary strip and the review
   table all read from these numbers, so they can never disagree.
   ------------------------------------------------------------------ */
$leagueNameSafe = htmlspecialchars((string) ($league_row['league_name'] ?? ''));
$maxTeams      = max(1, (int) ($league_row['max_team'] ?? 1));
$maxPlayers    = (int) ($league_row['max_player'] ?? 0);
$minPlayers    = max(1, (int) ($league_row['min_player'] ?? 5));
$rosterRange   = rosterSizeLabel($minPlayers, $maxPlayers);
$leagueEnded   = trim((string) ($league_row['end_date'] ?? '')) !== '' && date('Y-m-d') > (string) $league_row['end_date'];

$countStmt = $conn->prepare("SELECT
        SUM(CASE WHEN LOWER(status) = 'pending'  THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN LOWER(status) = 'approved' THEN 1 ELSE 0 END) AS approved_count
    FROM tbl_team WHERE league_id = ?");
$countStmt->bind_param('i', $id); $countStmt->execute();
$teamCounts    = $countStmt->get_result()->fetch_assoc() ?: [];
$countStmt->close();
$pendingCount  = (int) ($teamCounts['pending_count'] ?? 0);
$approvedCount = (int) ($teamCounts['approved_count'] ?? 0);
$slotsLeft     = max(0, $maxTeams - $approvedCount);
$registrationSummary = resolveRegistrationWindowSummary($league_row, $approvedCount);

$pendingTeams = [];
$teamStmt = $conn->prepare("SELECT t.*,
        (SELECT COUNT(*) FROM tbl_player p WHERE p.team_id = t.id) AS roster_count,
        (SELECT COUNT(*) FROM tbl_player p WHERE p.team_id = t.id AND p.eligibility_status <> 'Eligible') AS flagged_players,
        (SELECT COUNT(*) FROM tbl_submitted_document d WHERE d.team_id = t.id AND d.review_status = 'Rejected') AS rejected_docs
    FROM tbl_team t
    WHERE t.league_id = ? AND LOWER(t.status) = 'pending'
    ORDER BY t.created ASC, t.id ASC");
$teamStmt->bind_param('i', $id); $teamStmt->execute();
$teamResult = $teamStmt->get_result();
while ($teamRow = $teamResult->fetch_assoc()) { $pendingTeams[] = $teamRow; }
$teamStmt->close();

$fmtDate = static function ($value): string {
    $value = trim((string) $value);
    $ts    = $value !== '' ? strtotime($value) : false;
    return $ts ? date('M d, Y', $ts) : '—';
};
$regisDateRange = $fmtDate($league_row['regis_start'] ?? '') . ' – ' . $fmtDate($league_row['regis_end'] ?? '');

// Team crest: Cloudinary URL, local upload, or bundled fallback art.
$teamCrest = static function ($raw): string {
    $raw = (string) $raw;
    if ($raw !== '' && StorageService::isCloudinary($raw)) {
        return $raw;
    }
    $file = basename($raw);
    return ($file !== '' && is_file(__DIR__ . '/../../assets/uploads/' . $file))
        ? '../../assets/uploads/' . $file
        : '../../assets/images/default-league.svg';
};

include 'includes/header.php';
?>
    <body id="page-top" class="body">
        <?php include 'includes/bar.php'; ?>

        <div class="container-fluid lp-page" style="padding-top:20px;min-height:800px;">
            <div class="row p-3">
                <div class="card cards" style="background:transparent;box-shadow:none;border:none;">
                    <div class="card-body p-0">
                        <?php include 'includes/dashboard-styles.php'; ?>
                        <?php include 'includes/league-view-styles.php'; ?>
                        <?php include 'includes/team-review-styles.php'; ?>
                        <?php include_once __DIR__ . '/../../includes/summary-strip-styles.php'; ?>

                        <!-- ==================== BREADCRUMB ==================== -->
                        <nav class="lp-crumbs dash-animate" aria-label="Breadcrumb">
                            <a href="league.php"><i class="bi bi-arrow-left"></i> League Management</a>
                            <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
                            <a href="league_info.php?id=<?= $id ?>"><?= $leagueNameSafe ?></a>
                            <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
                            <span class="lp-crumb-current">Team review</span>
                        </nav>

                        <!-- ==================== LEAGUE HERO ==================== -->
                        <?php $leagueHeroData = $league_row; $leagueHeroApprovedCount = $approvedCount; include 'includes/league-hero.php'; ?>

                      

                        <!-- ==================== KPI ROW ==================== -->
                        <?php renderSummaryStrip([
                            [
                                'label'  => 'Awaiting review',
                                'value'  => $pendingCount,
                                'note'   => $pendingCount === 0 ? 'No applications in the queue.' : 'Applications need a decision',
                                'accent' => $pendingCount > 0 ? '#a56a14' : '#64708a',
                                'tint'   => $pendingCount > 0 ? '#fdf6e7' : '#f1f3f8',
                            ],
                            [
                                'label' => 'Approved teams',
                                'value' => $approvedCount,
                                'note'  => 'of ' . $maxTeams . ' slots · ' . $slotsLeft . ' still available',
                            ],
                            [
                                'label' => 'Team size',
                                'value' => rosterSizeShortLabel($minPlayers, $maxPlayers),
                                'note'  => $rosterRange . ' per team',
                            ],
                            [
                                'label'  => 'Registration availability',
                                'value'  => $registrationSummary['status'],
                                'note'   => $registrationSummary['note'],
                                'notes'  => [$regisDateRange],
                                'accent' => $registrationSummary['isOpen'] ? '#16735e' : '#64708a',
                                'tint'   => $registrationSummary['isOpen'] ? '#edf7f3' : '#f1f3f8',
                            ],
                        ], 'Team review totals'); ?>

                        <!-- ==================== REVIEW QUEUE ==================== -->
                        <div class="activity-section dash-animate dash-animate-d3">
                            <div class="activity-header">
                                <div class="activity-title">
                                    <i class="bi bi-inbox-fill"></i> Team review queue
                                    
                                </div>
                                <div class="tvr-toolbar">
                                    <div class="tvr-search">
                                        <i class="bi bi-search"></i>
                                        <input type="search" id="tvrQueueSearch" placeholder="Search team or representative" autocomplete="off" aria-label="Search pending teams">
                                    </div>
                                </div>
                            </div>
                            <div class="tvr-table-wrap">
                                <table class="table activity-table tvr-table align-middle mb-0" id="pendingTeamsTable">
                                    <thead>
                                        <tr>
                                            <th style="width:64px;">#</th>
                                            <th>Team</th>
                                            <th style="width:170px;">Roster</th>
                                            <th>Representative</th>
                                            <th style="width:150px;">Submitted</th>
                                            <th style="width:130px;">Status</th>
                                            <th class="text-end tvr-action-column">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $count = 0;
                                        foreach ($pendingTeams as $row):
                                            $count++;
                                            $team_id     = (int) $row['id'];
                                            $rosterCount = (int) ($row['roster_count'] ?? 0);
                                            $rosterPct   = $maxPlayers > 0
                                                ? min(100, (int) round($rosterCount / $maxPlayers * 100))
                                                : ($rosterCount > 0 ? 100 : 0);
                                            $teamNameSafe = htmlspecialchars((string) $row['team_name']);
                                            $teamAttr     = htmlspecialchars((string) $row['team_name'], ENT_QUOTES);
                                            $searchBlob   = strtolower((string) $row['team_name'] . ' ' . (string) $row['team_repre'] . ' ' . (string) $row['email']);
                                            $rosterShort  = $rosterCount . ($maxPlayers > 0 ? ' / ' . $maxPlayers : ' players');
                                            $rosterBelowMin = $rosterCount > 0 && $rosterCount < $minPlayers;
                                            // Nothing the representative could change (see app/tr/action/reapplyTeam.php):
                                            // every player eligible, roster complete and no rejected document.
                                            $nothingToFix = $rosterCount > 0
                                                && (int) ($row['flagged_players'] ?? 0) === 0
                                                && (int) ($row['rejected_docs'] ?? 0) === 0
                                                && !$rosterBelowMin;
                                        ?>
                                        <tr id="team_row_<?= $team_id ?>" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES) ?>">
                                            <td><span class="tvr-index"><?= $count ?></span></td>
                                            <td>
                                                <div class="tvr-team-cell">
                                                    <img class="tvr-team-avatar" src="<?= htmlspecialchars($teamCrest($row['team_image'] ?? ''), ENT_QUOTES) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='../../assets/images/default-league.svg';">
                                                    <div>
                                                        <div class="tvr-team-name"><?= $teamNameSafe ?></div>
                                                        <div class="tvr-team-sub"><?= htmlspecialchars((string) ($row['email'] ?? '')) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="tvr-roster">
                                                    <span class="tvr-roster-count"><?= $rosterShort ?></span>
                                                    <div class="lp-progress"><span style="width:<?= $rosterPct ?>%"></span></div>
                                                </div>
                                            </td>
                                            <td class="tvr-cell-strong"><?= htmlspecialchars((string) $row['team_repre']) ?></td>
                                            <td class="tvr-cell-muted"><?= $fmtDate($row['created'] ?? '') ?></td>
                                            <td><span class="lp-row-status is-pending">Pending</span></td>
                                            <td class="text-end tvr-action-column">
                                                <div class="tvr-actions">
                                                    <a class="league-icon-btn league-action-primary" href="view_player.php?id=<?= $team_id ?>" title="Review team details" aria-label="Review <?= $teamAttr ?>">
                                                        <i class="bi bi-eye-fill" aria-hidden="true"></i>
                                                    </a>
                                                    
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if ($count === 0): ?>
                                        <tr id="tvrQueueEmptyRow">
                                            <td colspan="7">
                                                <div class="lp-empty">
                                                    <i class="bi bi-inbox"></i>
                                                    <p>
                                                        <?= $leagueEnded
                                                            ? 'This league has ended, so the review queue is closed.'
                                                            : 'No team applications are waiting for review. New submissions appear here as soon as representatives register.' ?>
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="tvr-empty" id="tvrQueueCleared" hidden>
                                <i class="bi bi-check2-circle"></i>
                                <p>Queue cleared — every application in this league has been reviewed.</p>
                            </div>
                        </div>
                    </div><!-- /card-body -->
                </div><!-- /card -->
            </div><!-- /row -->
        </div><!-- /container-fluid -->

        <?php include 'includes/footer.php'; ?>

        <script>
            const pendingTeamCsrfToken = <?= json_encode(csrfToken()) ?>;

            /* Queue search — filters the review table without a page reload. */
            (function () {
                var input = document.getElementById('tvrQueueSearch');
                var table = document.getElementById('pendingTeamsTable');
                if (!input || !table) return;
                var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-search]'));
                var emptyRow = document.getElementById('tvrQueueEmptyRow');
                input.addEventListener('input', function () {
                    var term = this.value.trim().toLowerCase();
                    var visible = 0;
                    rows.forEach(function (row) {
                        var haystack = row.getAttribute('data-search') || '';
                        var match = term === '' || haystack.indexOf(term) !== -1;
                        row.hidden = !match;
                        if (match) visible++;
                    });
                    if (emptyRow) emptyRow.hidden = rows.length === 0 ? false : visible !== 0;
                });
            })();

            /* Keep the queue badge and the "queue cleared" panel in sync when a
               decision handler removes a row from the table. */
            (function () {
                var tbody = document.querySelector('#pendingTeamsTable tbody');
                if (!tbody) return;
                var badge = document.getElementById('tvrQueueCount');
                var cleared = document.getElementById('tvrQueueCleared');
                var serverEmpty = document.getElementById('tvrQueueEmptyRow');
                function refresh() {
                    var remaining = tbody.querySelectorAll('tr[data-search]').length;
                    if (badge) badge.textContent = remaining + ' awaiting decision';
                    if (cleared) cleared.hidden = remaining !== 0 || serverEmpty !== null;
                }
                new MutationObserver(refresh).observe(tbody, { childList: true });
                refresh();
            })();
            function sendRejectAction(teamId) {
                Swal.fire({
                    title: 'Reject this application permanently?',
                    input: 'textarea',
                    inputLabel: 'Final rejection reason',
                    inputPlaceholder: 'Explain why this application cannot be resubmitted...',
                    showCancelButton: true,
                    confirmButtonText: 'Reject',
                    confirmButtonColor: '#dc3545',
                    preConfirm: (remarks) => {
                        if (!remarks.trim()) {
                            Swal.showValidationMessage('Remarks is required');
                        }
                        return remarks;
                    }
                }).then(function(res) {
                    if (res.isConfirmed) {
                        $.post('action/leagueAction.php', { reject: true, team_id: teamId, remarks: res.value, csrf_token: pendingTeamCsrfToken }, function(response) {
                            var json = (typeof response === 'string') ? JSON.parse(response) : response;
                            if (json.status == 201) {
                                $('#team_row_' + teamId).fadeOut(300, function(){ $(this).remove(); });
                                Swal.fire({ icon: 'success', title: json.msg, timer: 1500 });
                            } else {
                                Swal.fire({ icon: 'error', title: json.msg || 'Error' });
                            }
                        }).fail(function(){ Swal.fire({ icon: 'error', title: 'Server error' }); });
                    }
                });
            }

            function sendApproveAction(teamId) {
                Swal.fire({
                    title: 'Approve team?',
                    showCancelButton: true,
                    confirmButtonText: 'Approve'
                }).then(function(res) {
                    if (res.isConfirmed) {
                        $.post('action/leagueAction.php', { approved: true, team_id: teamId, csrf_token: pendingTeamCsrfToken }, function(response) {
                            var json = (typeof response === 'string') ? JSON.parse(response) : response;
                            if (json.status == 201) {
                                $('#team_row_' + teamId).fadeOut(300, function(){ $(this).remove(); });
                                Swal.fire({ icon: 'success', title: json.msg, timer: 1500 });
                            } else {
                                Swal.fire({ icon: 'error', title: json.msg || 'Error' });
                            }
                        }).fail(function(){ Swal.fire({ icon: 'error', title: 'Server error' }); });
                    }
                });
            }

            function sendCorrectionAction(teamId) {
                Swal.fire({title:'Return for resubmission',html:`<style>.return-option{display:flex;align-items:center;gap:12px;margin-bottom:10px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;cursor:pointer;font-weight:700}.return-option:hover{border-color:#d39e00;background:#fff9e6}.return-option:has(input:checked){border-color:#d39e00;background:#fff4cc;box-shadow:0 0 0 2px rgba(211,158,0,.14)}.return-option input{flex:0 0 auto;width:22px!important;height:22px!important;margin:0!important;border:2px solid #64748b!important;cursor:pointer;accent-color:#d39e00}</style><div class="text-start"><label class="return-option"><input type="checkbox" class="pending-return-reason" value="incomplete_team_information"><span>Incomplete team information</span></label><label class="return-option"><input type="checkbox" class="pending-return-reason" value="invalid_team_document"><span>Invalid team-level document</span></label><label class="return-option"><input type="checkbox" class="pending-return-reason" value="roster_below_minimum"><span>Roster is below the required minimum</span></label><label class="return-option"><input type="checkbox" class="pending-return-reason" value="conflicting_team_information"><span>Duplicate or conflicting team information</span></label><label class="return-option"><input type="checkbox" class="pending-return-reason" value="multiple_player_corrections"><span>Multiple players require correction</span></label><label class="return-option"><input type="checkbox" class="pending-return-reason" value="other" id="pendingReturnOther"><span>Other</span></label><textarea id="pendingReturnRemarks" class="form-control" maxlength="500" placeholder="Other remarks" disabled></textarea></div>`,showCancelButton:true,confirmButtonText:'Return Application',confirmButtonColor:'#d39e00',didOpen:()=>document.getElementById('pendingReturnOther').addEventListener('change',function(){const field=document.getElementById('pendingReturnRemarks');field.disabled=!this.checked;if(!this.checked)field.value='';}),preConfirm:()=>{const reasons=Array.from(document.querySelectorAll('.pending-return-reason:checked')).map(item=>item.value),other=document.getElementById('pendingReturnRemarks').value.trim();if(!reasons.length)return Swal.showValidationMessage('Select at least one reason.');if(reasons.includes('other')&&!other)return Swal.showValidationMessage('Enter the other reason.');return {reasons,other};}}).then(function(res) {
                    if (!res.isConfirmed) return;
                    $.post('action/leagueAction.php',{requestCorrection:true,team_id:teamId,return_reasons:res.value.reasons,other_remarks:res.value.other,csrf_token:pendingTeamCsrfToken},function(response) {
                        var json = typeof response === 'string' ? JSON.parse(response) : response;
                        if (json.status === 201) $('#team_row_'+teamId).fadeOut(300,function(){$(this).remove();});
                        Swal.fire(json.status === 201 ? 'Sent' : 'Error',json.msg,json.status === 201 ? 'success' : 'error');
                    }).fail(function(){Swal.fire('Error','Server request failed.','error');});
                });
            }

            $(document).on('click', '.approve-btn', function(){
                var id = $(this).data('id');
                sendApproveAction(id);
            });

            $(document).on('click', '.reject-btn', function(){
                var id = $(this).data('id');
                sendRejectAction(id);
            });
            $(document).on('click', '.correction-btn', function(){ sendCorrectionAction($(this).data('id')); });
        </script>

    </body>

    </html>
