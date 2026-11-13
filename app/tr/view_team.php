<?php
require_once __DIR__ . '/../../config/dbconnect.php';
/** @var mysqli $conn */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/teamCorrectionDeadline.php';
require_once __DIR__ . '/../../includes/rosterSize.php';
require_once __DIR__ . '/../../includes/storageService.php';
require_once __DIR__ . '/../../includes/documentPreview.php';
require_once __DIR__ . '/../../includes/summaryStrip.php';
requireRole([2]);
requireActiveAccount($conn);

$userId = currentUserId();
$full = currentUserName();
$userType = currentRole();
$id = (int) ($_GET["id"] ?? 0);

expireTeamCorrectionDeadlines($conn);

// Verify team belongs to logged-in user
$fetch_team = "SELECT * FROM tbl_team WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $fetch_team);
mysqli_stmt_bind_param($stmt, 'ii', $id, $userId);
mysqli_stmt_execute($stmt);
$team_res = mysqli_stmt_get_result($stmt);
$team_row = mysqli_fetch_assoc($team_res);
mysqli_stmt_close($stmt);

if (!$team_row) {
    header("location: league.php");
    exit;
}

// League context — the hero subtitle, the details panel and the roster limits all
// come from the same row, so they are read in a single query.
$leagueStmt = $conn->prepare("SELECT id, league_name, sport, division, category, venue, min_player, max_player FROM tbl_league WHERE id = ? LIMIT 1");
$leagueStmt->bind_param('i', $team_row['league_id']);
$leagueStmt->execute();
$league_row = $leagueStmt->get_result()->fetch_assoc() ?: [];
$leagueStmt->close();

// Roster bounds go through the shared helper so the page cannot drift from
// eligibilityValidator (min clamped to 1, max_player 0 = "no upper limit").
$rosterBounds = normalizeRosterBounds($league_row['min_player'] ?? 5, $league_row['max_player'] ?? 5);
$rosterLabel  = rosterSizeLabel($rosterBounds['min'], $rosterBounds['max']);

// Roster — the list behind the roster table (corrections live on resubmit_team.php).
$players = [];
$ps = mysqli_prepare($conn, "SELECT * FROM tbl_player WHERE team_id = ? ORDER BY id ASC");
$id_str = (string) $id;
mysqli_stmt_bind_param($ps, 's', $id_str);
mysqli_stmt_execute($ps);
$pr = mysqli_stmt_get_result($ps);
while ($p = mysqli_fetch_assoc($pr)) $players[] = $p;
mysqli_stmt_close($ps);

// Submitted documents, grouped per player and for the team itself.
$submittedDocuments = [];
$submittedDocStmt = $conn->prepare("SELECT d.id,d.player_id,d.requirement_id,d.original_name,d.quality_status,u.name AS document_name,p.name AS player_name,r.applies_to FROM tbl_submitted_document d JOIN tbl_league_document_requirement r ON r.id=d.requirement_id JOIN tbl_utility u ON u.id=r.document_type_id LEFT JOIN tbl_player p ON p.id=d.player_id WHERE d.team_id=? ORDER BY r.applies_to,u.name,p.name");
$submittedDocStmt->bind_param('i', $id);
$submittedDocStmt->execute();
$submittedDocResult = $submittedDocStmt->get_result();
while ($doc = $submittedDocResult->fetch_assoc()) $submittedDocuments[] = $doc;
$submittedDocStmt->close();
$playerSubmittedDocuments = [];
$teamSubmittedDocuments = [];
foreach ($submittedDocuments as $document) {
    if ($document['applies_to'] === 'player' && !empty($document['player_id'])) {
        $playerSubmittedDocuments[(int) $document['player_id']][] = $document;
    } elseif ($document['applies_to'] === 'team') {
        $teamSubmittedDocuments[] = $document;
    }
}

/* ------------------------------------------------------------------
   Display values — one source for the hero, the KPI strip, the roster
   and the correction banner.
   ------------------------------------------------------------------ */
