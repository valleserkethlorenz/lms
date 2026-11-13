<?php
/**
 * Correction resubmission — dedicated page.
 *
 * Replaces the #editReapplyModal modal that used to live in app/tr/view_team.php.
 * Same reasoning as app/tr/register_team.php: the correction form is long and
 * document-heavy, so a refresh, Back or an accidental navigation must not wipe
 * the files the representative just picked, and the URL stays resumable.
 *
 * Access: Team Representative (role 2) only, and only while the correction
 * window is still open. The submit endpoint is unchanged
 * (action/reapplyTeam.php, JSON), as are the rules it enforces: only players
 * marked "Needs Correction" (plus newly added players) can change.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../config/dbconnect.php';
/** @var mysqli $conn */
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/teamCorrectionDeadline.php';
require_once __DIR__ . '/../../includes/rosterSize.php';

requireRole([2], getProjectBasePath() . '/login.php');
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = 2;

$id = (int) ($_GET['id'] ?? 0);

expireTeamCorrectionDeadlines($conn);

$stmt = $conn->prepare("SELECT * FROM tbl_team WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $id, $userId);
$stmt->execute();
$team_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$team_row) {
    header('Location: league.php');
    exit;
}

$viewUrl = 'view_team.php?id=' . (int) $id;

/* ------------------------------------------------------------------
   League context + roster limits
   ------------------------------------------------------------------ */
$leagueStmt = $conn->prepare("SELECT id, league_name, sport, division, category, venue, min_player, max_player FROM tbl_league WHERE id = ? LIMIT 1");
$leagueStmt->bind_param('i', $team_row['league_id']);
$leagueStmt->execute();
$league_row = $leagueStmt->get_result()->fetch_assoc() ?: [];
$leagueStmt->close();

$minimumPlayers    = max(1, (int) ($league_row['min_player'] ?? 5));
$configuredMaximum = (int) ($league_row['max_player'] ?? $minimumPlayers);
// max_player 0 means "no upper limit" — same convention as eligibilityValidator.
$maximumPlayers = $configuredMaximum > 0 ? max($minimumPlayers, $configuredMaximum) : 0;
$rosterIsFixed  = isFixedRosterSize($minimumPlayers, $maximumPlayers);


/* ------------------------------------------------------------------
   Roster + submitted documents
   ------------------------------------------------------------------ */
$players = [];
$ps = $conn->prepare("SELECT * FROM tbl_player WHERE team_id = ? ORDER BY id ASC");
$ps->bind_param('i', $id);
$ps->execute();
$playerResult = $ps->get_result();
while ($p = $playerResult->fetch_assoc()) {
    $players[] = $p;
}
$ps->close();

$submittedDocuments = [];
$docStmt = $conn->prepare("SELECT d.id,d.player_id,d.requirement_id,d.original_name,d.quality_status,u.name AS document_name,p.name AS player_name,r.applies_to FROM tbl_submitted_document d JOIN tbl_league_document_requirement r ON r.id=d.requirement_id JOIN tbl_utility u ON u.id=r.document_type_id LEFT JOIN tbl_player p ON p.id=d.player_id WHERE d.team_id=? ORDER BY r.applies_to,u.name,p.name");
$docStmt->bind_param('i', $id);
$docStmt->execute();
$docResult = $docStmt->get_result();
while ($doc = $docResult->fetch_assoc()) {
    $submittedDocuments[] = $doc;
}
$docStmt->close();

$playerSubmittedDocuments = [];
$teamSubmittedDocuments   = [];
foreach ($submittedDocuments as $document) {
    if ($document['applies_to'] === 'player' && !empty($document['player_id'])) {
        $playerSubmittedDocuments[(int) $document['player_id']][] = $document;
    } elseif ($document['applies_to'] === 'team') {
        $teamSubmittedDocuments[] = $document;
    }
}

/* ------------------------------------------------------------------
   Display values
   ------------------------------------------------------------------ */
$teamStatus = trim((string) ($team_row['status'] ?? 'Pending')) ?: 'Pending';
$statusKey  = strtolower($teamStatus);
if ($statusKey === 'correction requested') $statusKey = 'correction';
if ($statusKey === 'canceled') $statusKey = 'cancelled';

