<?php
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../config/leagueStatus.php';
require_once __DIR__ . '/../../includes/storageService.php';
require_once __DIR__ . '/../../includes/documentPreview.php';
require_once __DIR__ . '/../../includes/rosterSize.php';
require_once __DIR__ . '/../../includes/summaryStrip.php';
/** @var mysqli $conn */
session_start();
$userId = currentUserId();
$full = currentUserName();
$userType = currentRole();
$id = intval($_GET["id"] ?? 0);
requireRole([1]);
requireActiveAccount($conn);
if (!organizerOwnsTeam($conn, $id, (int)$userId)) {
    $_SESSION['league_flash'] = ['danger', "That team belongs to another organizer's league."];
    header('Location: league.php'); exit;
}

$_SESSION['loggin'];
$_SESSION['loggin'] == true;
$loggin = true;
$time = strtotime("-10 year", time());
$date = date("Y-m-d", $time);
$today = date("M/d/Y");

$teamStmt = $conn->prepare("SELECT * FROM tbl_team WHERE id=? LIMIT 1");
$teamStmt->bind_param('i', $id); $teamStmt->execute();
$team_row = $teamStmt->get_result()->fetch_assoc(); $teamStmt->close();

// League context: hero artwork, roster rules and the status chips.
$leagueStmt = $conn->prepare("SELECT * FROM tbl_league WHERE id=? LIMIT 1");
$leagueStmt->bind_param('i', $team_row['league_id']); $leagueStmt->execute();
$league_row = $leagueStmt->get_result()->fetch_assoc() ?: []; $leagueStmt->close();

$playerDocumentRequirements = [];
$teamDocumentRequirements = [];
$requirementStmt = $conn->prepare("SELECT r.id,r.applies_to,r.is_required,u.name FROM tbl_league_document_requirement r JOIN tbl_utility u ON u.id=r.document_type_id WHERE r.league_id=? ORDER BY r.id");
$requirementStmt->bind_param('i', $team_row['league_id']);
$requirementStmt->execute();
$requirementResult = $requirementStmt->get_result();
while ($requirement = $requirementResult->fetch_assoc()) {
    if ($requirement['applies_to'] === 'player') $playerDocumentRequirements[] = $requirement;
    else $teamDocumentRequirements[] = $requirement;
}
$requirementStmt->close();

$playerDocuments = [];
$teamDocuments = [];
$documentStmt = $conn->prepare("SELECT * FROM tbl_submitted_document WHERE team_id=? ORDER BY id");
$documentStmt->bind_param('i', $id);
$documentStmt->execute();
$documentResult = $documentStmt->get_result();
while ($document = $documentResult->fetch_assoc()) {
    if ($document['player_id'] === null) $teamDocuments[(int)$document['requirement_id']] = $document;
    else $playerDocuments[(int)$document['player_id']][(int)$document['requirement_id']] = $document;
}
$documentStmt->close();

/* ------------------------------------------------------------------
   Review analytics — the hero, the summary strip, the roster table and
   the document cards all read from these numbers.
   ------------------------------------------------------------------ */
$players = [];
$playerStmt = $conn->prepare("SELECT * FROM tbl_player WHERE team_id=? ORDER BY id");
$playerStmt->bind_param('i', $id); $playerStmt->execute();
$playerResult = $playerStmt->get_result();
while ($playerRow = $playerResult->fetch_assoc()) { $players[] = $playerRow; }
$playerStmt->close();

$rosterCount     = count($players);
$maxPlayers      = (int) ($league_row['max_player'] ?? 0);
$minPlayers      = max(1, (int) ($league_row['min_player'] ?? 5));
$rosterLabel     = rosterSizeLabel($minPlayers, $maxPlayers);
$rosterShortfall = max(0, $minPlayers - $rosterCount);

$eligibleCount = $pendingEligibility = $correctionCount = $ineligibleCount = 0;
$correctionPlayerNames = [];
foreach ($players as $playerRow) {
    switch ((string) ($playerRow['eligibility_status'] ?? 'Pending')) {
        case 'Eligible':         $eligibleCount++;     break;
        case 'Needs Correction': $correctionCount++;   $correctionPlayerNames[] = (string) $playerRow['name']; break;
        case 'Ineligible':       $ineligibleCount++;   break;
        default:                 $pendingEligibility++; break;
    }
}

// Document counters — typed requirements are per player, so they multiply.
$submittedDocCount = count($teamDocuments);
foreach ($playerDocuments as $playerDocSet) { $submittedDocCount += count($playerDocSet); }
if ($playerDocumentRequirements) {
    $requiredDocSlots = count($teamDocumentRequirements) + count($playerDocumentRequirements) * $rosterCount;
} else {
    // Legacy single-document column on tbl_player.
    $requiredDocSlots = count($teamDocumentRequirements) + $rosterCount;
    foreach ($players as $playerRow) { if (!empty($playerRow['document'])) $submittedDocCount++; }
}

