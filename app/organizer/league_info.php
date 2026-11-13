<?php
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../includes/eligibilityValidator.php';
require_once __DIR__ . '/../../includes/storageService.php';
require_once __DIR__ . '/../../includes/summaryStrip.php';
require_once __DIR__ . '/../../includes/rosterSize.php';
require_once __DIR__ . '/../../config/leagueStatus.php';
require_once __DIR__ . '/../../includes/registrationGate.php';
require_once __DIR__ . '/../../config/auth_guard.php';
/** @var mysqli $conn */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$userId = currentUserId();
$full = currentUserName();
$userType = currentRole();
$id = intval($_GET["id"] ?? 0);
requireRole([1]);
requireActiveAccount($conn);
if (!organizerOwnsLeague($conn, $id, (int)$userId, true)) {
    $_SESSION['league_flash'] = ['danger', 'That league belongs to another organizer.'];
    header('Location: league.php'); exit;
}
    $loggin = true;
    $time = strtotime("-10 year", time());
    $date = date("Y-m-d", $time);

    $today = date("M/d/Y");
    include 'includes/header.php';
    $leagueStmt = $conn->prepare("SELECT * FROM tbl_league WHERE id=? AND created_by=? LIMIT 1");
    $leagueStmt->bind_param('ii', $id, $userId); $leagueStmt->execute();
    $league_row = $leagueStmt->get_result()->fetch_assoc(); $leagueStmt->close();

    // Fetch organizer profile data
    $orgStmt = $conn->prepare("SELECT fname, lname, mname, suffix, gender, date_of_birth, email, phone, address, years_experience, credentials FROM tbl_user WHERE id=? LIMIT 1");
    $orgStmt->bind_param('i', $userId); $orgStmt->execute();
    $organizer = $orgStmt->get_result()->fetch_assoc(); $orgStmt->close();