$deadlineTs     = !empty($team_row['correction_due_at']) ? strtotime((string) $team_row['correction_due_at']) : null;
$deadlineLabel  = $deadlineTs ? date('M d, Y · h:i A', $deadlineTs) : '';
$deadlineHours  = $deadlineTs ? max(0, (int) ceil(($deadlineTs - time()) / 3600)) : null;
$deadlineUrgent = $deadlineHours !== null && $deadlineHours <= 12;

$canResubmit = $statusKey === 'correction' && $deadlineTs !== null && $deadlineTs >= time();

$rosterCount = count($players);
$correctionCount = 0;
$eligibleCount   = 0;
foreach ($players as $playerRow) {
    $eligibilityState = strtolower((string) ($playerRow['eligibility_status'] ?? 'Pending'));
    if ($eligibilityState === 'needs correction') $correctionCount++;
    if ($eligibilityState === 'eligible')         $eligibleCount++;
}

$rosterLabel   = rosterSizeLabel($minimumPlayers, $maximumPlayers);
$submittedOn   = !empty($team_row['created']) ? date('M d, Y', strtotime((string) $team_row['created'])) : '—';
$leagueName    = trim((string) ($league_row['league_name'] ?? '')) ?: 'League';
$sportLabel    = trim((string) ($league_row['sport'] ?? '')) ?: '—';
$divisionLabel = trim((string) ($league_row['division'] ?? '')) ?: '—';
$categoryLabel = trim((string) ($league_row['category'] ?? '')) ?: '—';
$teamNameSafe  = htmlspecialchars((string) $team_row['team_name'], ENT_QUOTES, 'UTF-8');

$cleanRemarks = static function ($value): string {
    $text = trim((string) $value);
    if (in_array(strtolower($text), ['true', 'false', '1', '0'], true)) return '';
    return $text;
};

$statusLabel = match ($statusKey) {
    'approved'   => 'Approved',
    'pending'    => 'Pending review',
    'correction' => 'Correction requested',
    'rejected'   => 'Rejected',
    'expired'    => 'Expired',
    'cancelled'  => 'Cancelled',
    default      => ucwords($teamStatus),
};

$documentAccept = '.pdf,.jpg,.jpeg,.png,.bmp,.webp,application/pdf,image/jpeg,image/png,image/bmp,image/webp';

include 'includes/header.php';
include 'includes/registration-page-styles.php';
include 'includes/resubmit-page-styles.php';
?>
<body id="page-top" class="body">
<?php include 'includes/bar.php'; ?>