$teamStatus    = trim((string) ($team_row['status'] ?? 'Pending')) ?: 'Pending';
$teamStatusKey = strtolower($teamStatus);
if ($teamStatusKey === 'correction requested') $teamStatusKey = 'correction';
$canReview     = $teamStatusKey === 'pending';
$statusPill    = match ($teamStatusKey) {
    'approved' => 'tvr-pill-approved',
    'rejected' => 'tvr-pill-rejected',
    'pending'  => 'tvr-pill-pending',
    default    => 'tvr-pill-correction',
};
$statusIcon    = match ($teamStatusKey) {
    'approved' => 'bi-patch-check-fill',
    'rejected' => 'bi-x-octagon-fill',
    'pending'  => 'bi-hourglass-split',
    default    => 'bi-arrow-repeat',
};
$submittedOn   = !empty($team_row['created']) ? date('M d, Y', strtotime($team_row['created'])) : '—';

/* Return for correction is only possible when the representative has something
   they are allowed to change: on resubmission only the players flagged "Needs
   Correction" (and their documents) plus the team name, logo and team-level
   documents are editable — see app/tr/action/reapplyTeam.php. With no player
   marked "Needs Correction" the application must be approved or rejected. */
$nothingToCorrect = $canReview && $correctionCount === 0;

$leagueId      = (int) ($team_row['league_id'] ?? 0);
$leagueName    = trim((string) ($league_row['league_name'] ?? '')) ?: 'League';
$sportLabel    = trim((string) ($league_row['sport'] ?? '')) ?: '—';
$divisionLabel = trim((string) ($league_row['division'] ?? '')) ?: '—';
$categoryLabel = trim((string) ($league_row['category'] ?? '')) ?: '—';
$backHref      = ($canReview && $leagueId) ? 'pending_teams.php?id=' . $leagueId : ($leagueId ? 'league_info.php?id=' . $leagueId : 'league.php');
$backLabel     = $canReview ? 'Back to team review queue' : 'Back to league overview';

// Hero artwork (league cover) + team crest, both Cloudinary-aware.
$heroRaw  = (string) ($league_row['league_image'] ?? '');
$heroFile = basename($heroRaw);
$heroBg   = ($heroRaw !== '' && StorageService::isCloudinary($heroRaw))
    ? $heroRaw
    : (($heroFile !== '' && is_file(__DIR__ . '/../../assets/uploads/' . $heroFile))
        ? '../../assets/uploads/' . $heroFile
        : '../../assets/images/default-league.svg');