?>

    <body id="page-top" class="body">
        <?php include 'includes/bar.php'; ?>

        <?php
        /* ------------------------------------------------------------------
           Overview analytics — prepared statements only, so the hero banner,
           the KPI row and the teams table all read from the same numbers.
           ------------------------------------------------------------------ */
        $approved_count = 0;
        $approved_q = $conn->prepare("SELECT COUNT(*) AS cnt FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved'");
        $approved_q->bind_param('i', $id);
        $approved_q->execute();
        $approved_count = (int) ($approved_q->get_result()->fetch_assoc()['cnt'] ?? 0);
        $approved_q->close();

        $pending_count = 0;
        $pq = $conn->prepare("SELECT COUNT(*) AS cnt FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'pending'");
        $pq->bind_param('i', $id);
        $pq->execute();
        $pending_count = (int) ($pq->get_result()->fetch_assoc()['cnt'] ?? 0);
        $pq->close();

        $organizer     = is_array($organizer) ? $organizer : [];
        $requirements  = getEligibilityRequirements($conn, $id);
        $maxTeams      = max(1, (int) $league_row['max_team']);
        $slotsFull     = $approved_count >= $maxTeams;
        $slotsLeft     = max(0, $maxTeams - $approved_count);
        $slotPct       = min(100, (int) round($approved_count / $maxTeams * 100));
        $maxPlayers    = (int) ($league_row['max_player'] ?? 0);
        $minPlayers    = max(1, (int) ($requirements['min_players'] ?? 5));
        // min === max means a fixed roster (e.g. Pickleball: exactly 2 players).
        $rosterFixed   = isFixedRosterSize($minPlayers, $maxPlayers);
        $minMale       = (int) ($requirements['min_male_players'] ?? 0);
        $minFemale     = (int) ($requirements['min_female_players'] ?? 0);
        $minAge        = (int) ($requirements['min_age'] ?? 0);
        $maxAge        = (int) ($requirements['max_age'] ?? 100);
        $leagueEnded   = (date('Y-m-d') > $league_row['end_date']);
        $registrationSummary = resolveRegistrationWindowSummary($league_row, $approved_count);
        $registrationReopen  = resolveRegistrationReopenState($league_row, $approved_count);
        // Human label for the non-open states. The KPI row carries the full
        // explanation; the chip only has to be truthful at a glance.
        $registrationChip = (static function (array $summary, array $league): string {
            return match ($summary['status']) {
                'Manually closed'  => 'Registration Closed',
                'Upcoming'         => strtotime((string) ($league['regis_start'] ?? ''))
                    ? 'Opens ' . date('M j', strtotime((string) $league['regis_start']))
                    : 'Registration Upcoming',
                'Ended'            => 'Registration Ended',
                'Full'             => 'Registration Full',
                'League ended'     => 'League Ended',
                'Not published'    => 'Not Published',
                'Dates incomplete' => 'Dates Incomplete',
                'Archived'         => 'Archived',
                default            => 'Registration Closed',
            };
        })($registrationSummary, $league_row);
        // When the window is manually closed, explain why it cannot be reopened
        // (end date passed, slots full, league ended) instead of repeating the
        // generic "closed by the organizer" note.
        $registrationChipTitle = ($registrationSummary['status'] === 'Manually closed' && $registrationReopen['reason'] !== '')
            ? $registrationReopen['reason']
            : $registrationSummary['note'];
        $leagueStatus  = getLeagueStatus($league_row);
        $statusLabel   = getLeagueStatusLabel($leagueStatus);
        $statusPill    = 'lp-pill-' . strtolower($leagueStatus);
        $publication   = trim((string) ($league_row['publication_status'] ?? ''));

        // Hero artwork: Cloudinary URL, local upload, or bundled fallback art.
        $heroRaw = (string) ($league_row['league_image'] ?? '');
        if ($heroRaw !== '' && StorageService::isCloudinary($heroRaw)) {
            $heroImage = $heroRaw;
        } else {
            $heroFile  = basename($heroRaw);
            $heroImage = ($heroFile !== '' && is_file(__DIR__ . '/../../assets/uploads/' . $heroFile))
                ? '../../assets/uploads/' . $heroFile
                : '../../assets/images/default-league.svg';
        }

        $fmtDate = static function ($value) {
            $value = trim((string) $value);
            $ts = $value !== '' ? strtotime($value) : false;
            return $ts ? date('M d, Y', $ts) : '—';
        };
        $leagueDateRange = $fmtDate($league_row['start_date']) . ' – ' . $fmtDate($league_row['end_date']);
        $regisDateRange  = $fmtDate($league_row['regis_start']) . ' – ' . $fmtDate($league_row['regis_end']);
        $organizerName   = htmlspecialchars(trim(($organizer['fname'] ?? '') . ' ' . (!empty($organizer['mname']) ? $organizer['mname'] . ' ' : '') . ($organizer['lname'] ?? '') . ' ' . ($organizer['suffix'] ?? '')));
        $organizerInitials = strtoupper(mb_substr((string) ($organizer['fname'] ?? ''), 0, 1) . mb_substr((string) ($organizer['lname'] ?? ''), 0, 1));
        $leagueNameSafe  = htmlspecialchars((string) $league_row['league_name']);
        $sportLabel      = trim((string) ($league_row['sport'] ?? '')) !== '' ? $league_row['sport'] : '—';
        $divisionLabel   = trim((string) ($league_row['division'] ?? '')) !== '' ? $league_row['division'] : '—';
        $categoryLabel   = trim((string) ($league_row['category'] ?? '')) !== '' ? $league_row['category'] : '—';
        $venueLabel      = trim((string) ($league_row['venue'] ?? '')) !== '' ? $league_row['venue'] : '—';
        $feeLabel        = trim((string) ($league_row['regis_fee'] ?? '')) !== '' ? $league_row['regis_fee'] : '—';
        ?>

        <div class="container-fluid lp-page" style="padding-top:20px;min-height:800px;">
            <div class="row p-3">
                <div class="card cards" style="background:transparent;box-shadow:none;border:none;">
                    <div class="card-body p-0">
                        <?php include 'includes/dashboard-styles.php'; ?>
                        <?php include 'includes/league-view-styles.php'; ?>
                        <?php include_once __DIR__ . '/../../includes/summary-strip-styles.php'; ?>
                        <style>
                            /* ==========================================================
                               League Overview — page-specific additions only.
                               Shared hero / breadcrumb / pills / buttons / tabs /
                               panels / modals live in includes/league-view-styles.php
                               ========================================================== */

                            
                            #pendingTeamsBtn { position:relative; }
                        

                            /* ---------- Page-specific: pending-review badge ---------- */

                            #pendingTeamsBtn { position:relative; }
                            #pendingTeamsBtn .pending-dot { position:absolute; top:-4px; right:-4px; width:10px; height:10px; background:#ef4444; border-radius:50%; box-shadow:0 0 0 2px #fff; }

                            .lp-overview-actions { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:22px; }
                            .lp-overview-actions .lp-btn-pending { background:#fef3c7; border-color:#f59e0b; color:#b45309; box-shadow:none; }
                            .lp-overview-actions .lp-btn-pending:hover { background:#fde68a; border-color:#d97706; color:#92400e; }
                            .lp-overview-actions .lp-btn-pending i { color:inherit; }
                            .lp-pending-count { padding:1px 7px; border-radius:12px; background:rgba(180,83,9,.12); font-size:11px; }
                            .lp-overview-actions .lp-btn-light, .lp-overview-actions .lp-btn-soft { background:#fff; border-color:var(--lp-border); color:var(--lp-navy); }
                            .lp-overview-actions .lp-btn-light:hover, .lp-overview-actions .lp-btn-soft:hover { background:var(--lp-soft); border-color:#cbd5e1; }
                            .lp-overview-actions .lp-btn-danger { margin-left:auto; background:#fff; border-color:#dc3545; color:#dc3545; }
                            .lp-overview-actions .lp-btn-danger:hover { background:#fff1f2; border-color:#b91c1c; color:#b91c1c; }
                            .lp-overview-actions .lp-btn-state { margin-left:auto; background:#f3f4f6; border-color:var(--lp-border); color:#4b5563; }
                            @media (max-width:575px) {
                                .lp-overview-actions .lp-btn { width:100%; justify-content:center; }
                                .lp-overview-actions .lp-btn-danger, .lp-overview-actions .lp-btn-state { margin-left:0; }
                            }

                            /* ---------- Page-specific: teams table ------------------- */
                            .lp-table-wrap { overflow-x:auto; }
                            .lp-teams-table { font-size:13.5px; margin:0; width:100%; }
                            .lp-teams-table thead th { white-space:nowrap; }
                            .lp-teams-table .lp-row-index { color:#9ca3af; font-size:12.5px; font-weight:600; }
                            .lp-team-cell { display:flex; align-items:center; gap:12px; min-width:210px; }
                            .lp-team-avatar { width:40px; height:40px; flex:0 0 auto; border-radius:10px; border:1px solid #e8edf4; background:#eef2f7; object-fit:cover; }
                            .lp-team-name { font-weight:600; color:var(--lp-ink); line-height:1.3; text-transform:capitalize; }
                            .lp-team-sub { font-size:11.5px; color:#9ca3af; }
                            .lp-roster { min-width:120px; }
                            .lp-roster-count { font-size:12.5px; font-weight:600; color:var(--lp-ink); }
                            .lp-roster .lp-progress { height:5px; margin-top:7px; }
                            .lp-cell-strong { font-weight:500; color:#334155; text-transform:capitalize; }
                            .lp-cell-muted { color:var(--lp-muted); font-size:12.5px; white-space:nowrap; }
                            .lp-table-tools { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
                            .lp-search { position:relative; }
                            .lp-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:13px; color:#9ca3af; }
                            .lp-search input { min-width:230px; padding:7px 14px 7px 32px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12.5px; transition:border-color .2s ease, box-shadow .2s ease; }
                            .lp-search input:focus { outline:none; border-color:var(--lp-blue); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.1); }

                            /* ---------- Organizer panel ------------------------------ */
                            .lp-organizer-body { display:flex; align-items:flex-start; gap:22px; padding:22px 24px 24px; flex-wrap:wrap; }
                            .lp-org-avatar { width:76px; height:76px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; border-radius:50%; background:linear-gradient(135deg,var(--lp-navy),var(--lp-blue)); color:#fff; font-size:1.45rem; font-weight:600; letter-spacing:1px; box-shadow:0 6px 18px rgba(9,23,60,.18); }
                            .lp-org-main { flex:1 1 320px; min-width:0; }
                            .lp-org-name { margin:0 0 8px; font-size:1.15rem; font-weight:600; color:var(--lp-ink); text-transform:capitalize; }
                            .lp-org-chips { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
                            .lp-org-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; background:#eef2fb; color:#1e3a68; font-size:11.5px; font-weight:600; }
                            .lp-contact-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px 22px; }
                            .lp-credentials { margin-top:18px; padding:14px 16px; border:1px solid #eef2f7; border-radius:10px; background:#f8fafc; }
                            .lp-credentials small { display:block; margin-bottom:6px; font-size:10.5px; font-weight:600; letter-spacing:.6px; text-transform:uppercase; color:#9ca3af; }
                            .lp-credentials p { margin:0; max-height:72px; overflow:hidden; font-size:12.5px; color:#475569; white-space:pre-wrap; }
                            @media (max-width:767px) { .lp-contact-grid { grid-template-columns:1fr; } .lp-organizer-body { gap:16px; } }

                        </style>

                        <!-- ==================== BREADCRUMB ==================== -->
                        <nav class="lp-crumbs dash-animate" aria-label="Breadcrumb">
                            <a href="league.php"><i class="bi bi-arrow-left"></i> League Management</a>
                            <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
                            <span class="lp-crumb-current"><?= $leagueNameSafe ?></span>
                        </nav>

                        <?php $leagueHeroData = $league_row; $leagueHeroApprovedCount = $approved_count; include 'includes/league-hero.php'; ?>
                       
                         <!-- ==================== LEAGUE TABS ==================== -->
                        <div class="lp-tabs dash-animate dash-animate-d1">
                            <?php $leagueTabLeagueId = $id; include 'includes/league_tabs.php'; ?>
                        </div>

                        <!-- ==================== KPI ROW ==================== -->
                        <?php renderSummaryStrip([
                            [
                                'label' => 'Registered Teams',
                                'value' => $approved_count,
                                'note'  => 'of ' . $maxTeams . ' slots · ' . $slotsLeft . ' still available',
                            ],
                            [
                                'label' => 'Pending Applications',
                                'value' => $pending_count,
                                'note'  => $leagueEnded
                                    ? 'League ended — review queue closed.'
                                    : ($pending_count === 0 ? 'No applications waiting for review.' : 'Applications awaiting a decision'),
                                'link'  => (!$leagueEnded && $pending_count > 0)
                                    ? ['href' => 'pending_teams.php?id=' . $id, 'label' => 'Review applications']
                                    : null,
                            ],
                            [
                                'label' => 'Players per Team',
                                'value' => rosterSizeShortLabel($minPlayers, $maxPlayers),
                                'note'  => $categoryLabel . ' squad · ages ' . $minAge . '–' . $maxAge . ($rosterFixed ? ' · fixed roster' : ''),
                                'notes' => array_values(array_filter([
                                    $minMale > 0 ? 'Min ' . $minMale . ' male player(s)' : '',
                                    $minFemale > 0 ? 'Min ' . $minFemale . ' female player(s)' : '',
                                ])),
                            ],
                            [
                                'label'  => 'Registration availability',
                                'value'  => $registrationSummary['status'],
                                'note'   => $registrationSummary['note'],
                                'accent' => $registrationSummary['All team slots are filled.isOpen'] ? '#16735e' : '#64708a',
                                'tint'   => $registrationSummary['isOpen'] ? '#edf7f3' : '#f1f3f8',
                            ],
                        ], 'League overview'); ?>

                        

                        <div class="lp-overview-actions dash-animate dash-animate-d1" role="group" aria-label="League actions">
                            <?php if (!$leagueEnded): ?>
                                <a href="pending_teams.php?id=<?= $id ?>" id="pendingTeamsBtn" class="lp-btn lp-btn-pending" title="Review pending teams">
                                    <i class="bi bi-inbox-fill"></i> Pending Teams
                                    <span class="lp-pending-count" aria-label="<?= $pending_count ?> pending applications"><?= $pending_count ?></span>
                                </a>
                                <button type="button" class="lp-btn lp-btn-light" data-bs-toggle="modal" data-bs-target="#announcementModal" title="Manage announcements">
                                    <i class="bi bi-megaphone-fill"></i> Announcements
                                </button>
                                <?php if ($registrationSummary['isOpen'] || $registrationSummary['status'] === 'Upcoming'): ?>
                                    <button type="button" class="lp-btn lp-btn-danger" onclick="closeRegistration()">
                                        <i class="bi bi-lock-fill"></i> Close Registration
                                    </button>
                                <?php elseif ($registrationSummary['status'] === 'Manually closed' && $registrationReopen['allowed']): ?>
                                    <button type="button" class="lp-btn lp-btn-soft" onclick="reopenRegistration()">
                                        <i class="bi bi-unlock-fill"></i> Reopen Registration
                                    </button>
                                <?php else: ?>
                                    <span class="lp-btn lp-btn-state" title="<?= htmlspecialchars($registrationChipTitle, ENT_QUOTES) ?>">
                                        <i class="bi bi-lock-fill"></i> <?= htmlspecialchars($registrationChip, ENT_QUOTES) ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <button type="button" class="lp-btn lp-btn-soft" onclick="archiveLeague(<?= $id ?>, '<?= htmlspecialchars($league_row['league_name'], ENT_QUOTES) ?>')">
                                    <i class="bi bi-archive-fill"></i> Move to Archive
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- ==================== APPROVED TEAMS ==================== -->
                        <div class="activity-section dash-animate dash-animate-d6">
                            <div class="activity-header">
                                <div class="activity-title">
                                    <i class="bi bi-people-fill"></i> Approved Teams
                                    <span class="activity-badge-count"><?= $approved_count ?> of <?= $maxTeams ?> slots filled</span>
                                </div>
                                <div class="lp-table-tools">
                                    <div class="lp-search">
                                        <i class="bi bi-search"></i>
                                        <input type="text" id="lpTeamSearch" placeholder="Search teams or representatives" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                            <div class="lp-table-wrap">
                                <table class="table activity-table align-middle mb-0 lp-teams-table" id="leagueTeamsTable">
                                    <thead>
                                        <tr>
                                            <th style="width:56px;"><i class="bi me-1"></i>#</th>
                                            <th><i class="bi bi-shield-fill me-1"></i>Team</th>
                                            <th style="width:170px;"><i class="bi bi-people me-1"></i>Roster</th>
                                            <th><i class="bi bi-person-badge me-1"></i>Representative</th>
                                            <th style="width:150px;"><i class="bi bi-calendar2-check me-1"></i>Approved</th>
                                            <th style="width:140px;"><i class="bi bi-activity me-1"></i>Status</th>
                                            <th style="width:96px;" class="text-end"><i class="bi bi-gear me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $count = 0;
                                        // Only approved teams are listed in the league overview.
                                        $fetch_team = "SELECT * FROM tbl_team WHERE league_id = $id AND LOWER(status) = 'approved'";
                                        $team_res = mysqli_query($conn, $fetch_team);
                                        while ($row = mysqli_fetch_assoc($team_res)) {
                                            $count++;
                                            $created = date("M d, Y", strtotime($row['created']));
                                            $team_id = (int) $row['id'];
                                            // Roster size drives the mini progress bar.
                                            $player_res  = mysqli_query($conn, "SELECT COUNT(*) AS total_player FROM tbl_player WHERE team_id = $team_id");
                                            $player_row  = $player_res ? mysqli_fetch_assoc($player_res) : null;
                                            $rosterCount = (int) ($player_row['total_player'] ?? 0);
                                            $rosterPct   = $maxPlayers > 0 ? min(100, (int) round($rosterCount / $maxPlayers * 100)) : 0;
                                            // Team crest: Cloudinary URL, local upload, or fallback art.
                                            $teamImgRaw = (string) ($row['team_image'] ?? '');
                                            if ($teamImgRaw !== '' && StorageService::isCloudinary($teamImgRaw)) {
                                                $teamImgSrc = $teamImgRaw;
                                            } else {
                                                $teamImgFile = basename($teamImgRaw);
                                                $teamImgSrc  = ($teamImgFile !== '' && is_file(__DIR__ . '/../../assets/uploads/' . $teamImgFile))
                                                    ? '../../assets/uploads/' . $teamImgFile
                                                    : '../../assets/images/default-league.svg';
                                            }
                                            $statusKey = strtolower((string) $row['status']);
                                            $statusCls = $statusKey === 'approved' ? 'is-approved' : ($statusKey === 'pending' ? 'is-pending' : 'is-neutral');

                                            echo '<tr>'
                                                . '<td class="lp-row-index">' . $count . '</td>'
                                                . '<td><div class="lp-team-cell">'
                                                    . '<img class="lp-team-avatar" src="' . htmlspecialchars($teamImgSrc) . '" alt="" loading="lazy" onerror="this.onerror=null;this.src=\'../../assets/images/default-league.svg\';">'
                                                    . '<div><div class="lp-team-name">' . htmlspecialchars((string) $row['team_name']) . '</div>'
                                                    . '<div class="lp-team-sub">' . htmlspecialchars((string) ($row['email'] ?? '')) . '</div></div>'
                                                . '</div></td>'
                                                . '<td><div class="lp-roster"><span class="lp-roster-count">' . $rosterCount . ' / ' . $maxPlayers . '</span>'
                                                    . '<div class="lp-progress"><span style="width:' . $rosterPct . '%"></span></div></div></td>'
                                                . '<td class="lp-cell-strong">' . htmlspecialchars((string) $row['team_repre']) . '</td>'
                                                . '<td class="lp-cell-muted">' . $created . '</td>'
                                                . '<td><span class="lp-row-status ' . $statusCls . '">' . htmlspecialchars((string) $row['status']) . '</span></td>'
                                                . '<td class="text-end"><a class="league-icon-btn league-action-primary" href="view_player.php?id=' . $team_id . '" title="Review team" aria-label="Review team"><i class="bi bi-eye-fill" aria-hidden="true"></i></a></td>'
                                            . '</tr>';
                                        }
                                        if ($count === 0) {
                                            echo '<tr><td colspan="7"><div class="lp-empty"><i class="bi bi-people"></i>'
                                                . '<p>No approved teams yet. Applications appear here once you approve them from Pending Teams.</p></div></td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div><!-- /card-body -->
                </div><!-- /card -->
            </div><!-- /row -->
        </div><!-- /container-fluid -->




                    <!-- ==================== ORGANIZER DETAIL MODAL ==================== -->
                    <div class="modal fade lp-modal" id="organizerDetailModal" tabindex="-1" aria-labelledby="organizerDetailModalLabel">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title" id="organizerDetailModalLabel"><i class="bi bi-person-badge-fill me-2"></i>Organizer Profile</h5>
                                        <span class="modal-subtitle">Full organizer record for this league</span>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="lp-profile-head">
                                        <div class="lp-org-avatar" aria-hidden="true"><?= $organizerInitials !== '' ? htmlspecialchars($organizerInitials) : '<i class="bi bi-person-fill"></i>' ?></div>
                                        <div>
                                            <h4 class="lp-org-name mb-1"><?= $organizerName !== '' ? $organizerName : '—' ?></h4>
                                            <div class="lp-org-chips mb-0">
                                                <span class="lp-org-chip"><i class="bi bi-award"></i>
                                                    <?= ($organizer['years_experience'] ?? '') !== '' && ($organizer['years_experience'] ?? null) !== null ? htmlspecialchars((string) $organizer['years_experience']) . ' year(s) experience' : 'Experience not set' ?>
                                                </span>
                                                <?php if (!empty($organizer['gender'])): ?>
                                                    <span class="lp-org-chip"><i class="bi bi-gender-ambiguous"></i><?= htmlspecialchars($organizer['gender']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Personal Information -->
                                    <div class="lp-panel">
                                        <h6><i class="bi bi-person-vcard"></i> Personal Information</h6>
                                        <div class="lp-panel-grid">
                                            <div class="lp-field"><small>Gender</small><span><?= htmlspecialchars((string) ($organizer['gender'] ?? '') ?: '—') ?></span></div>
                                            <div class="lp-field"><small>Date of Birth</small><span><?= !empty($organizer['date_of_birth']) ? date('F d, Y', strtotime($organizer['date_of_birth'])) : '—' ?></span></div>
                                        </div>
                                    </div>
                                    <!-- Contact Information -->
                                    <div class="lp-panel">
                                        <h6><i class="bi bi-telephone"></i> Contact Information</h6>
                                        <div class="lp-panel-grid">
                                            <div class="lp-field"><small>Email</small><span><?= htmlspecialchars((string) ($organizer['email'] ?? '') ?: '—') ?></span></div>
                                            <div class="lp-field"><small>Phone</small><span><?= htmlspecialchars((string) ($organizer['phone'] ?? '') ?: '—') ?></span></div>
                                            <div class="lp-field lp-field-wide"><small>Address</small><span><?= htmlspecialchars((string) ($organizer['address'] ?? '') ?: '—') ?></span></div>
                                        </div>
                                    </div>
                                    <!-- Organizer Profile -->
                                    <div class="lp-panel mb-0">
                                        <h6><i class="bi bi-patch-check"></i> Organizer Profile</h6>
                                        <div class="lp-panel-grid">
                                            <div class="lp-field"><small>Years of Experience</small><span><?= ($organizer['years_experience'] ?? null) !== null && ($organizer['years_experience'] ?? '') !== '' ? htmlspecialchars((string) $organizer['years_experience']) . ' year(s)' : '—' ?></span></div>
                                        </div>
                                        <div class="lp-field mt-3"><small>Credentials / Qualifications</small><span class="lp-prewrap"><?= nl2br(htmlspecialchars((string) ($organizer['credentials'] ?? '') ?: 'No credentials provided.')) ?></span></div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php include 'includes/footer.php'; ?>

                    <!-- Add Team Modal (kept for parity with the Add Team flow) -->
                    <div class="modal fade lp-modal" id="addTeamModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
                        <div class="modal-dialog modal-xl modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title"><i class="bi bi-people-fill me-2"></i>Add Team</h5>
                                        <span class="modal-subtitle">Register a new team for this league</span>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <?php if ($requirements): ?>
                                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                                        <strong>League Requirements:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Roster Size: <?= htmlspecialchars(rosterSizeLabel($requirements['min_players'] ?? 0, $requirements['max_players'] ?? 0), ENT_QUOTES, 'UTF-8') ?></li>
                                            <?php if ($requirements['min_male_players'] > 0): ?><li>Minimum Male Players: <?= $requirements['min_male_players'] ?></li><?php endif; ?>
                                            <?php if ($requirements['min_female_players'] > 0): ?><li>Minimum Female Players: <?= $requirements['min_female_players'] ?></li><?php endif; ?>
                                            <li>Category: <?= $requirements['category'] ?></li>
                                            <li>Division: <?= $requirements['division'] ?></li>
                                        </ul>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php endif; ?>
                                    <form class="row g-3" id="addTeamForm" enctype="multipart/form-data">
                                        <input type="hidden" name="addTeam" value="1">
                                        <input type="hidden" name="league_id" value="<?= $id ?>">
                                        <div class="col-md-12">
                                            <label class="form-label">Team Image <sup class="text-danger">*</sup></label>
                                            <input type="file" name="team_image" class="form-control" accept="image/*" required>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Team Name <sup class="text-danger">*</sup></label>
                                            <input type="text" name="team_name" class="form-control" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Representative Name <sup class="text-danger">*</sup></label>
                                            <input type="text" name="team_repre" class="form-control" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Email <sup class="text-danger">*</sup></label>
                                            <input type="email" name="email" class="form-control" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Phone <sup class="text-danger">*</sup></label>
                                            <input type="text" name="phone" class="form-control" required>
                                        </div>
                                        <div class="col-md-12 mt-3">
                                            <div id="addTeamValidationSummary" class="alert alert-warning d-none" role="alert">
                                                <strong>Validation Issues:</strong>
                                                <ul id="addTeamValidationList" class="mb-0 mt-2"></ul>
                                            </div>
                                            <div class="table-responsive">
                                            <table class="table table-bordered" id="addTeamPlayerTable">

                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th style="width:100px">Age</th>
                                                        <th>Role</th>
                                                        <th style="width:100px">Jersey No.</th>
                                                        <th>Gender</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="addTeamPlayerBody"></tbody>
                                            </table>
                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <div class="me-auto d-flex align-items-center gap-2">
                                                <button type="button" class="btn btn-success btn-sm" onclick="addTeamRow()">+ Add Player</button>
                                                <span class="text-muted">(Minimum <?= (int)($requirements['min_players'] ?? 5) ?> Players)</span>
                                            </div>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Add Team</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php include 'includes/announcement-modal-styles.php'; ?>
                    <!-- Announcement Modal -->
                    <div class="modal fade lp-modal" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div class="lp-am-head">
                                        <span class="lp-am-head-icon"><i class="bi bi-megaphone-fill"></i></span>
                                        <div>
                                            <h5 class="modal-title" id="announcementModalLabel">Announcements</h5>
                                            
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="announcementForm" novalidate>
                                        <div class="lp-am-composer">
                                            <div class="lp-am-composer-head">
                                                <span class="lp-am-composer-title"><i class="bi bi-pencil-square"></i> New announcement</span>
                                                <span class="lp-am-hint" id="announcementCounter" aria-hidden="true">0 / 5000</span>
                                            </div>
                                            <div class="lp-am-field">
                                                <label class="form-label" for="announcementTitle">Title</label>
                                                <input type="text" class="form-control" id="announcementTitle" name="title"
                                                       placeholder="e.g. Week 3 schedule moved to Saturday" maxlength="255"
                                                       autocomplete="off" aria-describedby="announcementTitleError" required>
                                                <div class="invalid-feedback" id="announcementTitleError">Please add a short title for this announcement.</div>
                                            </div>
                                            <div class="lp-am-field">
                                                <label class="form-label" for="announcementContent">Message</label>
                                                <textarea class="form-control" id="announcementContent" name="content" rows="4"
                                                          placeholder="Share the details teams need to know — venue, time, what changed…"
                                                          maxlength="5000" aria-describedby="announcementContentError" required></textarea>
                                                <div class="invalid-feedback" id="announcementContentError">Please write the message you want to send.</div>
                                            </div>
                                            <div class="lp-am-composer-foot">
                                                
                                                <button type="submit" class="lp-am-submit" id="announcementSubmit">
                                                    <i class="bi bi-send-fill"></i><span>Publish</span>
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    <div class="lp-am-list-head">
                                        <span class="lp-am-list-title"><i class="bi bi-clock-history"></i> Published announcements</span>
                                        <span class="lp-am-count" id="announcementCount">—</span>
                                    </div>
                                    <div id="announcementsList" class="lp-am-list" aria-live="polite" aria-busy="true">
                                        <div class="lp-am-skeleton"><span></span><span></span></div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="lp-am-btn-ghost" data-bs-dismiss="modal">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        const leagueId = <?php echo $id; ?>;
                        const csrfToken = <?= json_encode(csrfToken()) ?>;

                        // Lightweight client-side filter for the approved-teams table.
                        (function () {
                            const input = document.getElementById('lpTeamSearch');
                            const table = document.getElementById('leagueTeamsTable');
                            if (!input || !table) return;
                            const tbody = table.querySelector('tbody');
                            input.addEventListener('input', function () {
                                const q = this.value.trim().toLowerCase();
                                let visible = 0;
                                tbody.querySelectorAll('tr').forEach(function (row) {
                                    if (row.classList.contains('lp-search-empty')) return;
                                    const match = row.textContent.toLowerCase().indexOf(q) !== -1;
                                    row.style.display = match ? '' : 'none';
                                    if (match) visible++;
                                });
                                let note = tbody.querySelector('.lp-search-empty');
                                if (!visible && q !== '') {
                                    if (!note) {
                                        note = document.createElement('tr');
                                        note.className = 'lp-search-empty';
                                        note.innerHTML = '<td colspan="7"><div class="lp-empty"><i class="bi bi-search"></i><p>No teams match your search.</p></div></td>';
                                        tbody.appendChild(note);
                                    }
                                } else if (note) {
                                    note.remove();
                                }
                            });
                        })();

                        /* ------------------------- Announcements -------------------------
                           The modal posts to action/announcementAction.php. Every
                           mutation must carry the session CSRF token, otherwise the
                           endpoint answers 419 and nothing is published.
                           ---------------------------------------------------------------- */
                        (function () {
                            const modalEl = document.getElementById('announcementModal');
                            const form = document.getElementById('announcementForm');
                            if (!modalEl || !form) return;

                            const listEl   = document.getElementById('announcementsList');
                            const countEl  = document.getElementById('announcementCount');
                            const titleEl  = document.getElementById('announcementTitle');
                            const bodyEl   = document.getElementById('announcementContent');
                            const hintEl   = document.getElementById('announcementCounter');
                            const submitEl = document.getElementById('announcementSubmit');
                            const CONTENT_MAX = 5000;

                            function escapeHtml(value) {
                                return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
                                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
                                });
                            }

                            // MySQL DATETIMEs ("YYYY-MM-DD HH:MM:SS") do not parse reliably
                            // in every browser, so normalise them to ISO first.
                            function formatCreated(raw) {
                                const value = String(raw == null ? '' : raw).replace(' ', 'T');
                                const date = value ? new Date(value) : null;
                                if (!date || isNaN(date.getTime())) return 'Just now';
                                return date.toLocaleString(undefined, {
                                    month: 'short', day: 'numeric', year: 'numeric',
                                    hour: 'numeric', minute: '2-digit'
                                });
                            }

                            function feedback(kind, title, text) {
                                if (typeof Swal === 'undefined') return;
                                if (kind === 'success') {
                                    Swal.fire({
                                        toast: true, position: 'top-end', icon: 'success', title: title,
                                        showConfirmButton: false, timer: 2200, timerProgressBar: true
                                    });
                                    return;
                                }
                                Swal.fire({ icon: kind, title: title, text: text || '' });
                            }

                            // Every request carries the CSRF token the endpoint requires.
                            function postAnnouncement(payload) {
                                payload.csrf_token = csrfToken;
                                return fetch('action/announcementAction.php', {
                                    method: 'POST',
                                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                    body: new URLSearchParams(payload)
                                }).then(function (response) {
                                    return response.json().catch(function () {
                                        return { status: response.status, msg: 'Unexpected server response. Please try again.' };
                                    });
                                });
                            }

                            function errorMessage(res) {
                                if (res.status === 419) {
                                    return 'Your session expired. Refresh the page, then try again.';
                                }
                                return res.msg || 'Something went wrong. Please try again.';
                            }

                            function updateHint() {
                                const used = bodyEl.value.length;
                                hintEl.textContent = used + ' / ' + CONTENT_MAX;
                                hintEl.classList.toggle('is-near-limit', used > CONTENT_MAX - 250);
                            }

                            function setBusy(busy) {
                                submitEl.disabled = busy;
                                const label = submitEl.querySelector('span');
                                if (label) label.textContent = busy ? 'Publishing…' : 'Publish';
                            }

                            function showSkeleton() {
                                listEl.setAttribute('aria-busy', 'true');
                                listEl.innerHTML = '<div class="lp-am-skeleton"><span></span><span></span></div>';
                            }

                            function emptyState(icon, heading, copy) {
                                return `
                                    <div class="lp-am-empty">
                                        <span class="lp-am-empty-icon"><i class="bi ${icon}"></i></span>
                                        <h6>${escapeHtml(heading)}</h6>
                                        <p>${escapeHtml(copy)}</p>
                                    </div>`;
                            }

                            function renderAnnouncements(items) {
                                listEl.setAttribute('aria-busy', 'false');

                                if (!items || !items.length) {
                                    countEl.textContent = '0';
                                    listEl.innerHTML = emptyState(
                                        'bi-megaphone',
                                        'No announcements yet',
                                        'Use the composer above to share the first update with your teams.'
                                    );
                                    return;
                                }

                                countEl.textContent = items.length + (items.length === 1 ? ' post' : ' posts');
                                listEl.innerHTML = items.map(function (ann) {
                                    const id = parseInt(ann.id, 10) || 0;
                                    const title = escapeHtml(ann.title);
                                    const body = escapeHtml(ann.content).replace(/\r?\n/g, '<br>');
                                    return `
                                        <article class="lp-am-item">
                                            <span class="lp-am-item-icon" aria-hidden="true"><i class="bi bi-megaphone-fill"></i></span>
                                            <div class="lp-am-item-body">
                                                <div class="lp-am-item-head">
                                                    <h6 class="lp-am-item-title">${title}</h6>
                                                    <button type="button" class="lp-am-delete" data-announcement-id="${id}"
                                                            title="Delete announcement" aria-label="Delete announcement: ${title}">
                                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                                <p class="lp-am-item-text">${body}</p>
                                                <span class="lp-am-item-time"><i class="bi bi-clock" aria-hidden="true"></i>${escapeHtml(formatCreated(ann.created))}</span>
                                            </div>
                                        </article>`;
                                }).join('');
                            }

                            function loadAnnouncements() {
                                showSkeleton();
                                postAnnouncement({ action: 'get', league_id: leagueId })
                                    .then(function (res) {
                                        if (res.status === 200) {
                                            renderAnnouncements(res.announcements || []);
                                            return;
                                        }
                                        listEl.setAttribute('aria-busy', 'false');
                                        listEl.innerHTML = emptyState(
                                            'bi-exclamation-triangle',
                                            'Announcements could not be loaded',
                                            res.msg || 'Close this window and try again.'
                                        );
                                    })
                                    .catch(function () {
                                        listEl.setAttribute('aria-busy', 'false');
                                        listEl.innerHTML = emptyState(
                                            'bi-wifi-off',
                                            'You appear to be offline',
                                            'Check your connection and reopen this window.'
                                        );
                                    });
                            }

                            function deleteAnnouncement(announcementId) {
                                if (typeof Swal === 'undefined') return;
                                Swal.fire({
                                    title: 'Delete this announcement?',
                                    text: 'It disappears from this list and from every team notification.',
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonText: 'Delete',
                                    cancelButtonText: 'Cancel',
                                    confirmButtonColor: '#dc3545'
                                }).then(function (result) {
                                    if (!result.isConfirmed) return;

                                    postAnnouncement({ action: 'delete', announcement_id: announcementId })
                                        .then(function (res) {
                                            if (res.status === 200) {
                                                feedback('success', 'Announcement deleted');
                                                loadAnnouncements();
                                            } else {
                                                feedback(res.status >= 500 ? 'error' : 'warning', 'Unable to delete', errorMessage(res));
                                            }
                                        })
                                        .catch(function () {
                                            feedback('error', 'Unable to delete', 'The server could not be reached. Please try again.');
                                        });
                                });
                            }

                            // Kept global so any legacy inline handler keeps working.
                            window.deleteAnnouncement = deleteAnnouncement;

                            form.addEventListener('submit', function (e) {
                                e.preventDefault();

                                const title = titleEl.value.trim();
                                const message = bodyEl.value.trim();
                                const titleOk = title !== '';
                                const bodyOk = message !== '';

                                titleEl.classList.toggle('is-invalid', !titleOk);
                                bodyEl.classList.toggle('is-invalid', !bodyOk);

                                if (!titleOk) { titleEl.focus(); return; }
                                if (!bodyOk) { bodyEl.focus(); return; }

                                setBusy(true);
                                postAnnouncement({
                                    action: 'create',
                                    league_id: leagueId,
                                    title: title,
                                    content: message
                                }).then(function (res) {
                                    if (res.status === 201) {
                                        form.reset();
                                        titleEl.classList.remove('is-invalid');
                                        bodyEl.classList.remove('is-invalid');
                                        updateHint();
                                        feedback('success', 'Announcement published');
                                        loadAnnouncements();
                                    } else {
                                        feedback(res.status >= 500 ? 'error' : 'warning', 'Announcement not published', errorMessage(res));
                                    }
                                }).catch(function () {
                                    feedback('error', 'Announcement not published', 'The server could not be reached. Please try again.');
                                }).finally(function () {
                                    setBusy(false);
                                });
                            });

                            // Live character counter + inline validation reset.
                            bodyEl.addEventListener('input', updateHint);
                            titleEl.addEventListener('input', function () {
                                if (titleEl.value.trim() !== '') titleEl.classList.remove('is-invalid');
                            });

                            // Delegated delete handler keeps the markup free of inline JS.
                            listEl.addEventListener('click', function (e) {
                                const btn = e.target.closest('.lp-am-delete');
                                if (!btn) return;
                                const id = parseInt(btn.getAttribute('data-announcement-id'), 10);
                                if (id) deleteAnnouncement(id);
                            });

                            modalEl.addEventListener('show.bs.modal', function () {
                                titleEl.classList.remove('is-invalid');
                                bodyEl.classList.remove('is-invalid');
                                updateHint();
                                loadAnnouncements();
                            });

                            updateHint();
                        })();

                        const addTeamRequirements = <?= json_encode($requirements ?? []) ?>;
                        const addTeamMinimumPlayers = Math.max(1, Number(addTeamRequirements.min_players || 5));
                        const addTeamMaximumPlayers = Math.max(addTeamMinimumPlayers, Number(addTeamRequirements.max_players || addTeamMinimumPlayers));
                        const addTeamRosterIsFixed = <?= isFixedRosterSize($requirements['min_players'] ?? 0, $requirements['max_players'] ?? 0) ? 'true' : 'false' ?>;

                        function addTeamRow() {
                            const tbody = document.getElementById('addTeamPlayerBody');
                            if (tbody.rows.length >= addTeamMaximumPlayers) {
                                Swal.fire({
                                    icon: 'info',
                                    title: addTeamRosterIsFixed ? 'Fixed Roster Size' : 'Roster Limit Reached',
                                    text: addTeamRosterIsFixed
                                        ? `This league requires exactly ${addTeamMaximumPlayers} players.`
                                        : `This league allows a maximum of ${addTeamMaximumPlayers} players.`
                                });
                                return;
                            }
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td><input type="text" name="player_name[]" class="form-control" required onchange="validateAddTeamForm()"></td>
                                <td><input type="number" name="player_age[]" class="form-control" required onchange="validateAddTeamForm()"></td>
                                <td><input type="text" name="player_role[]" class="form-control" required></td>
                                <td><input type="number" name="jersey_number[]" class="form-control" required></td>
                                <td>
                                    <select name="gender[]" class="form-control" required onchange="validateAddTeamForm()">
                                        <option value="">Select</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </td>
                                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeAddTeamRow(this)">Delete</button></td>
                            `;
                            tbody.appendChild(row);
                            validateAddTeamForm();
                        }

                        function removeAddTeamRow(btn) {
                            const tbody = document.getElementById('addTeamPlayerBody');
                            if (tbody.rows.length <= addTeamMinimumPlayers) { alert(`Minimum ${addTeamMinimumPlayers} player(s) required!`); return; }
                            btn.closest('tr').remove();
                            validateAddTeamForm();
                        }

                        function validateAddTeamForm() {
                            const rows = document.querySelectorAll('#addTeamPlayerBody tr');
                            const r = addTeamRequirements;
                            let issues = [], maleCount = 0, femaleCount = 0, playerCount = 0;
                            rows.forEach((row, i) => {
                                const name = row.querySelector('input[name="player_name[]"]')?.value.trim();
                                const age = parseInt(row.querySelector('input[name="player_age[]"]')?.value);
                                const gender = row.querySelector('select[name="gender[]"]')?.value;
                                if (name) playerCount++;
                                if (!isNaN(age)) {
                                    if (r.min_age && age < r.min_age) issues.push(`Player ${i+1}: Age ${age} below minimum (${r.min_age})`);
                                    if (r.max_age && age > r.max_age) issues.push(`Player ${i+1}: Age ${age} above maximum (${r.max_age})`);
                                }
                                if (gender === 'Male') maleCount++;
                                if (gender === 'Female') femaleCount++;
                            });
                            if (addTeamRosterIsFixed) {
                                if (playerCount !== addTeamMinimumPlayers) issues.push(`This league requires exactly ${addTeamMinimumPlayers} player(s). You entered ${playerCount}.`);
                            } else {
                                if (playerCount < addTeamMinimumPlayers) issues.push(`Only ${playerCount} player(s). Minimum ${addTeamMinimumPlayers} required.`);
                                if (r.max_players && playerCount > r.max_players) issues.push(`${playerCount} players exceeds maximum (${r.max_players}).`);
                            }
                            if (r.min_male_players && maleCount < r.min_male_players) issues.push(`Only ${maleCount} male player(s). Minimum ${r.min_male_players} required.`);
                            if (r.min_female_players && femaleCount < r.min_female_players) issues.push(`Only ${femaleCount} female player(s). Minimum ${r.min_female_players} required.`);
                            if (r.category === 'Male' && femaleCount > 0) issues.push('Male-only league. Remove female players.');
                            if (r.category === 'Female' && maleCount > 0) issues.push('Female-only league. Remove male players.');
                            const summary = document.getElementById('addTeamValidationSummary');
                            document.getElementById('addTeamValidationList').innerHTML = issues.map(i => `<li>${i}</li>`).join('');
                            summary.classList.toggle('d-none', issues.length === 0);
                        }

                        // Initialize the league's configured minimum roster rows.
                        document.getElementById('addTeamModal').addEventListener('show.bs.modal', function() {
                            const tbody = document.getElementById('addTeamPlayerBody');
                            if (tbody.rows.length === 0) {
                                for (let i = 0; i < addTeamMinimumPlayers; i++) addTeamRow();
                            }
                        });

                        document.getElementById('addTeamForm').addEventListener('submit', function(e) {
                            e.preventDefault();
                            if (!document.getElementById('addTeamValidationSummary').classList.contains('d-none')) {
                                alert('Please fix validation issues before submitting.');
                                return;
                            }
                            Swal.fire({
                                title: 'Adding Team',
                                html: '<div class="spinner-border text-primary" role="status"></div><p class="mt-3">Processing...</p>',
                                allowOutsideClick: false, allowEscapeKey: false
                            });
                            fetch('action/leagueAction.php', { method: 'POST', body: new FormData(this) })
                            .then(r => r.json())
                            .then(res => {
                                if (res.status === 201) {
                                    Swal.fire({ icon:'success', title:'Team Added', text: res.msg, timer:1500, showConfirmButton:false }).then(() => location.reload());
                                } else {
                                    Swal.fire('Error', res.msg, 'error');
                                }
                            })
                            .catch(() => Swal.fire('Error', 'An error occurred. Please try again.', 'error'));
                        });

                        // Escape values interpolated into Swal's HTML. The league
                        // name is organizer-controlled, so never insert it raw.
                        function lpEscapeHtml(value) {
                            return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
                                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
                            });
                        }

                        function closeRegistration() {
                            const leagueName = <?= json_encode((string) $league_row['league_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                            const registrationUpcoming = <?= $registrationSummary['status'] === 'Upcoming' ? 'true' : 'false' ?>;
                            const opensOnLabel = <?= json_encode(strtotime((string) ($league_row['regis_start'] ?? '')) ? date('M j, Y', strtotime((string) $league_row['regis_start'])) : '') ?>;
                            Swal.fire({
                                title: 'Close registration?',
                                html: '<div class="text-start">'
                                    + (registrationUpcoming
                                        ? '<p class="mb-2">Registration has not opened yet. Closing now means it will <b>not</b> open' + (opensOnLabel ? ' on <b>' + lpEscapeHtml(opensOnLabel) + '</b>' : '') + '.</p>'
                                        : '<p class="mb-2">No new team applications will be accepted for <b>' + lpEscapeHtml(leagueName) + '</b>.</p>')
                                    + '<ul class="mb-2 ps-3">'
                                    + '<li><b>' + <?= (int) $approved_count ?> + ' of ' + <?= (int) $maxTeams ?> + '</b> slots filled</li>'
                                    + '<li><b>' + <?= (int) $pending_count ?> + '</b> application(s) still awaiting review — you can keep processing them</li>'
                                    + '<li>' + (registrationUpcoming
                                        ? 'The league stays <b>Upcoming</b> until its start date'
                                        : 'The league moves to <b>Ongoing</b> and schedule generation unlocks') + '</li>'
                                    + '</ul>'
                                    + '<p class="mb-0 text-muted small">You can reopen it while the registration end date has not passed.</p>'
                                    + '</div>',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#dc3545',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: '<i class="bi bi-lock-fill me-1"></i>Close registration',
                                cancelButtonText: 'Cancel'
                            }).then(function (result) {
                                if (!result.isConfirmed) return;
                                Swal.fire({
                                    title: 'Closing registration…',
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,
                                    didOpen: function () { Swal.showLoading(); }
                                });
                                $.ajax({
                                    url: 'action/leagueAction.php',
                                    method: 'POST',
                                    dataType: 'json',
                                    data: { closeRegistration: true, league_id: leagueId, csrf_token: csrfToken }
                                }).done(function (res) {
                                    if (Number(res.status) === 200) {
                                        Swal.fire({ icon: 'success', title: 'Registration Closed', text: res.msg, timer: 1500, showConfirmButton: false })
                                            .then(function () { location.reload(); });
                                        return;
                                    }
                                    Swal.fire('Unable to close registration', res.msg || 'Please try again.', 'error');
                                }).fail(function (xhr) {
                                    const message = xhr.responseJSON?.msg || 'The request failed. Please refresh the page and try again.';
                                    Swal.fire('Unable to close registration', message, 'error');
                                });
                            });
                        }

                        function reopenRegistration() {
                            const endLabel = <?= json_encode(strtotime((string) ($league_row['regis_end'] ?? '')) ? date('M j, Y', strtotime((string) $league_row['regis_end'])) : '') ?>;
                            Swal.fire({
                                title: 'Reopen registration?',
                                html: 'Teams can apply again' + (endLabel ? ' until <b>' + lpEscapeHtml(endLabel) + '</b>' : '') + '.',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonColor: '#16735e',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: '<i class="bi bi-unlock-fill me-1"></i>Reopen registration',
                                cancelButtonText: 'Cancel'
                            }).then(function (result) {
                                if (!result.isConfirmed) return;
                                Swal.fire({
                                    title: 'Reopening registration…',
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,
                                    didOpen: function () { Swal.showLoading(); }
                                });
                                $.ajax({
                                    url: 'action/leagueAction.php',
                                    method: 'POST',
                                    dataType: 'json',
                                    data: { reopenRegistration: true, league_id: leagueId, csrf_token: csrfToken }
                                }).done(function (res) {
                                    if (Number(res.status) === 200) {
                                        Swal.fire({ icon: 'success', title: 'Registration Reopened', text: res.msg, timer: 1500, showConfirmButton: false })
                                            .then(function () { location.reload(); });
                                        return;
                                    }
                                    Swal.fire('Unable to reopen registration', res.msg || 'Please try again.', 'error');
                                }).fail(function (xhr) {
                                    const message = xhr.responseJSON?.msg || 'The request failed. Please refresh the page and try again.';
                                    Swal.fire('Unable to reopen registration', message, 'error');
                                });
                            });
                        }

                        function archiveLeague(id, name) {
                            Swal.fire({
                                title: 'Move to Archive?',
                                html: `<b>${name}</b> will be moved to the archive.`,
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonColor: '#6c757d',
                                cancelButtonColor: '#aaa',
                                confirmButtonText: '<i class="bi bi-archive me-1"></i>Archive'
                            }).then(r => {
                                if (!r.isConfirmed) return;
                                $.post('action/leagueAction.php', { archiveLeague: true, league_id: id }, function(res) {
                                    try { res = JSON.parse(res); } catch(e) { Swal.fire('Error','Invalid response','error'); return; }
                                    if (res.status === 201) {
                                        Swal.fire({ icon:'success', title:'Archived!', text: res.msg, timer:1500, showConfirmButton:false }).then(() => location.href='league.php');
                                    } else { Swal.fire('Error', res.msg, 'error'); }
                                });
                            });
                        }
                        function deleteLeaguePermanent(id, name) {
                            Swal.fire({
                                title: 'Permanently Delete?',
                                html: `<b>${name}</b> will be <span class="text-danger fw-bold">permanently deleted</span> and cannot be recovered.`,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#dc3545',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: '<i class="bi bi-trash3 me-1"></i>Delete Permanently'
                            }).then(r => {
                                if (!r.isConfirmed) return;
                                $.post('action/leagueAction.php', { deleteLeague: true, league_id: id }, function(res) {
                                    try { res = JSON.parse(res); } catch(e) { Swal.fire('Error','Invalid response','error'); return; }
                                    if (res.status === 201) {
                                        Swal.fire({ icon:'success', title:'Deleted!', text: res.msg, timer:1500, showConfirmButton:false }).then(() => location.href='league.php');
                                    } else { Swal.fire('Error', res.msg, 'error'); }
                                });
                            });
                        }
                    </script>


    </body>

    </html>