$teamStatus = trim((string) ($team_row['status'] ?? 'Pending')) ?: 'Pending';
$statusKey = strtolower($teamStatus);
if ($statusKey === 'correction requested') $statusKey = 'correction';
if ($statusKey === 'canceled') $statusKey = 'cancelled';
/* One definition per status feeds the hero pill, the KPI strip and the banners,
   so a new status only has to be described once:
     label = page wording, kpi = compact tile wording, class/accent/tint = styling. */
$statusDefinitions = [
    'approved'   => ['label' => 'Approved',             'kpi' => 'Approved',  'class' => 'is-approved',   'accent' => '#16735e', 'tint' => '#edf7f3'],
    'pending'    => ['label' => 'Pending review',       'kpi' => 'Pending',   'class' => 'is-pending',    'accent' => '#a56a14', 'tint' => '#fdf6e7'],
    'correction' => ['label' => 'Correction requested', 'kpi' => 'Returned',  'class' => 'is-correction', 'accent' => '#6941c6', 'tint' => '#f5effa'],
    'rejected'   => ['label' => 'Rejected',             'kpi' => 'Rejected',  'class' => 'is-rejected',   'accent' => '#b42335', 'tint' => '#fdeef0'],
    'expired'    => ['label' => 'Expired',              'kpi' => 'Expired',   'class' => 'is-expired',    'accent' => '#64708a', 'tint' => '#f1f3f8'],
    'cancelled'  => ['label' => 'Cancelled',            'kpi' => 'Cancelled', 'class' => 'is-cancelled',  'accent' => '#64708a', 'tint' => '#f1f3f8'],
];
$status = $statusDefinitions[$statusKey] ?? [
    'label'  => ucwords($teamStatus),
    'kpi'    => ucwords($teamStatus),
    'class'  => 'is-default',
    'accent' => '#64708a',
    'tint'   => '#f1f3f8',
];
$canResubmit = $statusKey === 'correction';
$canCancel   = $statusKey === 'pending';

$deadlineTs = !empty($team_row['correction_due_at']) ? strtotime((string) $team_row['correction_due_at']) : null;
$deadlineLabel = $deadlineTs ? date('M d, Y · h:i A', $deadlineTs) : '';
$deadlineHours = $deadlineTs ? max(0, (int) ceil(($deadlineTs - time()) / 3600)) : null;
$deadlineUrgent = $deadlineHours !== null && $deadlineHours <= 12;

$rosterCount = count($players);
$eligibleCount = $playersWithDocument = 0;
foreach ($players as $playerRow) {
    if (strcasecmp((string) ($playerRow['eligibility_status'] ?? ''), 'Eligible') === 0) $eligibleCount++;
    // "Has a file" mirrors the roster cell below: a typed document row or the
    // legacy tbl_player.document value.
    if (!empty($playerSubmittedDocuments[(int) $playerRow['id']]) || trim((string) ($playerRow['document'] ?? '')) !== '') {
        $playersWithDocument++;
    }
}
$totalDocumentCount = count($submittedDocuments);
$submittedOn = !empty($team_row['created']) ? date('M d, Y', strtotime((string) $team_row['created'])) : '—';
$leagueName  = trim((string) ($league_row['league_name'] ?? '')) ?: 'League';
$sportLabel  = trim((string) ($league_row['sport'] ?? '')) ?: '—';
$divisionLabel = trim((string) ($league_row['division'] ?? '')) ?: '—';
$categoryLabel = trim((string) ($league_row['category'] ?? '')) ?: '—';
$venueLabel  = trim((string) ($league_row['venue'] ?? '')) ?: '—';

// Team crest: Cloudinary URL, local upload, or initials fallback.
$teamImgRaw = (string) ($team_row['team_image'] ?? '');
$teamImgFile = basename($teamImgRaw);
$teamImgSrc = ($teamImgRaw !== '' && StorageService::isCloudinary($teamImgRaw))
    ? $teamImgRaw
    : (($teamImgFile !== '' && is_file(__DIR__ . '/../../assets/uploads/' . $teamImgFile))
        ? '../../assets/uploads/' . $teamImgFile
        : '');

$playerInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    return $initials !== '' ? $initials : '?';
};
$eligibilityMeta = static function ($status): array {
    $value = trim((string) $status) ?: 'Pending';
    return match ($value) {
        'Eligible'         => ['class' => 'is-approved', 'label' => 'Eligible'],
        'Needs Correction' => ['class' => 'is-pending',  'label' => 'Needs correction'],
        'Ineligible'       => ['class' => 'is-rejected', 'label' => 'Ineligible'],
        default            => ['class' => 'is-default',  'label' => 'Not reviewed'],
    };
};
$cleanRemarks = static function ($value): string {
    $text = trim((string) $value);
    if (in_array(strtolower($text), ['true', 'false', '1', '0'], true)) return '';
    return $text;
};
$teamRemarks = $cleanRemarks($team_row['remarks'] ?? '');
include 'includes/header.php';
?>
<?php include 'includes/application-page-styles.php'; ?>
<?php include 'includes/team-view-page-styles.php'; ?>
<?php include_once __DIR__ . '/../../includes/summary-strip-styles.php'; ?>

<body id="page-top" class="body tr-applications tr-team-view">
    <?php include 'includes/bar.php'; ?>
    <div class="league-page">
        <div class="league-shell">

            <nav class="tv-crumbs" aria-label="Breadcrumb">
                <a href="league.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> My teams</a>
                <i class="bi bi-chevron-right tv-crumb-sep" aria-hidden="true"></i>
                <span class="tv-crumb-current"><?= htmlspecialchars((string) $team_row['team_name']) ?></span>
            </nav>

            <!-- ==================== HERO ==================== -->
            <section class="tv-hero" aria-label="<?= htmlspecialchars((string) $team_row['team_name'], ENT_QUOTES) ?> registration">
                <span class="tv-hero-court" aria-hidden="true"></span>
                <div class="tv-hero-mark" aria-hidden="true">
                    <?php if ($teamImgSrc !== ''): ?>
                        <img src="<?= htmlspecialchars($teamImgSrc, ENT_QUOTES) ?>" alt="" loading="lazy" onerror="this.parentNode.innerHTML='<i class=&quot;bi bi-people-fill&quot;></i>';">
                    <?php else: ?>
                        <i class="bi bi-people-fill"></i>
                    <?php endif; ?>
                </div>
                <div class="tv-hero-copy">
                    <span class="tv-hero-eyebrow">Team registration</span>
                    <h1 class="tv-hero-title"><?= htmlspecialchars((string) $team_row['team_name']) ?></h1>
                    <p class="tv-hero-sub">
                        <?= htmlspecialchars($leagueName) ?> · <?= htmlspecialchars($sportLabel) ?> · <?= htmlspecialchars($divisionLabel) ?> · <?= htmlspecialchars($categoryLabel) ?>
                    </p>
                    <div class="tv-hero-meta">
                        <span><i class="bi bi-calendar3" aria-hidden="true"></i> Submitted <?= htmlspecialchars($submittedOn) ?></span>
                        <span><i class="bi bi-people" aria-hidden="true"></i> <?= (int) $rosterCount ?> player<?= $rosterCount === 1 ? '' : 's' ?> registered</span>
                        <?php if ($canResubmit && $deadlineLabel !== ''): ?>
                        <span class="tv-hero-meta-deadline<?= $deadlineUrgent ? ' is-urgent' : '' ?>"><i class="bi bi-alarm" aria-hidden="true"></i> Resubmit by <?= htmlspecialchars($deadlineLabel) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
            </section>

            <?php renderSummaryStrip([
                [
                    'label'  => 'Registration status',
                    'value'  => $status['kpi'],
                    'note'   => $status['label'] . ' · submitted ' . $submittedOn,
                    'accent' => $status['accent'],
                    'tint'   => $status['tint'],
                ],
                [
                    'label' => 'Roster size',
                    'value' => $rosterCount,
                    'note'  => $rosterLabel,
                    'notes' => [$eligibleCount . ' of ' . $rosterCount . ' player(s) eligible'],
                ],
                [
                    'label' => 'Documents',
                    'value' => $totalDocumentCount,
                    'note'  => $playersWithDocument . ' of ' . $rosterCount . ' player(s) have files',
                    'notes' => $teamSubmittedDocuments ? [count($teamSubmittedDocuments) . ' team-level file(s)'] : [],
                ],
                [
                    'label'  => 'Correction window',
                    'value'  => $canResubmit ? ($deadlineHours . 'h') : '—',
                    'note'   => $canResubmit ? 'Time left · closes ' . $deadlineLabel : 'No correction requested',
                    'accent' => $canResubmit ? ($deadlineUrgent ? '#b42335' : '#a56a14') : '#64708a',
                    'tint'   => $canResubmit ? ($deadlineUrgent ? '#fdeef0' : '#fdf6e7') : '#f1f3f8',
                ],
            ], 'Team registration summary'); ?>

            <!-- ==================== STATUS BANNER ==================== -->
            <?php if ($canResubmit): ?>
            <div class="tv-banner is-warn">
                <span class="tv-banner-icon" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></span>
                <div class="tv-banner-body">
                    <strong>Returned for correction</strong>
                    <p>Fix the items the organizer flagged, then resubmit. Only players marked “Needs correction”, their documents, the team name/logo and team-level documents can be changed.</p>
                    <div class="tv-banner-actions">
                        <span class="tv-deadline<?= $deadlineUrgent ? ' is-urgent' : '' ?>">
                            <i class="bi bi-alarm" aria-hidden="true"></i>
                            <?= $deadlineUrgent ? 'Due soon — ' : 'Due ' ?><?= htmlspecialchars($deadlineLabel) ?>
                        </span>
                        <a class="tv-action tv-action-primary" href="resubmit_team.php?id=<?= (int) $id ?>">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i> Correct and resubmit
                        </a>
                    </div>
                    <?php if ($teamRemarks !== ''): ?>
                    <div class="tv-remarks">
                        <strong>Organizer remarks</strong>
                        <?= nl2br(htmlspecialchars($teamRemarks)) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($canCancel): ?>
            <div class="tv-banner is-info">
                <span class="tv-banner-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                <div class="tv-banner-body">
                    <strong>Pending organizer review</strong>
                    <p>Your registration is in the organizer’s review queue. You will be notified once it is approved or returned for correction.</p>
                    <div class="tv-banner-actions">
                        <button type="button" class="tv-action tv-action-danger" id="cancelBtn" data-team-id="<?= (int) $id ?>">
                            <i class="bi bi-x-circle" aria-hidden="true"></i> Cancel registration
                        </button>
                    </div>
                </div>
            </div>
            <?php elseif ($statusKey === 'approved'): ?>
            <div class="tv-banner is-info">
                <span class="tv-banner-icon" aria-hidden="true"><i class="bi bi-patch-check-fill"></i></span>
                <div class="tv-banner-body">
                    <strong>Registration approved</strong>
                    <p>Your team is confirmed for <?= htmlspecialchars($leagueName) ?>. Watch the schedule and announcements for match details.</p>
                </div>
            </div>
            <?php elseif ($statusKey === 'rejected'): ?>
            <div class="tv-banner is-danger">
                <span class="tv-banner-icon" aria-hidden="true"><i class="bi bi-x-octagon-fill"></i></span>
                <div class="tv-banner-body">
                    <strong>Application rejected</strong>
                    <p>This decision is final and the application cannot be resubmitted.</p>
                    <?php if ($teamRemarks !== ''): ?>
                    <div class="tv-remarks"><strong>Reason</strong><?= nl2br(htmlspecialchars($teamRemarks)) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($statusKey === 'expired'): ?>
            <div class="tv-banner is-muted">
                <span class="tv-banner-icon" aria-hidden="true"><i class="bi bi-hourglass-bottom"></i></span>
                <div class="tv-banner-body">
                    <strong>Correction deadline expired</strong>
                    <p>This registration can no longer be corrected, resubmitted or approved, and cannot be counted as an official participant.</p>
                </div>
            </div>
            <?php elseif ($statusKey === 'cancelled'): ?>
            <div class="tv-banner is-muted">
                <span class="tv-banner-icon" aria-hidden="true"><i class="bi bi-slash-circle"></i></span>
                <div class="tv-banner-body">
                    <strong>Registration cancelled</strong>
                    <p>This application was cancelled. Browse the leagues page to register again.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ==================== TEAM DETAILS ==================== -->
            <div class="section-head">
                <div>
                    <span class="section-eyebrow">Registration file</span>
                    <h2 class="section-title">Team details</h2>
                </div>
                
            </div>
            <div class="activity-section dash-animate dash-animate-d1">
                <div class="activity-header">
                    <div class="activity-title"><i class="bi bi-info-circle"></i> Submitted information</div>
                    
                </div>
                <div class="tv-facts">
                    <div class="tv-fact"><small>Representative</small><span><?= htmlspecialchars((string) ($team_row['team_repre'] ?? '') ?: '—') ?></span></div>
                    <div class="tv-fact"><small>Email</small><span><?= htmlspecialchars((string) ($team_row['email'] ?? '') ?: '—') ?></span></div>
                    <div class="tv-fact"><small>Phone</small><span><?= htmlspecialchars((string) ($team_row['phone'] ?? '') ?: '—') ?></span></div>
                    <div class="tv-fact"><small>Registered on</small><span><?= htmlspecialchars($submittedOn) ?></span></div>
                    <div class="tv-fact"><small>League</small><span><?= htmlspecialchars($leagueName) ?></span></div>
                    <div class="tv-fact"><small>Sport</small><span><?= htmlspecialchars($sportLabel) ?></span></div>
                    <div class="tv-fact"><small>Division · Category</small><span><?= htmlspecialchars($divisionLabel) ?> · <?= htmlspecialchars($categoryLabel) ?></span></div>
                    <div class="tv-fact"><small>Venue</small><span><?= htmlspecialchars($venueLabel) ?></span></div>
                </div>
            </div>

            <!-- ==================== ROSTER ==================== -->
            <div class="section-head" style="margin-top:22px;">
                <div>
                    <span class="section-eyebrow">Line-up</span>
                    <h2 class="section-title">Team roster</h2>
                </div>
                
            </div>
            <div class="activity-section dash-animate dash-animate-d2">
                <div class="activity-header">
                    <div class="activity-title">
                        <i class="bi bi-people-fill"></i> Player List
                        
                    </div>
                    
                </div>
                <?php if ($rosterCount === 0): ?>
                <div style="padding:40px 22px;text-align:center;">
                    <p class="mb-0" style="color:#8b95a8;font-size:13px;">This registration has no players yet.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table activity-table align-middle">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:56px;">#</th>
                                <th>Player</th>
                                <th style="width:130px;">Age / Gender</th>
                                <th style="width:120px;">Role</th>
                                <th class="text-center" style="width:90px;">Jersey</th>
                                <th style="width:170px;">Document</th>
                                <th style="width:210px;">Eligibility</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $index => $player):
                                $rowDocuments = $playerSubmittedDocuments[(int) $player['id']] ?? [];
                                $eligibility = $eligibilityMeta($player['eligibility_status'] ?? 'Pending');
                                $eligibilityRemarks = $cleanRemarks($player['eligibility_remarks'] ?? '');
                                $jersey = trim((string) ($player['jersey_number'] ?? ''));
                                if ($jersey === '0') $jersey = '';
                            ?>
                            <tr>
                                <td class="text-center"><span class="app-index"><?= $index + 1 ?></span></td>
                                <td>
                                    <div class="tv-player">
                                        <span class="tv-avatar" aria-hidden="true"><?= htmlspecialchars($playerInitials((string) $player['name'])) ?></span>
                                        <div>
                                            <div class="tv-player-name"><?= htmlspecialchars((string) $player['name']) ?></div>
                                            <div class="tv-player-sub"><?= htmlspecialchars(trim((string) ($player['barangay'] ?? '')) ?: 'Team player') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="tv-cell-muted"><?= (int) $player['age'] ?> yrs · <span class="text-capitalize"><?= htmlspecialchars((string) $player['gender']) ?></span></td>
                                <td><span class="tv-role"><?= htmlspecialchars((string) $player['role']) ?></span></td>
                                <td class="text-center tv-cell-strong"><?= $jersey !== '' ? '#' . htmlspecialchars($jersey) : '—' ?></td>
                                <td>
                                    <?php if ($rowDocuments): ?>
                                        <div class="d-flex flex-column gap-1">
                                            <?php foreach ($rowDocuments as $document): ?>
                                            <a class="tv-doc-link" href="../../includes/downloadDocument.php?source=typed&amp;id=<?= (int) $document['id'] ?>&amp;mode=preview" data-doc-preview data-doc-kind="<?= documentPreviewKind((string) ($document['original_name'] ?? '')) ?>" data-doc-title="<?= htmlspecialchars((string) $document['document_name'], ENT_QUOTES) ?>" data-doc-subtitle="<?= htmlspecialchars(trim((string) ($document['original_name'] ?? '')) . ' · ' . (string) $player['name'], ENT_QUOTES) ?>">
                                                <i class="bi bi-file-earmark-text" aria-hidden="true"></i> <?= htmlspecialchars((string) $document['document_name']) ?>
                                            </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif (!empty($player['document'])): ?>
                                        <a class="tv-doc-link" href="../../includes/downloadDocument.php?source=player&amp;id=<?= (int) $player['id'] ?>&amp;mode=preview" data-doc-preview data-doc-kind="<?= documentPreviewKind((string) $player['document']) ?>" data-doc-title="Eligibility document" data-doc-subtitle="<?= htmlspecialchars((string) $player['name'], ENT_QUOTES) ?>">
                                            <i class="bi bi-file-earmark-text" aria-hidden="true"></i> View document
                                        </a>
                                    <?php else: ?>
                                        <span class="tv-missing"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> Not uploaded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="app-status <?= $eligibility['class'] ?>"><?= htmlspecialchars($eligibility['label']) ?></span>
                                    <?php if ($eligibilityRemarks !== ''): ?>
                                        <span class="tv-elig-notes"><?= htmlspecialchars($eligibilityRemarks) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($teamSubmittedDocuments): ?>
            <div class="section-head" style="margin-top:22px;">
                <div>
                    <span class="section-eyebrow">Paperwork</span>
                    <h2 class="section-title">Team documents</h2>
                </div>
                <span class="section-meta"><?= count($teamSubmittedDocuments) ?> file(s)</span>
            </div>
            <div class="activity-section dash-animate dash-animate-d3">
                <div class="tv-doc-grid">
                    <?php foreach ($teamSubmittedDocuments as $document): ?>
                    <div class="tv-doc-card">
                        <div class="tv-doc-card-head">
                            <span class="tv-doc-card-icon" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span>
                            <div class="tv-doc-card-name"><?= htmlspecialchars((string) $document['document_name']) ?></div>
                        </div>
                        <div class="tv-doc-card-meta"><?= htmlspecialchars((string) $document['original_name']) ?></div>
                        <a class="tv-doc-link" href="../../includes/downloadDocument.php?source=typed&amp;id=<?= (int) $document['id'] ?>&amp;mode=preview" data-doc-preview data-doc-kind="<?= documentPreviewKind((string) ($document['original_name'] ?? '')) ?>" data-doc-title="<?= htmlspecialchars((string) $document['document_name'], ENT_QUOTES) ?>" data-doc-subtitle="<?= htmlspecialchars(trim((string) ($document['original_name'] ?? '')), ENT_QUOTES) ?>">
                            <i class="bi bi-eye" aria-hidden="true"></i> Open file
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /league-shell -->
    </div><!-- /league-page -->

    <?php include 'includes/footer.php'; ?>
    <?php include __DIR__ . '/../../includes/documentPreviewModal.php'; ?>

    <script>
        document.getElementById('cancelBtn')?.addEventListener('click', function () {
            const teamId = this.getAttribute('data-team-id');
            Swal.fire({
                title: 'Cancel registration?',
                text: 'This withdraws your team from the league. You would need to register again later.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, cancel registration',
                cancelButtonText: 'Keep my team',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (!result.isConfirmed) return;
                fetch('action/cancelTeam.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ team_id: teamId })
                })
                .then(response => {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (data.status === 200) {
                        Swal.fire({ icon: 'success', title: 'Cancelled', text: data.msg, timer: 2000 })
                            .then(() => { window.location.href = 'league.php'; });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Unable to cancel', text: data.msg, timer: 3000 });
                    }
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Unable to cancel', text: 'The request failed. Please try again.', timer: 3000 }));
            });
        });
    </script>
</body>
</html>