$crestRaw  = (string) ($team_row['team_image'] ?? '');
$crestFile = basename($crestRaw);
$teamCrest = ($crestRaw !== '' && StorageService::isCloudinary($crestRaw))
    ? $crestRaw
    : (($crestFile !== '' && is_file(__DIR__ . '/../../assets/uploads/' . $crestFile))
        ? '../../assets/uploads/' . $crestFile
        : '../../assets/images/default-league.svg');

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
                            <?php if ($leagueId): ?>
                            <a href="league_info.php?id=<?= $leagueId ?>"><?= htmlspecialchars($leagueName) ?></a>
                            <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
                            <?php endif; ?>
                            <?php if ($canReview && $leagueId): ?>
                            <a href="pending_teams.php?id=<?= $leagueId ?>">Team review</a>
                            <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
                            <?php endif; ?>
                            <span class="lp-crumb-current"><?= htmlspecialchars((string) $team_row['team_name']) ?></span>
                        </nav>

                        <!-- ==================== TEAM HERO ==================== -->
                        <div class="lp-hero tvr-hero dash-animate">
                            <div class="lp-hero-bg" style="background-image:url('<?= htmlspecialchars($heroBg, ENT_QUOTES) ?>');"></div>
                            <div class="lp-hero-inner">
                                <div class="tvr-hero-head">
                                    <img class="tvr-hero-crest" src="<?= htmlspecialchars($teamCrest, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $team_row['team_name'], ENT_QUOTES) ?> crest" onerror="this.onerror=null;this.src='../../assets/images/default-league.svg';">
                                    <div class="tvr-hero-copy">
                                        
                                        <h1 class="lp-hero-title"><?= htmlspecialchars((string) $team_row['team_name']) ?></h1>
                                        <div class="lp-hero-meta">
                                            <div class="lp-meta-item"><i class="bi bi-person-badge" aria-hidden="true"></i><span><small>Representative</small><?= htmlspecialchars((string) $team_row['team_repre']) ?></span></div>
                                            <div class="lp-meta-item"><i class="bi bi-envelope" aria-hidden="true"></i><span><small>Email</small><?= htmlspecialchars((string) $team_row['email']) ?></span></div>
                                            <div class="lp-meta-item"><i class="bi bi-telephone" aria-hidden="true"></i><span><small>Phone</small><?= htmlspecialchars((string) ($team_row['phone'] ?? '') ?: '—') ?></span></div>
                                            <div class="lp-meta-item"><i class="bi bi-calendar2-check" aria-hidden="true"></i><span><small>Submitted</small><?= $submittedOn ?></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ==================== KPI ROW ==================== -->
                        <?php renderSummaryStrip([
                            [
                                'label'  => 'Roster size',
                                'value'  => $rosterCount,
                                'note'   => $rosterLabel,
                                'notes'  => [$rosterShortfall > 0 ? $rosterShortfall . ' short of the minimum' : 'Minimum requirement met'],
                                'accent' => $rosterShortfall > 0 ? '#a56a14' : '#17356f',
                                'tint'   => $rosterShortfall > 0 ? '#fdf6e7' : '#f1f4fa',
                            ],
                            [
                                'label' => 'Documents',
                                'value' => $submittedDocCount,
                                'note'  => $requiredDocSlots > 0 ? 'of ' . $requiredDocSlots . ' required files' : 'No documents required',
                            ],
                            [
                                'label' => 'Eligibility',
                                'value' => $eligibleCount,
                                'note'  => 'of ' . $rosterCount . ' cleared',
                                'notes' => array_values(array_filter([
                                    $pendingEligibility > 0 ? $pendingEligibility . ' not reviewed' : '',
                                    $correctionCount    > 0 ? $correctionCount . ($correctionCount === 1 ? ' needs correction' : ' need correction') : '',
                                    $ineligibleCount    > 0 ? $ineligibleCount . ($ineligibleCount === 1 ? ' is ineligible' : ' are ineligible') : '',
                                ])),
                            ],
                            [
                                'label'  => 'Application',
                                'value'  => $teamStatus,
                                'note'   => 'Submitted ' . $submittedOn,
                                'accent' => $canReview ? '#a56a14' : ($teamStatusKey === 'approved' ? '#16735e' : '#64708a'),
                                'tint'   => $canReview ? '#fdf6e7' : ($teamStatusKey === 'approved' ? '#edf7f3' : '#f1f3f8'),
                            ],
                        ], 'Team review summary'); ?>

                        <?php if (!empty($team_row['remarks'])): ?>
                        <div class="tvr-note tvr-note-danger dash-animate dash-animate-d2">
                            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                            <div>
                                <strong><?= $canReview ? 'Returned for correction' : 'Organizer remarks' ?></strong>
                                <p><?= nl2br(htmlspecialchars((string) $team_row['remarks'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php
                        // Automated quality flags recorded during upload.
                        $preliminaryFlagsJson = $team_row['preliminary_flags'] ?? null;
                        $preliminaryFlags = $preliminaryFlagsJson ? json_decode($preliminaryFlagsJson, true) : [];
                        if (!empty($preliminaryFlags['document_quality'])):
                        ?>
                        <div class="tvr-note dash-animate dash-animate-d2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                            <div>
                                <strong>Automated quality flags</strong>
                                <p>The quality checker flagged one or more documents during upload (unclear, illegible or unverified content). Please review them manually.</p>
                                <ul>
                                    <?php foreach ($preliminaryFlags['document_quality'] as $flag): ?>
                                        <li><?= htmlspecialchars((string) $flag) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php
                        /* Review helpers — shared by the roster cells and the team
                           document cards so both read exactly the same way. */
                        $cleanRemarks = static function ($value): string {
                            $text = trim((string) $value);
                            // Legacy rows stored the confirm-dialog boolean ("true"/"false").
                            if (in_array(strtolower($text), ['true', 'false', '1', '0'], true)) {
                                return '';
                            }
                            return $text;
                        };
                        $docQualityMeta = static function (array $document): array {
                            $status = (string) ($document['quality_status'] ?? '');
                            $tooltip = '';
                            if (!empty($document['quality_metrics'])) {
                                $metrics = json_decode((string) $document['quality_metrics'], true);
                                if (is_array($metrics) && !empty($metrics['pages'])) {
                                    $pageParts = [];
                                    foreach ($metrics['pages'] as $page) {
                                        $line = 'Page ' . ($page['page'] ?? '')
                                            . ': sharpness=' . number_format($page['rel_sharpness'] ?? 0, 2)
                                            . ' (worst tile ' . number_format($page['rel_sharp_p10'] ?? 0, 2) . ')'
                                            . ', contrast=' . number_format(($page['contrast'] ?? 0) * 100, 1) . '%'
                                            . ', text height=' . (isset($page['text_height']) ? number_format((float)$page['text_height'], 0) . ' px' : 'n/a');
                                        if (!empty($page['ocr_chars'])) {
                                            $line .= ', OCR ' . (int)$page['ocr_chars'] . ' chars @ '
                                                . number_format((float)($page['ocr_confidence'] ?? 0), 0) . '%';
                                        }
                                        $pageParts[] = $line;
                                        if (!empty($page['ocr_missing_keywords'])) {
                                            $pageParts[] = '   expected but not found: ' . implode(', ', (array)$page['ocr_missing_keywords']);
                                        }
                                        if (!empty($page['page_reason'])) {
                                            $pageParts[] = '   verdict: ' . $page['page_reason'];
                                        }
                                    }
                                    $tooltip = implode("\n", $pageParts);
                                }
                            }
                            if ($status === 'Flagged') {
                                return ['class' => 'is-warn', 'icon' => 'bi-exclamation-triangle-fill', 'label' => 'Needs review', 'tooltip' => $tooltip];
                            }
                            if ($status === 'Clear') {
                                return ['class' => 'is-ok', 'icon' => 'bi-shield-check', 'label' => 'Passed automated check', 'tooltip' => $tooltip];
                            }
                            if ($status === 'Pending') {
                                return ['class' => 'is-muted', 'icon' => 'bi-question-circle', 'label' => 'Quality not checked', 'tooltip' => $tooltip];
                            }
                            return ['class' => 'is-muted', 'icon' => 'bi-question-circle', 'label' => 'Quality not checked', 'tooltip' => $tooltip];
                        };
                        $docReviewMeta = static function (array $document): array {
                            $status = (string) ($document['review_status'] ?? 'Pending');
                            return match ($status) {
                                'Accepted' => ['class' => 'is-approved', 'label' => 'Accepted',       'status' => $status],
                                'Rejected' => ['class' => 'is-cancelled', 'label' => 'Rejected',       'status' => $status],
                                default    => ['class' => 'is-pending',   'label' => 'Pending review', 'status' => $status],
                            };
                        };
                        $playerInitials = static function (string $name): string {
                            $parts    = preg_split('/\s+/', trim($name)) ?: [];
                            $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
                            return $initials !== '' ? $initials : '?';
                        };
                        ?>

                        <!-- ==================== ROSTER ==================== -->
                        <div class="activity-section dash-animate dash-animate-d3">
                            <div class="activity-header">
                                <div class="activity-title">
                                    <i class="bi bi-people-fill"></i> Team roster
                                    
                                </div>
                                <?php if ($rosterCount > 0): ?>
                                <div class="tvr-toolbar">
                                    <div class="tvr-search">
                                        <i class="bi bi-search"></i>
                                        <input type="search" id="tvrRosterSearch" placeholder="Search player, role or status" autocomplete="off" aria-label="Search roster">
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="tvr-steps" aria-label="How to review this team">
                                <span class="tvr-step"><b>1</b> Check each player&rsquo;s documents</span>
                                <span class="tvr-step"><b>2</b> Set the player&rsquo;s eligibility</span>
                                <span class="tvr-step"><b>3</b> Approve, return or reject the team below</span>
                            </div>
                            <?php if ($rosterCount === 0): ?>
                            <div class="tvr-empty">
                                <i class="bi bi-person-x"></i>
                                <p>This team has not submitted any players yet.</p>
                            </div>
                            <?php else: ?>
                            <div class="tvr-table-wrap">
                                <table class="table activity-table tvr-table tvr-roster-table align-middle mb-0" id="tvrRosterTable">
                                    <thead>
                                        <tr>
                                            <th style="width:60px;">#</th>
                                            <th>Player</th>
                                            <th style="width:140px;">Age · Gender</th>
                                            <th style="width:340px;">Documents</th>
                                            <th style="width:190px;">Eligibility</th>
                                            <th style="width:170px;" class="text-end">Decision</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $count = 0;
                                        foreach ($players as $player_row):
                                            $count++;
                                            $playerName        = (string) $player_row['name'];
                                            $eligibilityStatus = (string) ($player_row['eligibility_status'] ?? 'Pending');
                                            $eligibilityClass  = match ($eligibilityStatus) {
                                                'Eligible'         => 'is-approved',
                                                'Ineligible'       => 'is-cancelled',
                                                'Needs Correction' => 'is-pending',
                                                default            => 'is-neutral',
                                            };
                                            $eligibilityLabel  = match ($eligibilityStatus) {
                                                'Eligible'         => 'Eligible',
                                                'Ineligible'       => 'Ineligible',
                                                'Needs Correction' => 'Needs correction',
                                                default            => 'Not reviewed',
                                            };
                                            $eligibilityRemarks = $cleanRemarks($player_row['eligibility_remarks'] ?? '');
                                            $jersey     = trim((string) ($player_row['jersey_number'] ?? ''));
                                            $playerRole = trim((string) ($player_row['role'] ?? ''));
                                            $searchBlob = strtolower($playerName . ' ' . $playerRole . ' ' . $eligibilityStatus);
                                            $playerMeta = [];
                                            if ($jersey !== '')     $playerMeta[] = 'Jersey #' . $jersey;
                                            if ($playerRole !== '') $playerMeta[] = $playerRole;
                                        ?>
                                        <tr data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES) ?>">
                                            <td><span class="tvr-index"><?= $count ?></span></td>
                                            <td>
                                                <div class="tvr-player-cell">
                                                    <span class="tvr-player-avatar" aria-hidden="true"><?= htmlspecialchars($playerInitials($playerName)) ?></span>
                                                    <div>
                                                        <div class="tvr-player-name"><?= htmlspecialchars($playerName) ?></div>
                                                        <div class="tvr-player-sub"><?= htmlspecialchars($playerMeta ? implode(' · ', $playerMeta) : 'No jersey or role set') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="tvr-cell-muted">
                                                <span class="d-block"><?= htmlspecialchars((string) $player_row['age']) ?> yrs</span>
                                                <span class="d-block text-capitalize"><?= htmlspecialchars((string) $player_row['gender']) ?></span>
                                            </td>
                                            <td class="tvr-docs-cell">
                                                <?php if ($playerDocumentRequirements): ?>
                                                    <?php foreach ($playerDocumentRequirements as $requirement):
                                                        $document = $playerDocuments[(int) $player_row['id']][(int) $requirement['id']] ?? null;
                                                        $requirementName = (string) $requirement['name'];
                                                    ?>
                                                    <?php if ($document): ?>
                                                    <?php $quality = $docQualityMeta($document); ?>
                                                    <div class="tvr-doc-item">
                                                        <div class="tvr-doc-item-head">
                                                            <i class="bi bi-file-earmark-text tvr-doc-item-icon" aria-hidden="true"></i>
                                                            <span class="tvr-doc-item-name"><?= htmlspecialchars($requirementName) ?></span>
                                                        </div>
                                                        <div class="tvr-doc-item-foot">
                                                            <a class="tvr-doc-item-link" href="../../includes/downloadDocument.php?source=typed&amp;id=<?= (int) $document['id'] ?>&amp;mode=preview" data-doc-preview data-doc-kind="<?= documentPreviewKind((string) ($document['original_name'] ?? '')) ?>" data-doc-title="<?= htmlspecialchars($requirementName, ENT_QUOTES) ?>" data-doc-subtitle="<?= htmlspecialchars(trim((string) ($document['original_name'] ?? '')) . ' · ' . $playerName, ENT_QUOTES) ?>" aria-label="Preview <?= htmlspecialchars($requirementName, ENT_QUOTES) ?> for <?= htmlspecialchars($playerName, ENT_QUOTES) ?>"><i class="bi bi-eye" aria-hidden="true"></i> View file</a>
                                                            <span class="tvr-doc-quality <?= $quality['class'] ?>"<?= $quality['tooltip'] !== '' ? ' title="' . htmlspecialchars($quality['tooltip'], ENT_QUOTES) . '" data-bs-toggle="tooltip"' : '' ?>><i class="bi <?= $quality['icon'] ?>" aria-hidden="true"></i> <?= htmlspecialchars($quality['label']) ?></span>
                                                        </div>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="tvr-doc-item is-missing">
                                                        <div class="tvr-doc-item-head">
                                                            <i class="bi bi-file-earmark-x tvr-doc-item-icon" aria-hidden="true"></i>
                                                            <span class="tvr-doc-item-name"><?= htmlspecialchars($requirementName) ?></span>
                                                            <span class="lp-row-status is-cancelled">Not submitted</span>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php if (!empty($player_row['document'])): ?>
                                                    <div class="tvr-doc-item">
                                                        <div class="tvr-doc-item-head">
                                                            <i class="bi bi-file-earmark-text tvr-doc-item-icon" aria-hidden="true"></i>
                                                            <span class="tvr-doc-item-name">Eligibility document</span>
                                                        </div>
                                                        <div class="tvr-doc-item-foot">
                                                            <a class="tvr-doc-item-link" href="../../includes/downloadDocument.php?source=player&amp;id=<?= (int) $player_row['id'] ?>&amp;mode=preview" data-doc-preview data-doc-kind="<?= documentPreviewKind((string) $player_row['document']) ?>" data-doc-title="Eligibility document" data-doc-subtitle="<?= htmlspecialchars($playerName, ENT_QUOTES) ?>"><i class="bi bi-eye" aria-hidden="true"></i> View file</a>
                                                        </div>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="tvr-doc-item is-missing">
                                                        <div class="tvr-doc-item-head">
                                                            <i class="bi bi-file-earmark-x tvr-doc-item-icon" aria-hidden="true"></i>
                                                            <span class="tvr-doc-item-name">Eligibility document</span>
                                                            <span class="lp-row-status is-cancelled">Not submitted</span>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="tvr-elig">
                                                    <span class="lp-row-status <?= $eligibilityClass ?>"><?= htmlspecialchars($eligibilityLabel) ?></span>
                                                    <?php if ($eligibilityRemarks !== ''): ?>
                                                        <span class="tvr-elig-remarks"><?= htmlspecialchars($eligibilityRemarks) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($canReview): ?>
                                                <div class="dropdown">
                                                    <button class="tvr-btn tvr-btn-neutral dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Set eligibility for <?= htmlspecialchars($playerName, ENT_QUOTES) ?>"><i class="bi bi-person-check" aria-hidden="true"></i> Set status</button>
                                                    <ul class="dropdown-menu dropdown-menu-end tvr-decision-menu">
                                                        <li><h6 class="dropdown-header">Player eligibility</h6></li>
                                                        <li><button type="button" class="dropdown-item text-success player-eligibility-action" data-player-id="<?= (int) $player_row['id'] ?>" data-player-name="<?= htmlspecialchars($playerName, ENT_QUOTES) ?>" data-decision="Eligible"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>Eligible<small>Cleared to play</small></button></li>
                                                        <li><button type="button" class="dropdown-item text-warning player-eligibility-action" data-player-id="<?= (int) $player_row['id'] ?>" data-player-name="<?= htmlspecialchars($playerName, ENT_QUOTES) ?>" data-decision="Needs Correction"><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i>Needs correction<small>Ask the representative to fix this player</small></button></li>
                                                        <li><button type="button" class="dropdown-item text-danger player-eligibility-action" data-player-id="<?= (int) $player_row['id'] ?>" data-player-name="<?= htmlspecialchars($playerName, ENT_QUOTES) ?>" data-decision="Ineligible"><i class="bi bi-x-circle me-2" aria-hidden="true"></i>Ineligible<small>Cannot play in this league</small></button></li>
                                                    </ul>
                                                </div>
                                                <?php else: ?>
                                                <span class="tvr-locked"><i class="bi bi-lock-fill" aria-hidden="true"></i> Locked</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="tvr-empty" id="tvrRosterEmpty" hidden>
                                <i class="bi bi-search"></i>
                                <p>No players match that search.</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- ==================== DECISION BAR (below the roster) ==================== -->
                        <div class="tvr-actionbar dash-animate dash-animate-d4">
                            <?php if ($canReview): ?>
                            <div class="tvr-decision" role="group" aria-label="Application decision">
                                <form id="approvedForm">
                                    <button class="tvr-btn tvr-btn-approve" type="submit"><i class="bi bi-check-circle" aria-hidden="true"></i> Approve team</button>
                                    <input type="hidden" name="team_id" value="<?= $id ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                </form>
                                <form id="returnForm">
                                    <?php if ($nothingToCorrect): ?>
                                    <button class="tvr-btn tvr-btn-return" type="button" disabled aria-disabled="true" title="No player is marked &ldquo;Needs correction&rdquo; — mark the player(s) who must fix something first, or approve / reject this registration."><i class="bi bi-arrow-return-left" aria-hidden="true"></i> Return for correction</button>
                                    <?php else: ?>
                                    <button class="tvr-btn tvr-btn-return" type="submit"><i class="bi bi-arrow-return-left" aria-hidden="true"></i> Return for correction</button>
                                    <?php endif; ?>
                                    <input type="hidden" name="team_id" value="<?= $id ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                </form>
                                <form id="rejectForm">
                                    <button class="tvr-btn tvr-btn-reject" type="submit"><i class="bi bi-x-octagon" aria-hidden="true"></i> Reject</button>
                                    <input type="hidden" name="team_id" value="<?= $id ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                </form>
                            </div>
                            <?php else: ?>
                            <span class="tvr-state"><i class="bi <?= $statusIcon ?>" aria-hidden="true"></i> Application <?= htmlspecialchars($teamStatus) ?> </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($teamDocumentRequirements): ?>
                        <!-- ==================== TEAM DOCUMENTS ==================== -->
                        <div class="activity-section dash-animate dash-animate-d5">
                            <div class="activity-header">
                                <div class="activity-title">
                                    <i class="bi bi-folder2-open"></i> Team documents
                                    <span class="activity-badge-count"><?= count($teamDocuments) ?> of <?= count($teamDocumentRequirements) ?> submitted</span>
                                </div>
                            </div>
                            <div class="tvr-doc-grid">
                                <?php foreach ($teamDocumentRequirements as $requirement):
                                    $document = $teamDocuments[(int) $requirement['id']] ?? null;
                                    $requirementName = (string) $requirement['name'];
                                ?>
                                <div class="tvr-doc-card">
                                    <div class="tvr-doc-head">
                                        <span class="tvr-doc-icon" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span>
                                        <div>
                                            <div class="tvr-doc-name"><?= htmlspecialchars($requirementName) ?></div>
                                            <?php if (!empty($requirement['is_required'])): ?>
                                                <span class="tvr-chip tvr-chip-neutral">Required</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($document): ?>
                                        <?php
                                        $review  = $docReviewMeta($document);
                                        $quality = $docQualityMeta($document);
                                        $remarks = $cleanRemarks($document['review_remarks'] ?? '');
                                        ?>
                                        <div class="tvr-doc-meta">
                                            <span class="lp-row-status <?= $review['class'] ?>"><?= htmlspecialchars($review['label']) ?></span>
                                            <span class="tvr-doc-quality <?= $quality['class'] ?>"<?= $quality['tooltip'] !== '' ? ' title="' . htmlspecialchars($quality['tooltip'], ENT_QUOTES) . '" data-bs-toggle="tooltip"' : '' ?>><i class="bi <?= $quality['icon'] ?>" aria-hidden="true"></i> <?= htmlspecialchars($quality['label']) ?></span>
                                        </div>
                                        <?php if ($remarks !== ''): ?><p class="tvr-doc-item-remarks">Note: <?= htmlspecialchars($remarks) ?></p><?php endif; ?>
                                        <div class="tvr-doc-actions">
                                            <a class="tvr-btn tvr-btn-neutral" href="../../includes/downloadDocument.php?source=typed&amp;id=<?= (int) $document['id'] ?>&amp;mode=preview" data-doc-preview data-doc-kind="<?= documentPreviewKind((string) ($document['original_name'] ?? '')) ?>" data-doc-title="<?= htmlspecialchars($requirementName, ENT_QUOTES) ?>" data-doc-subtitle="<?= htmlspecialchars(trim((string) ($document['original_name'] ?? '')), ENT_QUOTES) ?>" aria-label="Preview <?= htmlspecialchars($requirementName, ENT_QUOTES) ?> document"><i class="bi bi-eye" aria-hidden="true"></i> View document</a>
                                            <?php if ($canReview && $review['status'] === 'Pending'): ?>
                                                <button type="button" class="tvr-btn tvr-btn-approve document-review-action" data-document-id="<?= (int) $document['id'] ?>" data-decision="Accepted" aria-label="Accept <?= htmlspecialchars($requirementName, ENT_QUOTES) ?> document"><i class="bi bi-check-lg" aria-hidden="true"></i> Accept</button>
                                                <button type="button" class="tvr-btn tvr-btn-reject document-review-action" data-document-id="<?= (int) $document['id'] ?>" data-decision="Rejected" aria-label="Reject <?= htmlspecialchars($requirementName, ENT_QUOTES) ?> document"><i class="bi bi-x-lg" aria-hidden="true"></i> Reject</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="tvr-doc-meta"><span class="lp-row-status is-cancelled">Not submitted</span></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div><!-- /card-body -->
                </div><!-- /card -->
            </div><!-- /row -->
        </div><!-- /container-fluid -->

        <?php
        include 'includes/footer.php'; ?>
        <?php include __DIR__ . '/../../includes/documentPreviewModal.php'; ?>
        <script>
            /* Roster search — filters the player rows without a page reload. */
            (function () {
                var input = document.getElementById('tvrRosterSearch');
                var table = document.getElementById('tvrRosterTable');
                if (!input || !table) return;
                var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-search]'));
                var empty = document.getElementById('tvrRosterEmpty');
                input.addEventListener('input', function () {
                    var term = this.value.trim().toLowerCase();
                    var visible = 0;
                    rows.forEach(function (row) {
                        var haystack = row.getAttribute('data-search') || '';
                        var match = term === '' || haystack.indexOf(term) !== -1;
                        row.hidden = !match;
                        if (match) visible++;
                    });
                    if (empty) empty.hidden = visible !== 0;
                });
            })();
                        $(document).on('submit', '#rejectForm', function(e) {
                            e.preventDefault();
                            var teamId = $(this).find('input[name="team_id"]').val();
                            Swal.fire({
                                title: 'Reject this application permanently?',
                                input: 'textarea',
                                inputLabel: 'Final rejection reason',
                                inputPlaceholder: 'Explain why this application cannot be resubmitted...',
                                inputAttributes: { required: true },
                                showCancelButton: true,
                                confirmButtonText: 'Reject',
                                confirmButtonColor: '#dc3545',
                                preConfirm: (remarks) => {
                                    if (!remarks.trim()) {
                                        Swal.showValidationMessage('Remarks is required');
                                    }
                                    return remarks;
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    var formData = new FormData();
                                    formData.append('reject', true);
                                    formData.append('team_id', teamId);
                                    formData.append('remarks', result.value);
                                    formData.append('csrf_token', $('#rejectForm input[name="csrf_token"]').val());
                                    $.ajax({
                                        url: "action/leagueAction.php",
                                        type: "post",
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(response) {
                                            var res = jQuery.parseJSON(response);
                                            if (res.status == 201) {
                                                Toast.fire({ icon: "success", title: res.msg }).then(() => location.reload());
                                            } else {
                                                Swal.fire({ icon: 'error', title: 'Something Went Wrong.', text: res.msg });
                                            }
                                        }
                                    });
                                }
                            });
                        });
                        $(document).on('submit', '#returnForm', function(e) {
                            e.preventDefault();
                            const teamId = $(this).find('input[name="team_id"]').val();
                            const csrfToken = $(this).find('input[name="csrf_token"]').val();
                            const correctionPlayers = <?= json_encode($correctionPlayerNames, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
                            const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
                            const playerList = correctionPlayers.map(name => '<li>' + escapeHtml(name) + '</li>').join('');
                            Swal.fire({
                                title: 'Return for correction?',
                                html: `<div class="text-start px-2">
                                    <p class="mb-2">This sends the application back to the representative. Only the ${correctionPlayers.length} player${correctionPlayers.length === 1 ? '' : 's'} marked <strong>Needs correction</strong> can be edited, together with the team name, logo and team documents:</p>
                                    <ul class="mb-3 ps-4">${playerList}</ul>
                                    <p class="mb-0">They have <strong>72 hours</strong> to resubmit. Without a resubmission the application expires and can no longer be corrected.</p>
                                </div>`,
                                showCancelButton: true,
                                confirmButtonText: 'Return Application',
                                confirmButtonColor: '#d39e00'
                            }).then(result => {
                                if (!result.isConfirmed) return;
                                const formData=new FormData(); formData.append('requestCorrection','1'); formData.append('team_id',teamId); formData.append('csrf_token',csrfToken);
                                $.ajax({url:'action/leagueAction.php',type:'post',data:formData,processData:false,contentType:false,success:response=>{
                                    const res=typeof response==='string'?JSON.parse(response):response;
                                    if(res.status===201) Toast.fire({icon:'success',title:res.msg}).then(()=>location.reload());
                                    else Swal.fire({icon:'error',title:'Unable to return application',text:res.msg});
                                }});
                            });
                        });
                        $(document).on('submit', '#approvedForm', function(e) {
                            e.preventDefault();
                            var formData = new FormData(this);
                            formData.append("approved", true);
                            Swal.fire({
                                title: 'Are you sure you want to approved this team?',
                                showDenyButton: false,
                                showCancelButton: true,
                                confirmButtonText: 'Yes',
                            }).then((result) => {
                                /* Read more about isConfirmed, isDenied below */
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: "action/leagueAction.php",
                                        type: "post",
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(response) {
                                            var res = jQuery.parseJSON(response);
                                            if (res.status != 201) {
                                                Swal.fire({
                                                    icon: 'error',
                                                    title: 'Unable to approve team',
                                                    text: res.msg,
                                                    timer: 10000
                                                })
                                            } else if (res.status == 201) {
                                                Toast.fire({
                                                    icon: "success",
                                                    title: res.msg
                                                }).then(() => {
                                                    location.reload();

                                                });
                                            }
                                        }
                                    })
                                } else if (result.isDenied) {
                                    Swal.fire('Changes are not saved', '', 'info').then(function() {
                                        location.reload();
                                    });
                                }
                            })
                        });
                        $(document).on('click','.player-eligibility-action',function(){
                            const button=this,decision=button.dataset.decision,playerId=button.dataset.playerId,playerName=button.dataset.playerName;
                            const needsRemarks=decision!=='Eligible';
                            Swal.fire({
                                title:`Mark ${playerName} as ${decision}?`,
                                input:needsRemarks?'textarea':undefined,
                                inputLabel:needsRemarks?'Remarks':'' ,
                                inputPlaceholder:decision==='Needs Correction'?'Explain what the representative must correct...':'State why this player cannot join the league...',
                                showCancelButton:true,
                                confirmButtonText:decision==='Needs Correction'?'Mark for Correction':`Mark ${decision}`,
                                confirmButtonColor:decision==='Eligible'?'#198754':(decision==='Ineligible'?'#dc3545':'#d39e00'),
                                preConfirm:value=>{if(needsRemarks&&!String(value||'').trim())return Swal.showValidationMessage('Remarks are required.');return String(value||'').trim();}
                            }).then(result=>{
                                if(!result.isConfirmed)return;
                                const data=new FormData();data.append('playerEligibility','1');data.append('team_id','<?= (int)$id ?>');data.append('player_id',playerId);data.append('decision',decision);data.append('remarks', typeof result.value === 'string' ? result.value : '');data.append('csrf_token','<?= htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8') ?>');
                                fetch('action/leagueAction.php',{method:'POST',body:data}).then(response=>response.json()).then(response=>{
                                    if(response.status===201) Swal.fire({icon:'success',title:'Saved',text:response.msg}).then(()=>location.reload());
                                    else Swal.fire({icon:'error',title:'Unable to save',text:response.msg});
                                }).catch(()=>Swal.fire({icon:'error',title:'Unable to save',text:'The server request failed.'}));
                            });
                        });
                        // Document review action handler
                        $(document).on('click','.document-review-action',function(){
                            const button=this,decision=button.dataset.decision,documentId=button.dataset.documentId;
                            const needsRemarks=decision==='Rejected';
                            
                            // Disable button to prevent double-clicks
                            button.disabled=true;
                            button.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
                            
                            Swal.fire({
                                title:`Mark this document as ${decision}?`,
                                input:needsRemarks?'textarea':undefined,
                                inputLabel:needsRemarks?'Rejection reason':'',
                                inputPlaceholder:'Explain why this document is not acceptable...',
                                showCancelButton:true,
                                confirmButtonText:decision==='Accepted'?'Accept Document':'Reject Document',
                                confirmButtonColor:decision==='Accepted'?'#198754':'#dc3545',
                                preConfirm:value=>{if(needsRemarks&&!String(value||'').trim())return Swal.showValidationMessage('Remarks are required.');return String(value||'').trim();}
                            }).then(result=>{
                                if(!result.isConfirmed){
                                    // Re-enable button if cancelled
                                    button.disabled=false;
                                    button.innerHTML=decision==='Accepted'?'<i class="bi bi-check-circle"></i> Accept':'<i class="bi bi-x-circle"></i> Reject';
                                    return;
                                }
                                const data=new FormData();data.append('documentReview','1');data.append('document_id',documentId);data.append('decision',decision);data.append('remarks', typeof result.value === 'string' ? result.value : '');data.append('csrf_token','<?= htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8') ?>');
                                
                                // Add timeout to fetch
                                const controller=new AbortController();
                                const timeoutId=setTimeout(()=>controller.abort(),15000);
                                
                                fetch('action/leagueAction.php',{method:'POST',body:data,signal:controller.signal})
                                    .then(response=>{
                                        clearTimeout(timeoutId);
                                        if(!response.ok) throw new Error('HTTP '+response.status);
                                        return response.json();
                                    })
                                    .then(response=>{
                                        if(response.status===201) {
                                            Swal.fire({icon:'success',title:'Saved',text:response.msg}).then(()=>location.reload());
                                        } else if(response.status===429) {
                                            Swal.fire({icon:'warning',title:'Rate Limited',text:response.msg});
                                            button.disabled=false;
                                            button.innerHTML=decision==='Accepted'?'<i class="bi bi-check-circle"></i> Accept':'<i class="bi bi-x-circle"></i> Reject';
                                        } else {
                                            Swal.fire({icon:'error',title:'Unable to save',text:response.msg});
                                            button.disabled=false;
                                            button.innerHTML=decision==='Accepted'?'<i class="bi bi-check-circle"></i> Accept':'<i class="bi bi-x-circle"></i> Reject';
                                        }
                                    })
                                    .catch(err=>{
                                        clearTimeout(timeoutId);
                                        let errorMsg='The server request failed.';
                                        if(err.name==='AbortError') errorMsg='Request timed out. Please try again.';
                                        else if(err.message.includes('HTTP')) errorMsg='Server error: '+err.message;
                                        Swal.fire({icon:'error',title:'Unable to save',text:errorMsg});
                                        button.disabled=false;
                                        button.innerHTML=decision==='Accepted'?'<i class="bi bi-check-circle"></i> Accept':'<i class="bi bi-x-circle"></i> Reject';
                                    });
                            }).catch(()=>{
                                // Re-enable button on any error
                                button.disabled=false;
                                button.innerHTML=decision==='Accepted'?'<i class="bi bi-check-circle"></i> Accept':'<i class="bi bi-x-circle"></i> Reject';
                            });
                        });
                        // Initialize Bootstrap tooltips for quality metrics
                        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                        tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
        </script>

    </body>

    </html>