<div class="reg-page">
    <div class="reg-shell">

        <nav class="resubmit-crumbs" aria-label="Breadcrumb">
            <a href="league.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> My teams</a>
            <i class="bi bi-chevron-right resubmit-crumb-sep" aria-hidden="true"></i>
            <a href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $teamNameSafe ?></a>
            <i class="bi bi-chevron-right resubmit-crumb-sep" aria-hidden="true"></i>
            <span class="resubmit-crumb-current" aria-current="page">Correct and resubmit</span>
        </nav>

        <?php
        // ── Hero ──────────────────────────────────────────────────────────
        $heroEyebrow  = 'My applications / Correction';
        $heroTitle    = $teamNameSafe;
        $heroSubtitle = 'Correct the items the organizer flagged, then send the application back for review.';
        $heroIcon     = 'bi-arrow-repeat';
        $heroLabel    = 'Correct and resubmit ' . (string) $team_row['team_name'];
        
        include 'includes/page-hero.php';
        ?>

        

        <?php if (!$canResubmit): ?>
        <div class="reg-state">
            <span class="reg-state-icon"><i class="bi bi-shield-check"></i></span>
            <h2>Nothing to correct right now</h2>
            <p>
                This application is currently marked “<?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>”.
                <?php if ($deadlineTs !== null && $deadlineTs < time()): ?>
                    The correction window closed on <?= htmlspecialchars($deadlineLabel, ENT_QUOTES, 'UTF-8') ?>. Contact the organizer if a correction still has to be sent.
                <?php else: ?>
                    Corrections can only be sent while the organizer has the application marked “Correction requested”.
                <?php endif; ?>
            </p>
            <div class="reg-state-actions">
                <a class="btn btn-primary" href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-arrow-left me-1"></i>View my application</a>
                <a class="btn btn-outline-secondary" href="league.php"><i class="bi bi-list-check me-1"></i>All applications</a>
            </div>
        </div>
        <?php else: ?>
        <div class="reg-body">
            <div class="requirements-banner">
                <span class="requirements-banner-icon"><i class="bi bi-info-circle" aria-hidden="true"></i></span>
                <div>
                    <h3 class="requirements-title">What you can change</h3>
                    <p class="requirements-copy">Only the items the organizer flagged are editable. Everything else stays exactly as it was submitted so the review can compare your corrections against the original entry.</p>
                    <div class="resubmit-scope">
                        <span class="resubmit-scope-item"><i class="bi bi-check2" aria-hidden="true"></i>Team name &amp; logo</span>
                        <span class="resubmit-scope-item"><i class="bi bi-check2" aria-hidden="true"></i>Team-level documents</span>
                        <span class="resubmit-scope-item"><i class="bi bi-check2" aria-hidden="true"></i><?= (int) $correctionCount ?> player<?= $correctionCount === 1 ? '' : 's' ?> marked “Needs correction”</span>
                        <span class="resubmit-scope-item is-locked"><i class="bi bi-lock-fill" aria-hidden="true"></i>Locked players &amp; their files</span>
                    </div>
                    <?php $teamRemarks = $cleanRemarks($team_row['remarks'] ?? ''); ?>
                    <?php if ($teamRemarks !== ''): ?>
                    <div class="resubmit-remarks">
                        <strong>Organizer remarks</strong>
                        <p><?= nl2br(htmlspecialchars($teamRemarks, ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <form id="resubmitTeam" enctype="multipart/form-data">
                <input type="hidden" name="team_id" value="<?= (int) $id ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <!-- 1 — Team identity -->
                <section class="registration-section">
                    <div class="section-heading">
                        <div class="section-heading-main">
                            <span class="section-number">1</span>
                            <div>
                                <h6>Team identity</h6>
                                <p>Fix the team name or logo and replace any team-level document the organizer flagged.</p>
                            </div>
                        </div>
                        <span class="resubmit-count"><?= count($teamSubmittedDocuments) ?> team file<?= count($teamSubmittedDocuments) === 1 ? '' : 's' ?> on record</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <label class="form-label" for="resubmitTeamName">Team name <span class="required-marker" aria-hidden="true">*</span></label>
                            <input type="text" id="resubmitTeamName" name="team_name" class="form-control" value="<?= htmlspecialchars((string) $team_row['team_name'], ENT_QUOTES, 'UTF-8') ?>" minlength="2" maxlength="100" required>
                            <div class="form-text">Keep the official name the organizer should see on the standings.</div>
                        </div>
                        <div class="col-lg-5">
                            <label class="form-label" for="resubmitTeamImage">Team logo</label>
                            <input type="file" id="resubmitTeamImage" name="team_image" class="form-control" accept="image/*">
                            <div class="form-text">Leave empty to keep the current logo · JPG, PNG, WebP or GIF · max 5 MB.</div>
                        </div>
                        <?php foreach ($teamSubmittedDocuments as $document): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="resubmitTeamDoc<?= (int) $document['id'] ?>"><?= htmlspecialchars((string) $document['document_name'], ENT_QUOTES, 'UTF-8') ?> <span class="text-muted">(team file)</span></label>
                            <div class="doc-slot-current"><i class="bi bi-check-circle" aria-hidden="true"></i> <?= htmlspecialchars((string) $document['original_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <input type="file" id="resubmitTeamDoc<?= (int) $document['id'] ?>" name="replacement_documents[<?= (int) $document['id'] ?>]" class="form-control mt-1" accept="<?= $documentAccept ?>">
                            <div class="form-text">Upload a new file to replace the one on record.</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- 2 — Roster -->
                <section class="registration-section">
                    <div class="section-heading">
                        <div class="section-heading-main">
                            <span class="section-number">2</span>
                            <div>
                                <h6>Corrected roster</h6>
                                <p>Only players marked “Needs correction”, and newly added players, can be changed.</p>
                            </div>
                        </div>
                        <span class="resubmit-count"><?= (int) $correctionCount ?> of <?= (int) $rosterCount ?> player<?= $rosterCount === 1 ? '' : 's' ?> need correction</span>
                    </div>

                    <div id="resubmitValidationSummary" class="alert alert-warning d-none" role="alert">
                        <strong>Please fix these issues:</strong>
                        <ul id="resubmitValidationList" class="mb-0 mt-2"></ul>
                    </div>

                    <div class="roster-scroll-hint mb-2"><i class="bi bi-arrow-left-right me-1"></i>Swipe horizontally to view all player fields.</div>
                    <div class="table-responsive roster-table-wrap">
                        <table class="table roster-table align-middle" id="resubmitPlayerTable">
                            <thead>
                                <tr>
                                    <th>Name <span class="required-marker" aria-hidden="true">*</span></th>
                                    <th style="width:96px;">Age <span class="required-marker" aria-hidden="true">*</span></th>
                                    <th style="width:140px;">Role <span class="required-marker" aria-hidden="true">*</span></th>
                                    <th style="width:96px;">Jersey <span class="required-marker" aria-hidden="true">*</span></th>
                                    <th style="width:120px;">Gender <span class="required-marker" aria-hidden="true">*</span></th>
                                    <th>Documents</th>
                                    <th class="text-center" style="width:110px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="resubmitPlayerBody">
                                <?php foreach ($players as $p):
                                    $canEditPlayer = strtolower((string) ($p['eligibility_status'] ?? '')) === 'needs correction';
                                    $rowDocuments  = $playerSubmittedDocuments[(int) $p['id']] ?? [];
                                    $playerRemarks = $cleanRemarks($p['eligibility_remarks'] ?? '');
                                ?>
                                <?php if ($canEditPlayer && $playerRemarks !== ''): ?>
                                <tr class="correction-note">
                                    <td colspan="7">
                                        <div class="alert alert-warning mb-0"><strong>Organizer remark:</strong> <?= htmlspecialchars($playerRemarks, ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr class="player-edit-row <?= $canEditPlayer ? 'is-editable' : 'is-locked' ?>">
                                    <td>
                                        <input type="hidden" name="player_id[]" value="<?= (int) $p['id'] ?>">
                                        <input type="text" name="player_name[]" class="form-control" value="<?= htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') ?>" required <?= $canEditPlayer ? 'onchange="validateReapplyForm()"' : 'readonly' ?>>
                                    </td>
                                    <td>
                                        <input type="number" name="player_age[]" class="form-control" min="1" max="99" value="<?= (int) $p['age'] ?>" required <?= $canEditPlayer ? 'onchange="validateReapplyForm()"' : 'readonly' ?>>
                                    </td>
                                    <td>
                                        <input type="text" name="player_role[]" class="form-control" value="<?= htmlspecialchars((string) $p['role'], ENT_QUOTES, 'UTF-8') ?>" required <?= $canEditPlayer ? '' : 'readonly' ?>>
                                    </td>
                                    <td>
                                        <input type="number" name="jersey_number[]" class="form-control" min="0" max="99" value="<?= (int) $p['jersey_number'] ?>" required <?= $canEditPlayer ? '' : 'readonly' ?>>
                                    </td>
                                    <td>
                                        <?php if (!$canEditPlayer): ?><input type="hidden" name="gender[]" value="<?= htmlspecialchars((string) $p['gender'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                                        <select <?= $canEditPlayer ? 'name="gender[]"' : '' ?> class="form-select" required <?= $canEditPlayer ? 'onchange="validateReapplyForm()"' : 'disabled' ?>>
                                            <option value="Male" <?= $p['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= $p['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                    </td>

                                    <td>
                                        <?php if ($rowDocuments): ?>
                                            <input type="file" name="player_document[]" class="d-none" tabindex="-1" aria-hidden="true">
                                            <div class="doc-slots">
                                                <?php foreach ($rowDocuments as $document): ?>
                                                <div>
                                                    <div class="doc-slot-name"><?= htmlspecialchars((string) $document['document_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div class="doc-slot-current"><i class="bi bi-check-circle" aria-hidden="true"></i> <?= htmlspecialchars((string) $document['original_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <?php if ($canEditPlayer): ?>
                                                    <input type="file" name="replacement_documents[<?= (int) $document['id'] ?>]" class="form-control form-control-sm mt-1" accept="<?= $documentAccept ?>">
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php elseif (!empty($p['document'])): ?>
                                            <div class="doc-slots">
                                                <div class="doc-slot-current"><i class="bi bi-check-circle" aria-hidden="true"></i> Document on file</div>
                                                <?php if ($canEditPlayer): ?>
                                                <input type="file" name="player_document[]" class="form-control form-control-sm" accept="<?= $documentAccept ?>">
                                                <?php else: ?>
                                                <input type="file" name="player_document[]" class="d-none" tabindex="-1" aria-hidden="true">
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="doc-slots">
                                                <div class="doc-slot-missing"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> No document</div>
                                                <?php if ($canEditPlayer): ?>
                                                <input type="file" name="player_document[]" class="form-control form-control-sm" accept="<?= $documentAccept ?>" required>
                                                <?php else: ?>
                                                <input type="file" name="player_document[]" class="d-none" tabindex="-1" aria-hidden="true">
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($canEditPlayer): ?>
                                        <button type="button" class="btn btn-outline-danger resubmit-remove" onclick="removeEditRow(this)" aria-label="Remove player"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                        <?php else: ?>
                                        <span class="locked-chip"><i class="bi bi-lock-fill" aria-hidden="true"></i> Locked</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mt-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addPlayerButton" onclick="addEditRow()">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i> Add player
                        </button>
                        
                    </div>
                </section>

                <!-- 3 — Before you resubmit -->
                <section class="registration-section">
                    <div class="section-heading">
                        <div class="section-heading-main">
                            <span class="section-number">3</span>
                            <div>
                                <h6>Before you resubmit</h6>
                                <p>What happens once the corrected application is sent.</p>
                            </div>
                        </div>
                        <?php if ($deadlineLabel !== ''): ?>
                        <span class="resubmit-deadline<?= $deadlineUrgent ? ' is-urgent' : '' ?>">
                            <i class="bi bi-alarm" aria-hidden="true"></i>
                            <?= $deadlineUrgent ? 'Due soon — ' : 'Closes ' ?><?= htmlspecialchars($deadlineLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <ul class="resubmit-checklist">
                        <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Corrected players go back into the organizer’s review queue as <strong>Pending</strong>. Players already marked eligible stay eligible.</span></li>
                        <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Every file you pick is checked for blur and readability before it is saved, so a photo of a document works better than a screenshot.</span></li>
                        <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>The organizer is notified as soon as the resubmission is stored — no need to message them separately.</span></li>
                        <li class="is-caution"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>Files you leave empty keep their current copy. Anything you upload replaces the previous file.</span></li>
                    </ul>
                </section>

                <div class="resubmit-progress" id="resubmitProgress" role="status" aria-live="polite">
                    <div class="resubmit-progress-head">
                        <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
                        <span id="resubmitProgressTitle">Uploading documents…</span>
                    </div>
                    <div class="resubmit-progress-bar" aria-hidden="true"><span></span></div>
                    <p class="resubmit-progress-note" id="resubmitProgressNote">Keep this tab open while we upload and check every file.</p>
                </div>

                <div class="reg-actionbar">
                    <div class="reg-actionbar-meta">
                        <span>Resubmitting sends the corrected application back to the organizer’s review queue.</span>
                        
                    </div>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="resubmitSubmit">
                        <i class="bi bi-send-check me-1" aria-hidden="true"></i>Resubmit application
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </div><!-- /reg-shell -->
</div><!-- /reg-page -->

<?php include 'includes/footer.php'; ?>

<?php if ($canResubmit): ?>
<script>
window.LMS_RESUBMIT = <?= json_encode([
    'teamId'       => (int) $id,
    'api'          => 'action/reapplyTeam.php',
    'redirect'     => $viewUrl,
    'minPlayers'   => (int) $minimumPlayers,
    'maxPlayers'   => (int) $maximumPlayers,
    'rosterIsFixed'=> (bool) $rosterIsFixed,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="js/resubmit-team.js"></script>
<?php endif; ?>
</body>
</html>

